<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\EncounterObservation;
use App\Models\Patient;
use App\Models\ClinicBranch;
use App\Models\TelemedicineSession;
use App\Support\PatientAuditLogger;
use App\Support\ClinicAppointmentMessenger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EncounterController extends Controller
{
    public function store(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $doctor = $this->authenticatedDoctor($customerId);
        $patient = Patient::where('customer_id', $customerId)->findOrFail($id);

        $request->validate(['appointment_id' => ['nullable', 'integer']]);
        if ($request->filled('appointment_id')) {
            $selectedAppointment = $patient->appointments()->where('customer_id', $customerId)
                ->where('professional_id', $doctor->id)->findOrFail($request->integer('appointment_id'));
            $existing = $patient->encounters()->where('customer_id', $customerId)
                ->where('appointment_id', $selectedAppointment->id)->first();
            if ($existing) {
                return $this->existingEncounterRedirect($customer, $existing);
            }
        }

        $validated = $request->validate([
            'chief_complaint' => ['required', 'string', 'max:5000'],
            'modality' => ['required', Rule::in(['in_person', 'telemedicine'])],
            'confirm_open_encounter' => ['nullable', 'boolean'],
            'telemedicine_session_id' => ['nullable', 'integer'],
            'appointment_id' => [
                'nullable',
                Rule::exists('appointments', 'id')->where(fn ($query) => $query
                    ->where('customer_id', $customerId)
                    ->where('patient_id', $patient->id)
                    ->where('professional_id', $doctor->id)
                    ->whereIn('status', [0, 1])),
            ],
            'specialty_id' => [
                Rule::requiredIf(!$request->filled('appointment_id')),
                'nullable',
                Rule::exists('specialties', 'id')->where('customer_id', $customerId),
            ],
        ], [
            'chief_complaint.required' => 'Ingresa el motivo de la atención.',
            'appointment_id.exists' => 'La cita seleccionada no corresponde a este paciente.',
            'specialty_id.required' => 'Selecciona la especialidad de la atención.',
        ]);

        $appointment = !empty($validated['appointment_id'])
            ? $patient->appointments()->where('customer_id', $customerId)->findOrFail($validated['appointment_id'])
            : null;
        $professionalId = $doctor->id;
        $specialtyId = $appointment?->specialty_id ?? $validated['specialty_id'];
        $branchId = $appointment?->clinic_branch_id
            ?? ((int) session('active_branch_customer_id') === (int) $customerId ? session('active_branch_id') : null);

        if (!$branchId || !ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->whereKey($branchId)->exists()) {
            return back()->withErrors(['encounter' => 'Selecciona una sede activa antes de iniciar la atención.'])->withInput();
        }

        $hasSpecialty = $branchId ? $doctor->specialtiesForBranch((int) $customerId, (int) $branchId)
            ->where('specialties.id', $specialtyId)
            ->where('specialties.status', 1)
            ->exists() : false;

        if (!$hasSpecialty) {
            return back()->withErrors(['specialty_id' => 'La especialidad seleccionada no corresponde al profesional.'])->withInput();
        }

        $encounter = DB::transaction(function () use ($patient, $customerId, $doctor, $appointment, $branchId, $professionalId, $specialtyId, $validated, $request) {
            // Serialize creation for this patient, including repeated submissions for the same appointment.
            Patient::where('customer_id', $customerId)->whereKey($patient->id)->lockForUpdate()->firstOrFail();
            if ($appointment) {
                $existing = $patient->encounters()->where('customer_id', $customerId)
                    ->where('appointment_id', $appointment->id)->first();
                if ($existing) return $existing;
            } elseif (!$request->boolean('confirm_open_encounter') && $patient->encounters()
                ->where('customer_id', $customerId)->where('status', 1)
                ->where(fn ($query) => $query->where('professional_id', $doctor->id)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('professional_id')->whereHas('appointment', fn ($query) => $query
                        ->withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)->where('professional_id', $doctor->id))))
                ->exists()) {
                throw ValidationException::withMessages(['confirm_open_encounter' => 'Ya tienes una atención abierta para este paciente. Confirma que deseas iniciar otra consulta sin cita.']);
            }
            $telemedicineSession = null;
            if ($validated['modality'] === 'telemedicine') {
                $query = TelemedicineSession::where('customer_id', $customerId);
                if ($appointment) {
                    if (\Illuminate\Support\Carbon::parse($appointment->end_at)->addHours(2)->isPast()) {
                        throw ValidationException::withMessages(['modality' => 'El horario de acceso a esta sala ha terminado.']);
                    }
                    $query->where('appointment_id', $appointment->id);
                } else {
                    $query->whereNull('appointment_id')->where('patient_id', $patient->id)
                        ->where('professional_id', $doctor->id)->where('clinic_branch_id', $branchId)
                        ->where('specialty_id', $specialtyId)->where('expires_at', '>', now());
                    if (!empty($validated['telemedicine_session_id'])) {
                        $query->whereKey($validated['telemedicine_session_id']);
                    } else {
                        $query->whereDoesntHave('encounter')->whereNull('ended_at')->whereIn('status', ['scheduled', 'in_progress']);
                    }
                }
                $telemedicineSession = $query->lockForUpdate()->first();
                if (!$telemedicineSession && !empty($validated['telemedicine_session_id'])) {
                    throw ValidationException::withMessages(['modality' => 'La sala seleccionada no est? disponible para esta atenci?n.']);
                }
                if ($telemedicineSession) {
                    if ($telemedicineSession->ended_at || !in_array($telemedicineSession->status, ['scheduled', 'in_progress'])) {
                        throw ValidationException::withMessages(['modality' => 'La sala de esta cita ya est? finalizada.']);
                    }
                    $existing = $patient->encounters()->where('customer_id', $customerId)->where('telemedicine_session_id', $telemedicineSession->id)->first();
                    if ($existing) return $existing;
                } else {
                    $telemedicineSession = TelemedicineSession::create([
                        'customer_id' => $customerId, 'appointment_id' => $appointment?->id,
                        'patient_id' => $patient->id, 'professional_id' => $doctor->id,
                        'clinic_branch_id' => $branchId, 'specialty_id' => $specialtyId,
                        'room_name' => 'vita-' . $customerId . '-' . \Illuminate\Support\Str::random(32),
                        'created_by' => $doctor->id, 'status' => 'scheduled',
                        'expires_at' => $appointment ? null : now()->addHours(4),
                    ]);
                }
            }
            $created = Encounter::create([
            'clinic_branch_id' => $branchId,
            'customer_id' => $customerId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'professional_id' => $professionalId,
            'specialty_id' => $specialtyId,
            'modality' => $validated['modality'],
            'telemedicine_session_id' => $telemedicineSession?->id,
            'opened_by' => $doctor->id,
            'created_by' => $doctor->id,
            'status' => 1,
            'chief_complaint' => trim($validated['chief_complaint']),
            'started_at' => now(),
            ]);
            $appointment?->update(['status' => 2]);
            return $created;
        });
        if (!$encounter->wasRecentlyCreated) return $this->existingEncounterRedirect($customer, $encounter);
        if ($encounter->telemedicine_session_id) {
            try {
                app(TelemedicineController::class)->inviteForEncounter($request, $encounter->telemedicine_session_id);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        if ($appointment) {
            app(ClinicAppointmentMessenger::class)->send($appointment, 'in_progress');
        }
        PatientAuditLogger::record($patient, 'started', 'Atención médica', [], [
            'profesional' => $doctor->name,
            'especialidad' => $appointment?->specialty?->name ?? ($branchId ? $doctor->specialtiesForBranch((int) $customerId, (int) $branchId)->find($specialtyId)?->name : null),
            'modalidad agendada' => $appointment?->modality === 'telemedicine' ? 'Remota (telemedicina)' : 'Presencial',
            'modalidad' => $validated['modality'] === 'telemedicine' ? 'Telemedicina' : 'Presencial',
            'motivo' => $encounter->chief_complaint,
            'estado' => 'En curso',
        ]);

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id, 'encounter_id' => $encounter->id])
            ->with('success', 'La atención se inició correctamente.');
    }

    private function existingEncounterRedirect($customer, Encounter $encounter)
    {
        return redirect()->route('patients.medical-record.show', array_filter([
            'customer' => $customer, 'id' => $encounter->patient_id,
            'encounter_id' => (int) $encounter->status === 1 ? $encounter->id : null,
        ], fn ($value) => $value !== null))->with('info', (int) $encounter->status === 1
            ? 'Esta cita ya tiene una atención abierta. Continúa la atención existente.'
            : 'Esta cita ya tiene una atención registrada; puedes consultarla en el historial.');
    }

    public function finish(Request $request, $customer, $id, $encounterId)
    {
        $encounter = $this->activeEncounter($id, $encounterId);
        $validated = $request->validate([
            'clinical_assessment' => ['required', 'string', 'max:20000'],
            'treatment_plan' => ['nullable', 'string', 'max:20000'],
        ], [
            'clinical_assessment.required' => 'Registra la evaluación clínica antes de finalizar.',
        ]);

        $before = [
            'evaluación clínica' => $encounter->clinical_assessment,
            'plan de tratamiento' => $encounter->treatment_plan,
            'estado' => 'En curso',
        ];
        $encounter->update([
            'clinical_assessment' => trim($validated['clinical_assessment']),
            'treatment_plan' => isset($validated['treatment_plan']) ? trim($validated['treatment_plan']) : null,
            'status' => 2,
            'ended_at' => now(),
        ]);
        $encounter->appointment?->update(['status' => 3]);
        PatientAuditLogger::record($encounter->patient, 'finished', 'Atención médica', $before, [
            'evaluación clínica' => $encounter->clinical_assessment,
            'plan de tratamiento' => $encounter->treatment_plan,
            'estado' => 'Finalizada',
        ]);

        if ($encounter->appointment) {
            app(ClinicAppointmentMessenger::class)->send($encounter->appointment, 'attended');
        }

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $encounter->patient_id])
            ->with('success', 'La atención quedó finalizada y guardada en el historial clínico.');
    }

    public function cancel(Request $request, $customer, $id, $encounterId)
    {
        $encounter = $this->activeEncounter($id, $encounterId);
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ], [
            'cancellation_reason.required' => 'Indica el motivo de la cancelación.',
        ]);

        $encounter->update([
            'cancellation_reason' => trim($validated['cancellation_reason']),
            'status' => 3,
            'ended_at' => now(),
        ]);
        $encounter->appointment?->update(['status' => 5]);
        PatientAuditLogger::record($encounter->patient, 'cancelled', 'Atención médica', [], [
            'motivo de cancelación' => $encounter->cancellation_reason,
            'estado' => 'Cancelada',
        ]);

        if ($encounter->appointment) {
            app(ClinicAppointmentMessenger::class)->send($encounter->appointment, 'cancelled');
        }

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $encounter->patient_id])
            ->with('success', 'La atención fue cancelada.');
    }

    public function addObservation(Request $request, $customer, $id, $encounterId)
    {
        $customerId = (int) app('customer')->id;
        $doctor = $this->authenticatedDoctor($customerId);
        $patient = Patient::query()->where('customer_id', $customerId)->findOrFail($id);
        $encounter = Encounter::query()
            ->where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->where('status', 2)
            ->with('appointment')
            ->findOrFail($encounterId);

        $responsibleProfessionalId = $encounter->professional_id ?? $encounter->appointment?->professional_id;
        abort_unless((int) $responsibleProfessionalId === (int) $doctor->id, 403, 'Solo el profesional responsable puede agregar observaciones a esta atención.');

        $validated = $request->validate([
            'observation' => ['required', 'string', 'max:10000'],
        ], [
            'observation.required' => 'Escribe la observación que deseas agregar.',
            'observation.max' => 'La observación no puede superar los 10.000 caracteres.',
        ]);

        $branchId = $encounter->clinic_branch_id ?? $encounter->appointment?->clinic_branch_id
            ?? \App\Support\ClinicBranchContext::defaultBranch($doctor, $customerId)?->id;
        $branchId = $branchId
            ? ClinicBranch::query()->where('customer_id', $customerId)->whereKey($branchId)->value('id')
            : null;

        $observation = trim($validated['observation']);
        EncounterObservation::create([
            'customer_id' => $customerId,
            'encounter_id' => $encounter->id,
            'user_id' => $doctor->id,
            'clinic_branch_id' => $branchId,
            'observation' => $observation,
        ]);

        PatientAuditLogger::record($patient, 'updated', 'Observación de atención', [], [
            'atención' => '#' . $encounter->id,
            'observación adicional' => $observation,
        ]);

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id])
            ->with('success', 'La observación se agregó al historial de la atención.');
    }

    private function activeEncounter($patientId, $encounterId): Encounter
    {
        $customerId = app('customer')->id;
        $doctor = $this->authenticatedDoctor($customerId);
        Patient::where('customer_id', $customerId)->findOrFail($patientId);

        $encounter = Encounter::where('customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->where('status', 1)
            ->where(function ($query) use ($doctor) {
                $query->where('professional_id', $doctor->id)
                    ->orWhere(function ($legacyQuery) use ($doctor) {
                        $legacyQuery->whereNull('professional_id')
                            ->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('professional_id', $doctor->id));
                    });
            })
            ->with('appointment')
            ->findOrFail($encounterId);

        if (!$encounter->professional_id) {
            $encounter->professional_id = $doctor->id;
            $encounter->specialty_id = $encounter->specialty_id ?? $encounter->appointment?->specialty_id;
            $encounter->save();
        }

        return $encounter;
    }

    private function authenticatedDoctor($customerId)
    {
        $user = auth()->user();
        abort_unless(
            $user
                && (int) $user->customer_id === (int) $customerId
                && (int) $user->status === 1
                && $user->roles()->where('roles.name', 'Doctor')->exists(),
            403,
            'Solo el médico autenticado puede gestionar sus atenciones.'
        );

        return $user;
    }
}
