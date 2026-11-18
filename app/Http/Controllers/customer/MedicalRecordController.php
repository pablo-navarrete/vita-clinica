<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Allergie;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Customer;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Specialty;
use App\Models\User;
use App\Support\PatientAuditLogger;
use App\Support\MedicalRecordFhirDocument;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class MedicalRecordController extends Controller
{

    public function exportFhirR4($customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $patient = $this->patientForExport($customerId, (int) $id);
        $clinic = Customer::findOrFail($customerId);
        $document = MedicalRecordFhirDocument::make($patient, $clinic);
        PatientAuditLogger::recordExport($patient, 'FHIR R4 JSON');
        $filename = 'ficha-clinica-' . $patient->id . '-fhir-r4.json';

        return response(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/fhir+json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function exportPdfA($customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $patient = $this->patientForExport($customerId, (int) $id);
        $clinic = Customer::findOrFail($customerId);
        $tempDirectory = storage_path('app/mpdf');
        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $tempDirectory,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 18,
        ]);
        $pdf->PDFA = true;
        $pdf->PDFAauto = true;
        $pdf->SetTitle('Ficha clínica - ' . $patient->name);
        $pdf->SetAuthor($clinic->legal_name ?: $clinic->name);
        $pdf->SetCreator('VITA Clínica');
        // mPDF requires three footer cells, separated by two pipes.
        $pdf->SetFooter('Documento clínico confidencial||Página {PAGENO} de {nbpg}');
        $pdf->WriteHTML(view('customer.patient.medical-record-export-pdfa', [
            'patient' => $patient,
            'clinic' => $clinic,
            'generatedAt' => now(),
            'record' => $patient->medicalRecord,
        ])->render());

        $contents = $pdf->Output('', 'S');
        PatientAuditLogger::recordExport($patient, 'PDF/A-1b');

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ficha-clinica-' . $patient->id . '-pdf-a-1b.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function patientForExport(int $customerId, int $patientId): Patient
    {
        $patient = Patient::query()
            ->where('customer_id', $customerId)
            ->with([
                'insurance',
                'clinicBranch',
                'documents.clinicBranch',
                'documents.uploader',
                'medicalRecord.allergies',
                'medicalRecord.chronicDiseases',
                'medicalRecord.regularMedications',
                'medicalRecord.previousSurgeries',
                'encounters' => fn ($query) => $query->where('customer_id', $customerId)->with([
                    'professional:id,name,email,status', 'specialty:id,name', 'clinicBranch', 'observations.author', 'observations.clinicBranch', 'signature.documentVersion', 'addendumSignatures.documentVersion',
                    'appointment' => fn ($appointment) => $appointment->withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)->with('clinicBranch'),
                ]),
            ])
            ->findOrFail($patientId);
        foreach ($patient->encounters as $encounter) {
            app(\App\Services\Signatures\SignedEncounterProjection::class)->apply($encounter, true);
            app(\App\Services\Signatures\EncounterAddendumReader::class)->read($encounter, true);
        }
        return $patient;
    }
  

    public function show($customer, $id)
    {
        $customerId = app('customer')->id;
        $loggedUser = auth()->user();
        $canCreateEncounter = $loggedUser
            && (int) $loggedUser->customer_id === (int) $customerId
            && (int) $loggedUser->status === 1
            && $loggedUser->roles()->where('roles.name', 'Doctor')->exists();
        $patient = Patient::with('insurance')->where('customer_id', $customerId)->findOrFail($id);

        $medicalRecord = $patient->medicalRecord;
        if (!$medicalRecord) {
            $medicalRecord = $patient->medicalRecord()->create(['customer_id' => $customerId]);
            PatientAuditLogger::record($patient, 'created', 'Ficha clínica', [], ['ficha' => 'Ficha clínica creada']);
        } elseif ($medicalRecord->customer_id === null) {
            $medicalRecord->update(['customer_id' => $customerId]);
        } elseif ((int) $medicalRecord->customer_id !== (int) $customerId) {
            abort(404);
        }
        $allergies = $medicalRecord->allergies;

        $chronicDiseases  = $medicalRecord->chronicDiseases ;

        $regularMedications  = $medicalRecord->regularMedications ;

        $previousSurgeries  = $medicalRecord->previousSurgeries ;

        $documents = PatientDocument::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->with(['encounter.professional', 'encounter.appointment.professional', 'clinicBranch', 'uploader'])
            ->latest()
            ->get();

        $encountersQuery = $patient->encounters()->where('customer_id', $customerId);
        $historyQuery = (clone $encountersQuery)->whereIn('status', [2, 3]);
        if (request()->filled('history_encounter_id')) {
            request()->validate(['history_encounter_id' => ['integer', 'min:1']]);
            $selectedHistory = (clone $encountersQuery)->whereIn('status', [2, 3])
                ->findOrFail(request()->integer('history_encounter_id'));
            $historyQuery->whereKey($selectedHistory->id);
        }


        if (request()->filled('search')) {
            $search = trim(request('search'));
            $historyQuery->where(function ($query) use ($search) {
                $query->where('chief_complaint', 'like', "%{$search}%")
                    ->orWhere('clinical_assessment', 'like', "%{$search}%")
                    ->orWhere('treatment_plan', 'like', "%{$search}%")
                    ->orWhereHas('professional', fn ($professional) => $professional->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('appointment.professional', fn ($professional) => $professional->where('name', 'like', "%{$search}%"));
            });
        }

        if (request()->filled('professional_id')) {
            $professionalId = request('professional_id');
            $historyQuery->where(function ($query) use ($professionalId) {
                $query->where('professional_id', $professionalId)
                    ->orWhere(function ($legacyQuery) use ($professionalId) {
                        $legacyQuery->whereNull('professional_id')
                            ->whereHas('appointment', fn ($appointment) => $appointment->where('professional_id', $professionalId));
                    });
            });
        }

        if (request()->filled('specialty_id')) {
            $specialtyId = request('specialty_id');
            $historyQuery->where(function ($query) use ($specialtyId) {
                $query->where('specialty_id', $specialtyId)
                    ->orWhere(function ($legacyQuery) use ($specialtyId) {
                        $legacyQuery->whereNull('specialty_id')
                            ->whereHas('appointment', fn ($appointment) => $appointment->where('specialty_id', $specialtyId));
                    });
            });
        }

        $encounters = $historyQuery
            ->with('professional', 'specialty', 'clinicBranch', 'openedBy', 'createdBy', 'appointment.professional', 'appointment.specialty', 'telemedicineSession', 'observations.author', 'observations.clinicBranch', 'signature.documentVersion', 'addendumSignatures.documentVersion')
            ->orderByDesc('started_at')
            ->paginate(10)
            ->withQueryString();

        $professionalOptionsQuery = User::where('customer_id', $customerId)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'));
        $activeBranchId = \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), (int) $customerId)?->id;
        if (request()->filled('specialty_id')) {
            $professionalOptionsQuery->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', request('specialty_id'))
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $activeBranchId ?? 0));
        }
        $professionalOptions = $professionalOptionsQuery
            ->orderBy('name')
            ->get(['id', 'name']);
        $specialtyOptions = Specialty::where('customer_id', $customerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Estado 2 corresponde a una atención terminada.
        $totalAttentions = (clone $encountersQuery)->where('status', 2)->count();
        $lastAttention = (clone $encountersQuery)
            ->where('status', 2)
            ->with('professional', 'appointment.professional')
            ->orderByRaw('COALESCE(ended_at, started_at, created_at) DESC')
            ->first();
        $lastAttentionDate = $lastAttention?->started_at
            ?? $lastAttention?->appointment?->start_at
            ?? $lastAttention?->created_at;
        $lastAttentionProfessional = $lastAttention?->professional?->name ?? $lastAttention?->appointment?->professional?->name;
        $specialties = $canCreateEncounter
            ? $loggedUser->specialties()
                ->wherePivot('customer_id', $customerId)
                ->wherePivot('clinic_branch_id', $activeBranchId ?? 0)
                ->where('specialties.status', 1)
                ->orderBy('specialties.name')
                ->get(['specialties.id', 'specialties.name'])
            : collect();
        $openEncounterModal = false;
        $preselectedAppointmentId = null;
        $preselectedSpecialtyId = null;
        $preselectedEncounterModality = 'in_person';
        $appointmentToStart = null;
        $requestedAppointmentId = request()->query('start_encounter');

        if ($requestedAppointmentId !== null) {
            abort_unless($canCreateEncounter, 403, 'Solo un médico puede iniciar una atención.');

            $existingForAppointment = (clone $encountersQuery)->where('appointment_id', $requestedAppointmentId)
                ->where(fn ($query) => $query->where('professional_id', $loggedUser->id)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('professional_id')->whereHas('appointment', fn ($query) => $query
                        ->where('professional_id', $loggedUser->id)->where('customer_id', $customerId))))->first();
            if ($existingForAppointment) {
                return redirect()->route('patients.medical-record.show', array_filter([
                    'customer' => $customer, 'id' => $patient->id,
                    'encounter_id' => (int) $existingForAppointment->status === 1 ? $existingForAppointment->id : null,
                ], fn ($value) => $value !== null))->with('info', 'Esta cita ya tiene una atención registrada.');
            }

            $appointmentToStart = Appointment::where('customer_id', $customerId)
                ->where('patient_id', $patient->id)
                ->where('professional_id', $loggedUser->id)
                ->whereIn('status', [0, 1])
                ->with(['professional:id,name', 'specialty:id,name', 'telemedicineSession:id,appointment_id,status,patient_consent_at,ended_at'])
                ->find($requestedAppointmentId);

            $appointmentAlreadyHasEncounter = $appointmentToStart
                && $patient->encounters()->where('customer_id', $customerId)
                    ->where('appointment_id', $appointmentToStart->id)->exists();
            if (!$appointmentToStart || $appointmentAlreadyHasEncounter) {
                $message = 'Esta cita ya no está disponible para iniciar una atención.';

                return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id])
                    ->with('error', $message);
            }

            $openEncounterModal = true;
            $preselectedAppointmentId = $appointmentToStart->id;
            $preselectedSpecialtyId = $appointmentToStart->specialty_id;
            $preselectedEncounterModality = ($appointmentToStart->modality === 'telemedicine')
                ? 'telemedicine'
                : 'in_person';
        }

        $appointmentsQuery = Appointment::where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->whereIn('status', [0, 1])
            ->whereDoesntHave('encounter')
            ->with(['professional:id,name', 'specialty:id,name', 'telemedicineSession:id,appointment_id,status,patient_consent_at,ended_at'])
            ->orderByDesc('start_at')
            ->limit(50);
        $patientAppointments = $canCreateEncounter
            ? $appointmentsQuery->where('professional_id', $loggedUser->id)->get(['id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'status', 'modality', 'clinic_branch_id'])
            : collect();
        if ($appointmentToStart && !$patientAppointments->contains('id', $appointmentToStart->id)) {
            $patientAppointments->prepend($appointmentToStart);
        }

        //status 1 abierta, 2 terminada , 3 cancelada
        $activeEncounters = (clone $encountersQuery)
            ->where('status', 1)
            ->with('professional', 'specialty', 'clinicBranch', 'openedBy', 'createdBy', 'appointment.professional', 'appointment.specialty', 'telemedicineSession')
            ->latest()
            ->get();
        $ownActiveEncounters = $activeEncounters->filter(fn ($encounter) => (int) ($encounter->professional_id ?? $encounter->appointment?->professional_id) === (int) $loggedUser->id);
        if (request()->filled('encounter_id')) {
            request()->validate(['encounter_id' => ['integer']]);
            $currentEncounter = $activeEncounters->firstWhere('id', request()->integer('encounter_id'));
            abort_unless($currentEncounter, 404);
        } else {
            $currentEncounter = $ownActiveEncounters->firstWhere('clinic_branch_id', $activeBranchId)
                ?? $ownActiveEncounters->first() ?? $activeEncounters->first();
        }
        $canManageCurrentEncounter = $canCreateEncounter
            && $currentEncounter
            && (int) ($currentEncounter->professional_id ?? $currentEncounter->appointment?->professional_id) === (int) $loggedUser->id;

        return view('customer.patient.medical-record', compact(
            'currentEncounter',
            'activeEncounters',
            'ownActiveEncounters',
            'encounters',
            'patient',
            'medicalRecord',
            'allergies',
            'chronicDiseases',
            'regularMedications',
            'previousSurgeries',
            'documents',
            'totalAttentions',
            'lastAttentionDate',
            'lastAttentionProfessional',
            'patientAppointments',
            'specialties',
            'canCreateEncounter',
            'canManageCurrentEncounter',
            'openEncounterModal',
            'preselectedAppointmentId',
            'preselectedSpecialtyId',
            'preselectedEncounterModality',
            'professionalOptions',
            'specialtyOptions'
        ));
    }

    public function activity($customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $patient = Patient::query()
            ->where('customer_id', $customerId)
            ->findOrFail($id);
        $branchNames = ClinicBranch::withTrashed()
            ->where('customer_id', $customerId)
            ->pluck('name', 'id');
        $auditLogs = $patient->auditLogs()
            ->with('user:id,name')
            ->paginate(20)
            ->withQueryString();

        return view('customer.patient.activity', compact('patient', 'auditLogs', 'branchNames'));
    }

  
}
