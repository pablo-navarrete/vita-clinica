<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Patient;
use App\Support\PatientAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EncounterController extends Controller
{
    public function store(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $doctor = $this->authenticatedDoctor($customerId);
        $patient = Patient::where('customer_id', $customerId)->findOrFail($id);

        if ($patient->encounters()->where('customer_id', $customerId)->where('status', 1)->exists()) {
            return back()->withErrors(['encounter' => 'Este paciente ya tiene una atención en curso.'])->withInput();
        }

        $validated = $request->validate([
            'chief_complaint' => ['required', 'string', 'max:5000'],
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
        if ($appointment && Encounter::where('customer_id', $customerId)->where('appointment_id', $appointment->id)->exists()) {
            return back()->withErrors(['appointment_id' => 'Esta cita ya tiene una atención asociada.'])->withInput();
        }
        $professionalId = $doctor->id;
        $specialtyId = $appointment?->specialty_id ?? $validated['specialty_id'];

        $hasSpecialty = $doctor->specialties()
            ->where('specialties.id', $specialtyId)
            ->where('specialties.status', 1)
            ->wherePivot('customer_id', $customerId)
            ->exists();

        if (!$hasSpecialty) {
            return back()->withErrors(['specialty_id' => 'La especialidad seleccionada no corresponde al profesional.'])->withInput();
        }

        $encounter = Encounter::create([
            'customer_id' => $customerId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'professional_id' => $professionalId,
            'specialty_id' => $specialtyId,
            'opened_by' => $doctor->id,
            'created_by' => $doctor->id,
            'status' => 1,
            'chief_complaint' => trim($validated['chief_complaint']),
            'started_at' => now(),
        ]);
        $appointment?->update(['status' => 2]);
        PatientAuditLogger::record($patient, 'started', 'Atención médica', [], [
            'profesional' => $doctor->name,
            'especialidad' => $appointment?->specialty?->name ?? $doctor->specialties()->find($specialtyId)?->name,
            'motivo' => $encounter->chief_complaint,
            'estado' => 'En curso',
        ]);

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id])
            ->with('success', 'La atención se inició correctamente.');
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

        return redirect()->route('patients.medical-record.show', ['customer' => $customer, 'id' => $encounter->patient_id])
            ->with('success', 'La atención fue cancelada.');
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
