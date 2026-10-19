<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Support\PatientAuditLogger;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index()
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionals = User::with('specialties')->where('customer_id', $customerId)
            ->where('status', 1)
            ->when($doctor, fn ($query) => $query->whereKey($doctor->id))
            ->orderBy('name')
            ->get();

        $patients = Patient::where('customer_id', $customerId)
            ->orderBy('name')
            ->get();

        $specialties = Specialty::where('customer_id', $customerId)
            ->where('status', 1)
            ->when($doctor, fn ($query) => $query->whereHas('users', fn ($users) => $users
                ->where('users.id', $doctor->id)
                ->where('specialty_user.customer_id', $customerId)))
            ->orderBy('name')
            ->get();
        $isDoctor = $this->hasDoctorRole(auth()->user());
        $currentProfessionalId = $doctor?->id;
        $currentProfessionalName = $doctor?->name;

        return view('customer.Appointment.index', compact('professionals', 'patients', 'specialties', 'isDoctor', 'currentProfessionalId', 'currentProfessionalName'));
    }

    public function professionalsBySpecialty(Request $request)
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $request->validate(['specialty_id' => ['required', 'integer']]);
        $specialtyExists = Specialty::where('customer_id', $customerId)
            ->where('status', 1)
            ->whereKey($request->specialty_id)
            ->exists();
        if (!$specialtyExists) {
            return response()->json([]);
        }

        $professionals = User::where('customer_id', $customerId)
            ->where('status', 1)
            ->when($doctor, fn ($query) => $query->whereKey($doctor->id))
            ->whereHas('specialties', function ($q) use ($request, $customerId) {

                $q->where('specialties.id', $request->specialty_id)
                    ->where('specialty_user.customer_id', $customerId)
                    ->where('specialties.customer_id', $customerId);
            })
            ->orderBy('name')
            ->get();

        return response()->json($professionals);
    }

    public function events(Request $request)
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionalId = $doctor?->id ?? $request->professional_id;
        if (!$professionalId) {
            return response()->json([]);
        }

        $appointments = Appointment::where('customer_id', $customerId)
            ->where('professional_id', $professionalId)

            ->where(function ($query) use ($request) {

                $query->whereBetween('start_at', [
                    $request->start,
                    $request->end
                ])
                    ->orWhereBetween('end_at', [
                        $request->start,
                        $request->end
                    ]);
            })

            ->whereHas('patient', function ($query) use ($request) {

                if ($request->filled('search')) {

                    $search = $request->search;

                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%");
                }
            })

            ->with(['patient', 'specialty'])

            ->get();

        $appointmentIds = $appointments->pluck('id');
        $patientIds = $appointments->pluck('patient_id')->unique();
        $encountersByAppointment = Encounter::where('customer_id', $customerId)
            ->whereIn('appointment_id', $appointmentIds)
            ->get(['id', 'appointment_id'])
            ->keyBy('appointment_id');
        $appointmentsWithAttention = $encountersByAppointment->keys()->all();
        $patientsWithActiveAttention = Encounter::where('customer_id', $customerId)
            ->where('status', 1)
            ->whereIn('patient_id', $patientIds)
            ->pluck('patient_id')
            ->all();
        $isDoctor = $this->hasDoctorRole(auth()->user());


        $events = $appointments->map(function ($appointment) use ($encountersByAppointment, $appointmentsWithAttention, $patientsWithActiveAttention, $isDoctor) {

            $color = match ($appointment->status) {
                0 => '#3B82F6', // agendado
                1 => '#10B981', // Confirmada
                2 => '#F59E0B', // En atención
                3 => '#16A34A', // Atendida
                4 => '#EF4444', // No asistió
                5 => '#6B7280', // Cancelada
                default => '#3B82F6',
            };

            return [

                'id' => $appointment->id,
                'title' => $appointment->patient->name,
                'start' => $appointment->start_at,
                'end' => $appointment->end_at,

                'extendedProps' => [
                    'appointmentId' => $appointment->id,
                    'encounterId' => $encountersByAppointment->get($appointment->id)?->id,
                    'patientId' => $appointment->patient_id,
                    'professionalId' => $appointment->professional_id,
                    'specialtyId' => $appointment->specialty_id,
                    'rut' => $appointment->patient->rut,
                    'reason' => $appointment->reason,
                    'notes' => $appointment->notes,
                    'status' => $appointment->status,
                    'specialty' => $appointment->specialty?->name,
                    'isDoctor' => $isDoctor,
                    'canStartAttention' => $isDoctor
                        && (int) $appointment->status === 0
                        && !in_array($appointment->id, $appointmentsWithAttention)
                        && !in_array($appointment->patient_id, $patientsWithActiveAttention),
                    'canUpdateAppointment' => in_array((int) $appointment->status, [0, 1], true)
                        && !in_array($appointment->id, $appointmentsWithAttention),
                    'patientHasActiveAttention' => in_array($appointment->patient_id, $patientsWithActiveAttention),
                    'attentionBlockReason' => !$isDoctor
                        ? 'Solo un profesional con rol Doctor puede iniciar una atención.'
                        : ((int) $appointment->status !== 0
                            ? 'Solo se puede iniciar una atención desde una cita agendada.'
                            : (in_array($appointment->id, $appointmentsWithAttention)
                                ? 'Esta cita ya tiene una atención asociada.'
                                : (in_array($appointment->patient_id, $patientsWithActiveAttention)
                                    ? 'El paciente ya tiene una atención en curso.'
                                    : null))),

                ],

                'backgroundColor' => $color,

                '       borderColor' => $color,

            ];
        });


        return response()->json($events)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function availability(Request $request)
    {
        $request->validate([
            'professional_id' => 'nullable|integer',
            'date' => 'required|date',
            'appointment_id' => 'nullable|integer',
        ]);

        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionalId = $doctor?->id ?? $request->professional_id;
        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            abort(404);
        }

        $timezone = config('app.timezone', 'America/Santiago');
        $date = Carbon::parse($request->date, $timezone)->startOfDay();
        $now = now($timezone);


        $dayOfWeek = $date->dayOfWeek;


        // TODOS los horarios del profesional ese día
        $schedules = ProfessionalSchedule::where('user_id', $professionalId)
            ->where('customer_id', $customerId)
            ->where('day_of_week', $dayOfWeek)
            ->get();


        if ($schedules->isEmpty()) {

            return response()->json([]);
        }



        // citas existentes ese día

        $appointments = Appointment::where('professional_id', $professionalId)
            ->where('customer_id', $customerId)
            ->when($request->filled('appointment_id'), fn ($query) => $query->where('id', '!=', $request->appointment_id))
            ->whereDate('start_at', $date->format('Y-m-d'))
            ->whereIn('status', [0, 1, 2])
            ->get();



        $available = [];



        foreach ($schedules as $schedule) {


            // construir inicio del bloque

            $start = Carbon::parse(
                $date->format('Y-m-d') . ' ' .
                    Carbon::parse($schedule->start_time)->format('H:i:s'),
                $timezone
            );


            $end = Carbon::parse(
                $date->format('Y-m-d') . ' ' .
                    Carbon::parse($schedule->end_time)->format('H:i:s'),
                $timezone
            );



            while ($start->copy()->addMinutes(30) <= $end) {


                $slotStart = $start->copy();

                $slotEnd = $start->copy()->addMinutes(30);



                // revisar si está ocupado

                $occupied = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) {

                    return
                        $slotStart < Carbon::parse($appointment->end_at)
                        &&
                        $slotEnd > Carbon::parse($appointment->start_at);
                });



                // La API solo ofrece horas futuras según la zona horaria de la clínica.
                if (!$occupied && $slotStart->gt($now)) {


                    $available[] = [

                        'value' => $slotStart->format('H:i'),

                        'label' =>
                        $slotStart->format('H:i')
                            . ' - ' .
                            $slotEnd->format('H:i')

                    ];
                }



                $start->addMinutes(30);
            }
        }



        $available = collect($available)
            ->unique('value')
            ->sortBy('value')
            ->values();

        return response()->json($available);
    }

    public function store(Request $request)
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionalId = $doctor?->id ?? $request->input('professional_id');

        $request->validate([

            'professional_id' => 'nullable|integer',

            'patient_id' => [
                'required',
                Rule::exists('patients', 'id')->where('customer_id', $customerId),
            ],

            'specialty_id' => [
                'required',
                Rule::exists('specialties', 'id')->where('customer_id', $customerId),
            ],

            'date' => 'required|date|after_or_equal:today',

            'time' => 'required',

            'reason' => 'nullable|string',

            'notes' => 'nullable|string',

        ]);

        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            return response()->json(['success' => false, 'message' => 'El profesional seleccionado no pertenece a esta clínica.'], 422);
        }

        $hasSpecialty = User::whereKey($professionalId)
            ->where('customer_id', $customerId)
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $request->specialty_id)
                ->where('specialty_user.customer_id', $customerId))
            ->exists();
        if (!$hasSpecialty) {
            return response()->json(['success' => false, 'message' => 'La especialidad no corresponde al profesional seleccionado.'], 422);
        }



        /*
        |--------------------------------------------------------------------------
        | Crear fecha inicio y termino
        |--------------------------------------------------------------------------
        */

        $startAt = Carbon::parse(
            $request->date . ' ' . $request->time,
            config('app.timezone', 'America/Santiago')
        );

        if (!$startAt->gt(now(config('app.timezone', 'America/Santiago')))) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona una hora futura disponible según la hora de Chile.',
            ], 422);
        }


        // citas de 30 minutos

        $endAt = $startAt->copy()->addMinutes(30);



        /*
        |--------------------------------------------------------------------------
        | Validar horario del profesional
        |--------------------------------------------------------------------------
        */

        $dayOfWeek = $startAt->dayOfWeek;


        $available = ProfessionalSchedule::where('customer_id', $customerId)

            ->where('user_id', $professionalId)

            ->where('day_of_week', $dayOfWeek)

            ->whereTime('start_time', '<=', $startAt->format('H:i:s'))

            ->whereTime('end_time', '>=', $endAt->format('H:i:s'))

            ->exists();



        if (!$available) {

            return response()->json([

                'success' => false,

                'message' => 'El profesional no tiene disponibilidad en ese horario.'

            ], 422);
        }



        /*
        |--------------------------------------------------------------------------
        | Evitar doble reserva
        |--------------------------------------------------------------------------
        */

        $exists = Appointment::where('customer_id', $customerId)
            ->where('professional_id', $professionalId)
            ->whereNotIn('status', [4, 5]) // ajusta según tus estados
            ->where(function ($query) use ($startAt, $endAt) {

                $query->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();



        if ($exists) {


            return response()->json([

                'success' => false,

                'message' => 'El horario seleccionado ya está ocupado.'

            ], 422);
        }



        /*
        |--------------------------------------------------------------------------
        | Guardar cita
        |--------------------------------------------------------------------------
        */

        $appointment = Appointment::create([

            'customer_id' => $customerId,

            'patient_id' => $request->patient_id,

            'specialty_id' => $request->specialty_id,

            'professional_id' => $professionalId,

            'start_at' => $startAt,

            'end_at' => $endAt,

            'status' => 0, // creada

            'reason' => $request->reason,

            'notes' => $request->notes,

        ]);

        $patient = Patient::where('customer_id', $customerId)->findOrFail($request->patient_id);
        PatientAuditLogger::record($patient, 'created', 'Cita médica', [], [
            'profesional' => User::find($professionalId)?->name,
            'fecha de inicio' => $startAt->format('Y-m-d H:i:s'),
            'fecha de término' => $endAt->format('Y-m-d H:i:s'),
            'motivo' => $appointment->reason,
            'observaciones' => $appointment->notes,
            'estado' => 'Agendada',
        ]);

        $this->notifyProfessionalAppointment(
            (int) $professionalId,
            $appointment,
            'created',
            'Nueva cita agendada',
            'Se agendó una nueva cita en tu agenda.'
        );



        return response()->json([

            'success' => true,

            'message' => 'Cita creada correctamente.'

        ]);
    }

    public function update(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $appointment = Appointment::where('customer_id', $customerId)
            ->when($doctor, fn ($query) => $query->where('professional_id', $doctor->id))
            ->findOrFail($id);

        $hasAssociatedEncounter = Encounter::where('customer_id', $customerId)
            ->where('appointment_id', $appointment->id)
            ->exists();
        if ($hasAssociatedEncounter) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar una cita que ya tiene una atención asociada.',
            ], 422);
        }

        if (!in_array((int) $appointment->status, [0, 1], true)) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden actualizar citas agendadas o confirmadas.'], 422);
        }

        $professionalId = $doctor?->id ?? $request->input('professional_id');
        $request->validate([
            'professional_id' => 'nullable|integer',
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('customer_id', $customerId)],
            'specialty_id' => ['required', Rule::exists('specialties', 'id')->where('customer_id', $customerId)],
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            return response()->json(['success' => false, 'message' => 'El profesional seleccionado no pertenece a esta clínica.'], 422);
        }

        $hasSpecialty = User::whereKey($professionalId)
            ->where('customer_id', $customerId)
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $request->specialty_id)
                ->where('specialty_user.customer_id', $customerId))
            ->exists();
        if (!$hasSpecialty) {
            return response()->json(['success' => false, 'message' => 'La especialidad no corresponde al profesional seleccionado.'], 422);
        }

        $startAt = Carbon::parse(
            $request->date . ' ' . $request->time,
            config('app.timezone', 'America/Santiago')
        );
        $endAt = $startAt->copy()->addMinutes(30);
        $available = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('user_id', $professionalId)
            ->where('day_of_week', $startAt->dayOfWeek)
            ->whereTime('start_time', '<=', $startAt->format('H:i:s'))
            ->whereTime('end_time', '>=', $endAt->format('H:i:s'))
            ->exists();

        $keepingExistingTime = Carbon::parse($appointment->start_at)->equalTo($startAt)
            && Carbon::parse($appointment->end_at)->equalTo($endAt);

        $keepingExistingSlot = $keepingExistingTime
            && (int) $appointment->professional_id === (int) $professionalId;

        if (!$keepingExistingSlot && !$startAt->gt(now(config('app.timezone', 'America/Santiago')))) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona una hora futura disponible según la hora de Chile.',
            ], 422);
        }

        if (!$available && !$keepingExistingTime) {
            return response()->json(['success' => false, 'message' => 'El profesional no tiene disponibilidad en ese horario.'], 422);
        }

        $occupied = Appointment::where('customer_id', $customerId)
            ->where('professional_id', $professionalId)
            ->whereKeyNot($appointment->id)
            ->whereNotIn('status', [4, 5])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();

        if ($occupied) {
            return response()->json(['success' => false, 'message' => 'El horario seleccionado ya está ocupado.'], 422);
        }

        $previousPatient = Patient::where('customer_id', $customerId)->findOrFail($appointment->patient_id);
        $previousProfessionalId = (int) $appointment->professional_id;
        $previousAppointmentDetails = [
            'patient_name' => $previousPatient->name,
            'patient_rut' => $previousPatient->rut,
            'starts_at' => Carbon::parse($appointment->start_at)->format('Y-m-d H:i:s'),
            'specialty' => $appointment->specialty?->name,
            'reason' => $appointment->reason,
        ];
        $before = array_intersect_key($appointment->getRawOriginal(), array_flip([
            'patient_id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'reason', 'notes',
        ]));
        $appointment->update([
            'patient_id' => $request->patient_id,
            'specialty_id' => $request->specialty_id,
            'professional_id' => $professionalId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);

        $appointment->refresh();
        $after = array_intersect_key($appointment->getRawOriginal(), array_flip([
            'patient_id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'reason', 'notes',
        ]));
        $newPatient = Patient::where('customer_id', $customerId)->findOrFail($appointment->patient_id);
        PatientAuditLogger::record($newPatient, 'updated', 'Cita médica', $before, $after);
        if ((int) $previousPatient->id !== (int) $newPatient->id) {
            PatientAuditLogger::record($previousPatient, 'updated', 'Cita médica', [], [
                'cita' => 'Reasignada al paciente ' . $newPatient->name,
            ]);
        }

        $sameProfessional = $previousProfessionalId === (int) $appointment->professional_id;
        $this->notifyProfessionalAppointment(
            (int) $appointment->professional_id,
            $appointment,
            $sameProfessional ? 'updated' : 'assigned',
            $sameProfessional ? 'Cita actualizada' : 'Nueva cita asignada',
            $sameProfessional
                ? 'Se actualizaron los datos de una cita de tu agenda.'
                : 'Se asignó una cita a tu agenda.'
        );

        if (!$sameProfessional) {
            $this->notifyProfessionalAppointment(
                $previousProfessionalId,
                $appointment,
                'reassigned',
                'Cita reasignada',
                'Una cita dejó de estar asignada a tu agenda.',
                $previousAppointmentDetails
            );
        }

        return response()->json(['success' => true, 'message' => 'Cita actualizada correctamente.']);
    }

    //cancelar cita
    public function cancel(Request $request)
    {
        $id = $request->route('id');
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);

        $appointment = Appointment::where('customer_id', $customerId)
            ->when($doctor, fn ($query) => $query->where('professional_id', $doctor->id))
            ->where('id', $id)
            ->firstOrFail();

        $before = ['status' => $appointment->status];
        $appointment->status = 5;
        $appointment->save();
        PatientAuditLogger::record($appointment->patient, 'updated', 'Cita médica', $before, ['status' => 5]);

        $this->notifyProfessionalAppointment(
            (int) $appointment->professional_id,
            $appointment,
            'cancelled',
            'Cita cancelada',
            'Se canceló una cita de tu agenda.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Cita cancelada correctamente.'
        ]);
    }

    private function hasDoctorRole($user): bool
    {
        return $user && $user->roles()->where('roles.name', 'Doctor')->exists();
    }

    private function notifyProfessionalAppointment(
        int $professionalId,
        Appointment $appointment,
        string $event,
        string $title,
        string $message,
        array $overrides = []
    ): void {
        $customerId = (int) app('customer')->id;
        $professional = User::query()
            ->where('customer_id', $customerId)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'))
            ->find($professionalId);

        if (!$professional) {
            return;
        }

        $appointment->loadMissing(['patient', 'specialty']);
        $details = array_merge([
            'customer_id' => $customerId,
            'appointment_id' => $appointment->id,
            'event' => $event,
            'title' => $title,
            'message' => $message,
            'patient_name' => $appointment->patient?->name,
            'patient_rut' => $appointment->patient?->rut,
            'specialty' => $appointment->specialty?->name,
            'starts_at' => Carbon::parse($appointment->start_at)->format('Y-m-d H:i:s'),
            'ends_at' => Carbon::parse($appointment->end_at)->format('Y-m-d H:i:s'),
            'reason' => $appointment->reason,
        ], $overrides);

        $professional->notify(new AppointmentNotification($details));
    }

    private function loggedInDoctor($customerId): ?User
    {
        $user = auth()->user();
        if (!$this->hasDoctorRole($user)) {
            return null;
        }

        abort_unless(
            (int) $user->customer_id === (int) $customerId && (int) $user->status === 1,
            403,
            'La cuenta profesional no está habilitada para esta clínica.'
        );

        return $user;
    }

    private function professionalBelongsToCustomer($professionalId, $customerId): bool
    {
        return User::where('customer_id', $customerId)
            ->where('status', 1)
            ->whereKey($professionalId)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'))
            ->exists();
    }
}
