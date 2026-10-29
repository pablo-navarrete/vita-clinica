<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserAbsence;
use App\Support\PatientAuditLogger;
use App\Support\ClinicBranchContext;
use App\Notifications\AppointmentNotification;
use App\Support\ClinicAppointmentMessenger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index()
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $branchId = $this->activeBranchId((int) $customerId);
        $canCreateAppointments = $branchId && ClinicBranchContext::hasPermissionInBranch(auth()->user(), (int) $customerId, (int) $branchId, 'appointments.create');
        $appointmentBranches = $branchId ? ClinicBranchContext::availableBranches(auth()->user(), (int) $customerId) : collect();
        $canChooseAppointmentBranch = $branchId
            && $appointmentBranches->count() > 1
            && ClinicBranchContext::isHeadquartersActive((int) $customerId)
            && ClinicBranchContext::canOperateAcrossBranches(auth()->user(), (int) $customerId)
            && ($canCreateAppointments || ClinicBranchContext::hasPermissionInBranch(auth()->user(), (int) $customerId, (int) $branchId, 'appointments.update'));
        $canFilterAppointmentsAcrossBranches = $branchId
            && ClinicBranchContext::canAdministerAllBranches(auth()->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch(auth()->user(), (int) $customerId, (int) $branchId, 'appointments.view');
        $canViewClinicWideAppointments = (bool) $canFilterAppointmentsAcrossBranches;
        $calendarBranches = $canFilterAppointmentsAcrossBranches
            ? ClinicBranchContext::centralAdministrationBranches(auth()->user(), (int) $customerId)
            : collect();
        $professionals = User::with(['specialties' => fn ($query) => $query
                ->where('specialties.customer_id', $customerId)
                ->wherePivot('customer_id', $customerId)
                ->wherePivot('clinic_branch_id', $branchId ?? 0)])->where('customer_id', $customerId)
            ->where('status', 1)
            ->when($doctor && !$canViewClinicWideAppointments, fn ($query) => $query->whereKey($doctor->id))
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true)), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();

        $calendarWorkingHours = $branchId
            ? DB::table('professional_schedules')
                ->where('customer_id', $customerId)
                ->where('clinic_branch_id', $branchId)
                ->whereIn('user_id', $professionals->pluck('id'))
                ->select('user_id')
                ->selectRaw('MIN(start_time) AS start_time, MAX(end_time) AS end_time')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id')
            : collect();

        $patients = Patient::where('customer_id', $customerId)
            ->orderBy('name')
            ->get();

        $specialties = Specialty::query()
            ->where('customer_id', $customerId)
            ->where('status', 1)
            ->whereHas('users', fn ($users) => $users
                ->where('users.customer_id', $customerId)
                ->where('users.status', 1)
                ->when($doctor && !$canViewClinicWideAppointments, fn ($query) => $query->whereKey($doctor->id))
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $branchId ?? 0)
                ->whereHas('branches', fn ($branches) => $branches
                    ->where('clinic_branches.id', $branchId ?? 0)
                    ->where('clinic_branches.status', true)
                    ->where('clinic_branch_user.status', true)))
            ->orderBy('name')
            ->get();
        $isDoctor = $doctor !== null && !$canViewClinicWideAppointments;
        $currentProfessionalId = $isDoctor ? $doctor?->id : null;
        $currentProfessionalName = $doctor?->name;
        $telemedicineConfigured = filled(config('services.livekit.url'))
            && filled(config('services.livekit.api_key'))
            && filled(config('services.livekit.api_secret'));

        return view('customer.Appointment.index', compact('professionals', 'patients', 'specialties', 'isDoctor', 'currentProfessionalId', 'currentProfessionalName', 'telemedicineConfigured', 'calendarWorkingHours', 'appointmentBranches', 'canChooseAppointmentBranch', 'branchId', 'calendarBranches', 'canFilterAppointmentsAcrossBranches', 'canCreateAppointments'));
    }

    public function calendarProfessionals(Request $request)
    {
        $customerId = (int) app('customer')->id;
        $branchId = $this->appointmentCalendarBranchId($request, $customerId);
        $professionals = User::query()->where('customer_id', $customerId)->where('status', 1)
            ->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)
                ->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))
            ->whereHas('branchRoles', fn ($roles) => $roles->where('roles.customer_id', $customerId)
                ->where('clinic_branch_user_role.clinic_branch_id', $branchId)->whereRaw('LOWER(TRIM(roles.name)) = ?', ['doctor']))
            ->orderBy('name')->get(['id', 'name']);

        $workingHours = DB::table('professional_schedules')->where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)->whereIn('user_id', $professionals->pluck('id'))
            ->select('user_id')->selectRaw('MIN(start_time) AS start_time, MAX(end_time) AS end_time')
            ->groupBy('user_id')->get()->keyBy('user_id');

        return response()->json(['professionals' => $professionals, 'working_hours' => $workingHours]);
    }

    public function specialtiesForBranch(Request $request)
    {
        $customerId = (int) app('customer')->id;
        $permission = $request->filled('appointment_id') ? 'appointments.update' : 'appointments.create';
        $branchId = $this->appointmentBranchId($request, $customerId, $permission);
        abort_unless($branchId, 422, 'Selecciona una sede activa.');
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), $customerId, (int) $this->activeBranchId($customerId), $permission)) {
            $doctor = null;
        }

        $specialties = Specialty::query()
            ->where('customer_id', $customerId)
            ->where('status', 1)
            ->whereHas('users', fn ($users) => $users
                ->where('users.customer_id', $customerId)
                ->where('users.status', 1)
                ->when($doctor, fn ($query) => $query->whereKey($doctor->id))
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $branchId)
                ->whereHas('branches', fn ($branches) => $branches
                    ->where('clinic_branches.id', $branchId)
                    ->where('clinic_branches.status', true)
                    ->where('clinic_branch_user.status', true)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($specialties);
    }

    public function professionalsBySpecialty(Request $request)
    {
        $customerId = app('customer')->id;
        $permission = $request->filled('appointment_id') ? 'appointments.update' : 'appointments.create';
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), $permission)) {
            $doctor = null;
        }
        $branchId = $this->appointmentBranchId($request, (int) $customerId, $permission);
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
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true)), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereHas('specialties', function ($q) use ($request, $customerId, $branchId) {

                $q->where('specialties.id', $request->specialty_id)
                    ->where('specialty_user.customer_id', $customerId)
                    ->where('specialty_user.clinic_branch_id', $branchId ?? 0)
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
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), 'appointments.view')) {
            $doctor = null;
        }
        $branchId = $this->appointmentCalendarBranchId($request, (int) $customerId);
        $professionalId = $doctor?->id ?? $request->professional_id;
        if (!$professionalId) {
            return response()->json([]);
        }
        if (!$branchId || !User::query()->where('customer_id', $customerId)->whereKey($professionalId)
            ->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))
            ->exists()) {
            return response()->json([]);
        }

        $appointments = Appointment::where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)
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

            ->with(['patient', 'specialty', 'clinicBranch'])

            ->get();

        $appointmentIds = $appointments->pluck('id');
        $patientIds = $appointments->pluck('patient_id')->unique();
        $encountersByAppointment = Encounter::where('customer_id', $customerId)
            ->whereIn('appointment_id', $appointmentIds)
            ->get(['id', 'appointment_id', 'status'])
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
                    'encounterStatus' => $encountersByAppointment->get($appointment->id)?->status,
                    'patientId' => $appointment->patient_id,
                    'professionalId' => $appointment->professional_id,
                    'specialtyId' => $appointment->specialty_id,
                    'rut' => $appointment->patient->rut,
                    'reason' => $appointment->reason,
                    'notes' => $appointment->notes,
                    'modality' => $appointment->modality ?? 'in_person',
                    'branchName' => $appointment->clinicBranch?->name,
                    'branchId' => (int) $appointment->clinic_branch_id,
                    'status' => $appointment->status,
                    'specialty' => $appointment->specialty?->name,
                    'isDoctor' => $isDoctor,
                    'canStartAttention' => $isDoctor
                        && in_array((int) $appointment->status, [0, 1], true)
                        && (int) $appointment->professional_id === (int) auth()->id()
                        && !in_array($appointment->id, $appointmentsWithAttention),
                    'canUpdateAppointment' => in_array((int) $appointment->status, [0, 1], true)
                        && !in_array($appointment->id, $appointmentsWithAttention),
                    'patientHasActiveAttention' => in_array($appointment->patient_id, $patientsWithActiveAttention),
                    'attentionBlockReason' => !$isDoctor
                        ? 'Solo un profesional con rol Doctor puede iniciar una atención.'
                        : (!in_array((int) $appointment->status, [0, 1], true)
                            ? 'Solo se puede iniciar una atención desde una cita agendada o confirmada.'
                            : (in_array($appointment->id, $appointmentsWithAttention)
                                ? 'Esta cita ya tiene una atención asociada.'
                                : null)),

                ],

                'backgroundColor' => $color,

                '       borderColor' => $color,

            ];
        });

        if (in_array($request->input('view_type'), ['timeGridWeek', 'timeGridDay'], true)
            && $this->professionalBelongsToCustomer($professionalId, $customerId)
            && $request->filled('start') && $request->filled('end')) {
            $timezone = config('app.timezone', 'America/Santiago');
            $rangeStart = Carbon::parse($request->input('start'), $timezone)->startOfDay();
            $rangeEnd = Carbon::parse($request->input('end'), $timezone)->startOfDay();
            $now = now($timezone);
            $schedules = ProfessionalSchedule::where('customer_id', $customerId)
                ->where('user_id', $professionalId)
                ->where('clinic_branch_id', $branchId)
                ->get()
                ->groupBy('day_of_week');
            $busyAppointments = Appointment::withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)
                ->where('professional_id', $professionalId)
                ->whereIn('status', [0, 1, 2])
                ->where('start_at', '<', $rangeEnd)
                ->where('end_at', '>', $rangeStart)
                ->get(['start_at', 'end_at', 'clinic_branch_id']);
            $absenceDates = UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
                ->whereDate('starts_on', '<=', $rangeEnd->toDateString())
                ->whereDate('ends_on', '>=', $rangeStart->toDateString())->get(['starts_on', 'ends_on']);

            $day = $rangeStart->copy();
            while ($day->lt($rangeEnd)) {
                if ($absenceDates->contains(fn ($absence) => $day->toDateString() >= $absence->starts_on->toDateString() && $day->toDateString() <= $absence->ends_on->toDateString())) {
                    $day->addDay();
                    continue;
                }
                foreach ($schedules->get($day->dayOfWeek, collect()) as $schedule) {
                    $slotStart = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->start_time)->format('H:i:s'), $timezone);
                    $scheduleEnd = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->end_time)->format('H:i:s'), $timezone);

                    while ($slotStart->copy()->addMinutes(30)->lte($scheduleEnd)) {
                        $slotEnd = $slotStart->copy()->addMinutes(30);
                        $overlappingAppointments = $busyAppointments->filter(function ($appointment) use ($slotStart, $slotEnd, $timezone) {
                            return $slotStart->lt(Carbon::parse($appointment->end_at, $timezone))
                                && $slotEnd->gt(Carbon::parse($appointment->start_at, $timezone));
                        });

                        if ($slotStart->gt($now) && $overlappingAppointments->isEmpty()) {
                            $events->push([
                                'id' => 'available-' . $slotStart->format('Ymd-Hi'),
                                'title' => 'Disponible',
                                'start' => $slotStart->toIso8601String(),
                                'end' => $slotEnd->toIso8601String(),
                                'display' => 'background',
                                'backgroundColor' => '#bbf7d0',
                                'extendedProps' => ['isAvailableSlot' => true],
                            ]);
                        } elseif ($slotStart->gt($now) && $overlappingAppointments->contains(
                            fn ($appointment) => (int) $appointment->clinic_branch_id !== (int) $branchId
                        )) {
                            // Keep cross-branch collision protection visible without exposing appointment details.
                            $events->push([
                                'id' => 'branch-conflict-' . $slotStart->format('Ymd-Hi'),
                                'title' => 'Ocupado en otra sede',
                                'start' => $slotStart->toIso8601String(),
                                'end' => $slotEnd->toIso8601String(),
                                'display' => 'background',
                                'backgroundColor' => '#fecaca',
                                'extendedProps' => ['isBranchConflict' => true],
                            ]);
                        }

                        $slotStart->addMinutes(30);
                    }
                }

                $day->addDay();
            }
        }


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
        $permission = $request->filled('appointment_id') ? 'appointments.update' : 'appointments.create';
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), $permission)) {
            $doctor = null;
        }
        $professionalId = $doctor?->id ?? $request->professional_id;
        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            abort(404);
        }
        $branchId = $this->appointmentBranchId($request, (int) $customerId, $permission);
        if (!$branchId || !User::query()->whereKey($professionalId)->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))->exists()) {
            abort(404);
        }
        if (UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
            ->whereDate('starts_on', '<=', $request->date)->whereDate('ends_on', '>=', $request->date)->exists()) {
            return response()->json([]);
        }

        $timezone = config('app.timezone', 'America/Santiago');
        $date = Carbon::parse($request->date, $timezone)->startOfDay();
        $now = now($timezone);


        $dayOfWeek = $date->dayOfWeek;


        // TODOS los horarios del profesional ese día
        $schedules = ProfessionalSchedule::where('user_id', $professionalId)
            ->where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->get();


        if ($schedules->isEmpty()) {

            return response()->json([]);
        }



        // citas existentes ese día

        $appointments = Appointment::withoutGlobalScope('active_clinic_branch')->where('professional_id', $professionalId)
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

    public function weeklyAvailability(Request $request)
    {
        $request->validate([
            'professional_id' => 'nullable|integer',
            'date' => 'required|date',
            'appointment_id' => 'nullable|integer',
        ]);

        $customerId = app('customer')->id;
        $permission = $request->filled('appointment_id') ? 'appointments.update' : 'appointments.create';
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), $permission)) {
            $doctor = null;
        }
        $professionalId = $doctor?->id ?? $request->professional_id;
        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            abort(404);
        }
        $branchId = $this->appointmentBranchId($request, (int) $customerId, $permission);
        if (!$branchId || !User::query()->whereKey($professionalId)->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))->exists()) {
            abort(404);
        }

        $timezone = config('app.timezone', 'America/Santiago');
        $today = now($timezone)->startOfDay();
        $weekStart = Carbon::parse($request->date, $timezone)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addWeek();
        $availability = [];
        $schedulesByDay = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('user_id', $professionalId)
            ->where('clinic_branch_id', $branchId)
            ->get()
            ->groupBy('day_of_week');
        $appointmentsByDate = Appointment::withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)
            ->where('professional_id', $professionalId)
            ->whereIn('status', [0, 1, 2])
            ->when($request->filled('appointment_id'), fn ($query) => $query->where('id', '!=', $request->appointment_id))
            ->where('start_at', '<', $weekEnd)
            ->where('end_at', '>', $weekStart)
            ->get(['start_at', 'end_at'])
            ->groupBy(fn ($appointment) => Carbon::parse($appointment->start_at, $timezone)->toDateString());
        $now = now($timezone);

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->copy()->addDays($offset);
            $date = $day->toDateString();
            if (UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
                ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists()) {
                $availability[$date] = [];
                continue;
            }
            if ($day->lt($today)) {
                $availability[$date] = [];
                continue;
            }

            $daySlots = [];
            $dayAppointments = $appointmentsByDate->get($date, collect());
            foreach ($schedulesByDay->get($day->dayOfWeek, collect()) as $schedule) {
                $slotStart = Carbon::parse($date . ' ' . Carbon::parse($schedule->start_time)->format('H:i:s'), $timezone);
                $scheduleEnd = Carbon::parse($date . ' ' . Carbon::parse($schedule->end_time)->format('H:i:s'), $timezone);

                while ($slotStart->copy()->addMinutes(30)->lte($scheduleEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes(30);
                    $occupied = $dayAppointments->contains(fn ($appointment) =>
                        $slotStart->lt(Carbon::parse($appointment->end_at, $timezone))
                        && $slotEnd->gt(Carbon::parse($appointment->start_at, $timezone))
                    );

                    if (!$occupied && $slotStart->gt($now)) {
                        $daySlots[] = [
                            'value' => $slotStart->format('H:i'),
                            'label' => $slotStart->format('H:i') . ' - ' . $slotEnd->format('H:i'),
                        ];
                    }

                    $slotStart->addMinutes(30);
                }
            }

            $availability[$date] = collect($daySlots)->unique('value')->sortBy('value')->values()->all();
        }

        return response()->json(['availability' => $availability]);
    }

    public function store(Request $request)
    {
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionalId = $doctor?->id ?? $request->input('professional_id');

        $request->validate([

            'professional_id' => 'nullable|integer',

            'clinic_branch_id' => ['nullable', 'integer'],

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

            'modality' => ['required', Rule::in(['in_person', 'telemedicine'])],
            'whatsapp_consent' => ['nullable', 'boolean'],

            'reason' => 'nullable|string',

            'notes' => 'nullable|string',

        ]);

        $branchId = $this->appointmentBranchId($request, (int) $customerId);
        if (ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->exists() && !$branchId) {
            return response()->json(['success' => false, 'message' => 'Selecciona una sede al iniciar sesión para agendar presencialmente.'], 422);
        }

        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {

        return response()->json(['success' => false, 'message' => 'El profesional seleccionado no pertenece a esta clínica.'], 422);
        }

        if ($branchId && !User::query()->whereKey($professionalId)->where('customer_id', $customerId)
            ->whereHas('branches', fn ($query) => $query->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))->exists()) {
            return response()->json(['success' => false, 'message' => 'El profesional no tiene asignada la sede seleccionada.'], 422);
        }

        $hasSpecialty = User::whereKey($professionalId)
            ->where('customer_id', $customerId)
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $request->specialty_id)
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $branchId ?? 0))
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

        if (UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
            ->whereDate('starts_on', '<=', $startAt->toDateString())->whereDate('ends_on', '>=', $startAt->toDateString())->exists()) {
            return response()->json(['success' => false, 'message' => 'El profesional tiene una ausencia registrada para esa fecha.'], 422);
        }



        /*
        |--------------------------------------------------------------------------
        | Validar horario del profesional
        |--------------------------------------------------------------------------
        */

        $dayOfWeek = $startAt->dayOfWeek;


        $available = ProfessionalSchedule::where('customer_id', $customerId)

            ->where('user_id', $professionalId)

            ->where('clinic_branch_id', $branchId)

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

        $appointment = DB::transaction(function () use ($customerId, $professionalId, $branchId, $request, $startAt, $endAt) {
            User::where('customer_id', $customerId)->whereKey($professionalId)->lockForUpdate()->firstOrFail();
            $exists = Appointment::withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)
                ->where('professional_id', $professionalId)
                ->whereNotIn('status', [4, 5]) // ajusta según tus estados
                ->where(function ($query) use ($startAt, $endAt) {

                    $query->where('start_at', '<', $endAt)
                        ->where('end_at', '>', $startAt);
                })
                ->exists();
            if ($exists) return null;
            return Appointment::create([

                'customer_id' => $customerId,

                'clinic_branch_id' => $branchId,

                'patient_id' => $request->patient_id,

                'specialty_id' => $request->specialty_id,

                'professional_id' => $professionalId,

                'start_at' => $startAt,

                'end_at' => $endAt,

                'status' => 0, // creada

                'modality' => $request->modality,
                'whatsapp_consent_at' => $request->boolean('whatsapp_consent') ? now() : null,

                'reason' => $request->reason,

                'notes' => $request->notes,

            ]);
        });
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'El horario seleccionado ya está ocupado.'], 422);
        }

        $patient = Patient::where('customer_id', $customerId)->findOrFail($request->patient_id);
        PatientAuditLogger::record($patient, 'created', 'Cita médica', [], [
            'profesional' => User::find($professionalId)?->name,
            'fecha de inicio' => $startAt->format('Y-m-d H:i:s'),
            'fecha de término' => $endAt->format('Y-m-d H:i:s'),
            'motivo' => $appointment->reason,
            'observaciones' => $appointment->notes,
            'modalidad' => $appointment->modality === 'telemedicine' ? 'Remota (telemedicina)' : 'Presencial',
            'estado' => 'Agendada',
        ]);

        $this->notifyProfessionalAppointment(
            (int) $professionalId,
            $appointment,
            'created',
            'Nueva cita agendada',
            'Se agendó una nueva cita en tu agenda.'
        );



        app(ClinicAppointmentMessenger::class)->send($appointment, 'created');

        return response()->json([

            'success' => true,

            'message' => 'Cita creada correctamente.'

        ]);
    }

    public function update(Request $request, $customer, $id)
    {
        $customerId = app('customer')->id;
        $branchId = $this->appointmentBranchId($request, (int) $customerId, 'appointments.update');
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), 'appointments.update')) {
            $doctor = null;
        }
        $appointment = Appointment::where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)
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
            'clinic_branch_id' => ['nullable', 'integer'],
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('customer_id', $customerId)],
            'specialty_id' => ['required', Rule::exists('specialties', 'id')->where('customer_id', $customerId)],
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'modality' => ['required', Rule::in(['in_person', 'telemedicine'])],
            'whatsapp_consent' => ['nullable', 'boolean'],
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->exists()
            && ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->exists()
            && !$branchId) {
            return response()->json(['success' => false, 'message' => 'Selecciona una sede al iniciar sesión para agendar presencialmente.'], 422);
        }

        if (!$professionalId || !$this->professionalBelongsToCustomer($professionalId, $customerId)) {
            return response()->json(['success' => false, 'message' => 'El profesional seleccionado no pertenece a esta clínica.'], 422);
        }

        $hasSpecialty = User::whereKey($professionalId)
            ->where('customer_id', $customerId)
            ->whereHas('specialties', fn ($query) => $query
                ->where('specialties.id', $request->specialty_id)
                ->where('specialty_user.customer_id', $customerId)
                ->where('specialty_user.clinic_branch_id', $branchId ?? 0))
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
            ->where('clinic_branch_id', $branchId)
            ->where('day_of_week', $startAt->dayOfWeek)
            ->whereTime('start_time', '<=', $startAt->format('H:i:s'))
            ->whereTime('end_time', '>=', $endAt->format('H:i:s'))
            ->exists();

        if ($branchId && !User::query()->whereKey($professionalId)->where('customer_id', $customerId)
            ->whereHas('branches', fn ($query) => $query->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->where('clinic_branch_user.status', true))->exists()) {
            return response()->json(['success' => false, 'message' => 'El profesional no tiene asignada la sede seleccionada.'], 422);
        }

        $keepingExistingTime = Carbon::parse($appointment->start_at)->equalTo($startAt)
            && Carbon::parse($appointment->end_at)->equalTo($endAt);

        $keepingExistingSlot = $keepingExistingTime
            && (int) $appointment->professional_id === (int) $professionalId;

        if (!$keepingExistingSlot && UserAbsence::query()->where('customer_id', $customerId)->where('user_id', $professionalId)
            ->whereDate('starts_on', '<=', $startAt->toDateString())->whereDate('ends_on', '>=', $startAt->toDateString())->exists()) {
            return response()->json(['success' => false, 'message' => 'El profesional tiene una ausencia registrada para esa fecha.'], 422);
        }

        if (!$keepingExistingSlot && !$startAt->gt(now(config('app.timezone', 'America/Santiago')))) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona una hora futura disponible según la hora de Chile.',
            ], 422);
        }

        if (!$available && !$keepingExistingTime) {
            return response()->json(['success' => false, 'message' => 'El profesional no tiene disponibilidad en ese horario.'], 422);
        }

        $occupied = Appointment::withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)
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
            'patient_id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'modality', 'reason', 'notes',
        ]));
        $appointment->update([
            'patient_id' => $request->patient_id,
            'specialty_id' => $request->specialty_id,
            'professional_id' => $professionalId,
            'clinic_branch_id' => $branchId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'modality' => $request->modality,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);

        if ($request->boolean('whatsapp_consent') && !$appointment->whatsapp_consent_at) {
            $appointment->forceFill(['whatsapp_consent_at' => now()])->save();
        }

        $appointment->refresh();
        $after = array_intersect_key($appointment->getRawOriginal(), array_flip([
            'patient_id', 'professional_id', 'specialty_id', 'start_at', 'end_at', 'modality', 'reason', 'notes',
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

        app(ClinicAppointmentMessenger::class)->send($appointment, 'updated');
        return response()->json(['success' => true, 'message' => 'Cita actualizada correctamente.']);
    }

    //cancelar cita
    public function cancel(Request $request)
    {
        $id = $request->route('id');
        $customerId = app('customer')->id;
        $branchId = $this->appointmentBranchId($request, (int) $customerId, 'appointments.cancel');
        $doctor = $this->loggedInDoctor($customerId);
        if ($doctor && ClinicBranchContext::canAdministerAllBranches($request->user(), (int) $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), (int) $customerId, (int) $this->activeBranchId((int) $customerId), 'appointments.cancel')) {
            $doctor = null;
        }

        $appointment = Appointment::where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)
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

        app(ClinicAppointmentMessenger::class)->send($appointment, 'cancelled');
        return response()->json([
            'success' => true,
            'message' => 'Cita cancelada correctamente.'
        ]);
    }

    private function hasDoctorRole($user): bool
    {
        if (!$user) {
            return false;
        }

        $customerId = (int) app('customer')->id;
        $branchId = $this->activeBranchId($customerId);

        return $branchId && $user->hasClinicRole('Doctor', $customerId, $branchId);
    }

    private function activeBranchId(int $customerId): ?int
    {
        if ((int) session('active_branch_customer_id') !== $customerId || !session()->has('active_branch_id')) {
            return null;
        }

        return ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->whereKey((int) session('active_branch_id'))
            ->value('id');
    }

    private function appointmentBranchId(Request $request, int $customerId, string $permission = 'appointments.create'): ?int
    {
        $activeBranchId = $this->activeBranchId($customerId);
        $requestedBranchId = $request->filled('clinic_branch_id')
            ? (int) $request->input('clinic_branch_id')
            : $activeBranchId;

        if (!$requestedBranchId) {
            return null;
        }

        $branchIsAvailable = ClinicBranchContext::availableBranches($request->user(), $customerId)
            ->contains(fn ($branch) => (int) $branch->id === $requestedBranchId);
        abort_unless($branchIsAvailable, 403, 'No tienes acceso a la sede seleccionada.');

        if ($requestedBranchId !== (int) $activeBranchId) {
            abort_unless(
                ClinicBranchContext::isHeadquartersActive($customerId)
                    &&
                ClinicBranchContext::canOperateAcrossBranches($request->user(), $customerId)
                    && $activeBranchId
                    && ClinicBranchContext::hasPermissionInBranch(
                        $request->user(), $customerId, $activeBranchId, $permission
                    ),
                403,
                'No tienes permiso para agendar citas en otra sede.'
            );
        }

        return $requestedBranchId;
    }

    private function appointmentCalendarBranchId(Request $request, int $customerId): ?int
    {
        $activeBranchId = $this->activeBranchId($customerId);
        $requestedBranchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : $activeBranchId;
        if (!$requestedBranchId) return null;
        if ($requestedBranchId === (int) $activeBranchId) return $activeBranchId;

        abort_unless(ClinicBranchContext::isHeadquartersActive($customerId)
            && ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            && ClinicBranchContext::hasPermissionInBranch($request->user(), $customerId, (int) $activeBranchId, 'appointments.view')
            && ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->whereKey($requestedBranchId)->exists(),
            403, 'No tienes permiso para consultar la agenda de esta sede.');

        return $requestedBranchId;
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
