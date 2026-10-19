<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Allergie;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
  

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
        $auditLogs = $patient->auditLogs()
            ->with('user:id,name')
            ->limit(100)
            ->get();

        $allergies = $medicalRecord->allergies;

        $chronicDiseases  = $medicalRecord->chronicDiseases ;

        $regularMedications  = $medicalRecord->regularMedications ;

        $previousSurgeries  = $medicalRecord->previousSurgeries ;

        $encountersQuery = $patient->encounters()->where('customer_id', $customerId);
        $historyQuery = (clone $encountersQuery)->whereIn('status', [2, 3]);

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
            ->with('professional', 'specialty', 'openedBy', 'createdBy', 'appointment.professional', 'appointment.specialty')
            ->orderByDesc('started_at')
            ->paginate(10)
            ->withQueryString();

        $professionalOptionsQuery = User::where('customer_id', $customerId)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'));
        if (request()->filled('specialty_id')) {
            $professionalOptionsQuery->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', request('specialty_id'))
                ->where('specialty_user.customer_id', $customerId));
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
                ->where('specialties.status', 1)
                ->orderBy('specialties.name')
                ->get(['specialties.id', 'specialties.name'])
            : collect();
        $openEncounterModal = false;
        $preselectedAppointmentId = null;
        $preselectedSpecialtyId = null;
        $appointmentToStart = null;
        $requestedAppointmentId = request()->query('start_encounter');

        if ($requestedAppointmentId !== null) {
            abort_unless($canCreateEncounter, 403, 'Solo un médico puede iniciar una atención.');

            $appointmentToStart = Appointment::where('customer_id', $customerId)
                ->where('patient_id', $patient->id)
                ->where('professional_id', $loggedUser->id)
                ->whereIn('status', [0, 1])
                ->with(['professional:id,name', 'specialty:id,name'])
                ->find($requestedAppointmentId);

            $appointmentAlreadyHasEncounter = $appointmentToStart
                && $patient->encounters()->where('customer_id', $customerId)
                    ->where('appointment_id', $appointmentToStart->id)->exists();
            $patientHasActiveEncounter = $patient->encounters()
                ->where('customer_id', $customerId)
                ->where('status', 1)
                ->exists();

            if (!$appointmentToStart || $appointmentAlreadyHasEncounter || $patientHasActiveEncounter) {
                $message = $patientHasActiveEncounter
                    ? 'El paciente ya tiene una atención en curso.'
                    : 'Esta cita ya no está disponible para iniciar una atención.';

                return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id])
                    ->with('error', $message);
            }

            $openEncounterModal = true;
            $preselectedAppointmentId = $appointmentToStart->id;
            $preselectedSpecialtyId = $appointmentToStart->specialty_id;
        }

        $appointmentsQuery = Appointment::where('customer_id', $customerId)
            ->where('patient_id', $patient->id)
            ->whereIn('status', [0, 1])
            ->with(['professional:id,name', 'specialty:id,name'])
            ->orderByDesc('start_at')
            ->limit(50);
        $patientAppointments = $canCreateEncounter
            ? $appointmentsQuery->where('professional_id', $loggedUser->id)->get(['id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'status'])
            : collect();
        if ($appointmentToStart && !$patientAppointments->contains('id', $appointmentToStart->id)) {
            $patientAppointments->prepend($appointmentToStart);
        }

        //status 1 abierta, 2 terminada , 3 cancelada
        $currentEncounter = (clone $encountersQuery)
            ->where('status', 1)
            ->with('professional', 'specialty', 'openedBy', 'createdBy', 'appointment.professional', 'appointment.specialty')
            ->latest()
            ->first();
        $canManageCurrentEncounter = $canCreateEncounter
            && $currentEncounter
            && (int) ($currentEncounter->professional_id ?? $currentEncounter->appointment?->professional_id) === (int) $loggedUser->id;

        return view('customer.patient.medical-record', compact(
            'currentEncounter',
            'encounters',
            'patient',
            'medicalRecord',
            'auditLogs',
            'allergies',
            'chronicDiseases',
            'regularMedications',
            'previousSurgeries',
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
            'professionalOptions',
            'specialtyOptions'
        ));
    }

  
}
