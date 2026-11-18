<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Encounter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;
        $customerId = (int) app('customer')->id;
        $user = auth()->user();
        if ($user && $user->customer_id !== null && (int) $user->status !== 1) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'La cuenta profesional está inactiva y no puede iniciar sesión. Contacta al administrador de la clínica.',
            ]);
        }

        $doctor = $this->loggedInDoctor($customerId);
        $user = auth()->user();
        $canManageAllBranches = $this->canViewAllBranches($user, $customerId);
        if ($doctor && $canManageAllBranches) {
            $doctor = null;
        }
        $activeBranchId = $this->activeBranchId($customerId);
        $isHeadquarters = $activeBranchId !== null && $activeBranchId === $this->headquartersBranchId($customerId);
        $showBranchStatistics = $canManageAllBranches && $isHeadquarters;
        $firstPatientYear = DB::table('patients')
            ->where('customer_id', $customerId)
            ->when(!$showBranchStatistics && $activeBranchId, fn ($query) => $query->where('clinic_branch_id', $activeBranchId))
            ->min(DB::raw('YEAR(created_at)'));
        $years = range($currentYear, min((int) ($firstPatientYear ?: $currentYear), $currentYear));

        $activeBranchName = null;
        if (!$showBranchStatistics && $activeBranchId !== null) {
            $activeBranchName = ClinicBranch::query()->where('customer_id', $customerId)
                ->where('status', true)->whereKey($activeBranchId)->value('name');
        }
        $professionals = $doctor
            ? collect()
            : User::query()
                ->where('customer_id', $customerId)
                ->whereHas('branchRoles', function ($roles) use ($customerId, $activeBranchId, $showBranchStatistics) {
                    $roles->where('roles.customer_id', $customerId)
                        ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['doctor'])
                        ->when(!$showBranchStatistics && $activeBranchId, fn ($query) => $query->where('clinic_branch_user_role.clinic_branch_id', $activeBranchId));
                })
                ->orderBy('name')
                ->get(['id', 'name', 'status']);

        $branches = $showBranchStatistics
            ? ClinicBranch::query()->where('customer_id', app('customer')->id)->where('status', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('customer.dashboard.index', [
            'isDoctor' => $doctor !== null,
            'professionalName' => $doctor?->name,
            'professionals' => $professionals,
            'currentYear' => $currentYear,
            'years' => $years,
            'branches' => $branches,
            'showBranchStatistics' => $showBranchStatistics,
            'activeBranchName' => $activeBranchName,
            'ongoingPanel' => $this->ongoingEncounters($user, $customerId, $activeBranchId, $canManageAllBranches),
        ]);
    }

    private function ongoingEncounters(?User $user, int $customerId, ?int $activeBranchId, bool $canManageAllBranches): array
    {
        $isAdmin = $user && $activeBranchId && $user->hasClinicRole('Administrador', $customerId, $activeBranchId);
        $isDoctor = $user && $activeBranchId && $user->hasClinicRole('Doctor', $customerId, $activeBranchId);
        if (!$user || (!$isAdmin && !$isDoctor && !$canManageAllBranches)) {
            return ['visible' => false, 'tabs' => false, 'groups' => collect()];
        }

        $branches = ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)
            ->when(!$canManageAllBranches, fn ($query) => $query->whereKey($activeBranchId ?? 0))
            ->orderBy('name')->get(['id', 'name']);
        $encounters = Encounter::query()->where('customer_id', $customerId)->where('status', 1)
            ->whereIn('clinic_branch_id', $branches->pluck('id'))
            ->when(!$isAdmin && !$canManageAllBranches, fn ($query) => $query
                ->where(function ($owner) use ($user, $customerId) {
                    $owner->where('professional_id', $user->id)
                        ->orWhere(function ($legacy) use ($user, $customerId) {
                            $legacy->whereNull('professional_id')->whereHas('appointment', fn ($appointment) => $appointment
                                ->withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)->where('professional_id', $user->id));
                        });
                }))
            ->with(['patient:id,customer_id,clinic_branch_id,name,rut', 'professional:id,name', 'specialty:id,name',
                'appointment' => fn ($query) => $query->withoutGlobalScope('active_clinic_branch')
                    ->where('customer_id', $customerId)->with(['professional:id,name', 'specialty:id,name'])])
            ->orderBy('started_at')->orderBy('id')->get()->groupBy('clinic_branch_id');

        return [
            'visible' => true,
            'tabs' => $canManageAllBranches,
            'groups' => $branches->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'encounters' => $encounters->get($branch->id, collect()),
            ]),
        ];
    }

    public function statistics(Request $request)
    {
        $year = $request->integer('year');
        $month = $request->integer('month');
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $canManageAllBranches = $this->canViewAllBranches(auth()->user(), (int) $customerId);
        if ($doctor && $canManageAllBranches) {
            $doctor = null;
        }
        $professionalId = $doctor?->id;
        $activeBranchId = $this->activeBranchId((int) $customerId);
        $isHeadquarters = $activeBranchId !== null && $activeBranchId === $this->headquartersBranchId((int) $customerId);
        $canViewAllBranches = $canManageAllBranches && $isHeadquarters;
        $branchId = null;

        if ($request->filled('branch_id')) {
            abort_unless($canViewAllBranches, 403);
            $branchId = ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)
                ->whereKey($request->integer('branch_id'))->value('id');
            abort_unless($branchId, 404, 'La sede seleccionada no pertenece a esta clínica o está inactiva.');
        } elseif (!$canViewAllBranches) {
            $branchId = $activeBranchId;
        }

        if (!$doctor && $request->filled('professional_id')) {
            $professionalId = User::query()
                ->whereKey($request->integer('professional_id'))
                ->where('customer_id', $customerId)
                ->whereHas('branchRoles', function ($roles) use ($customerId, $activeBranchId) {
                    $roles->where('roles.customer_id', $customerId)->whereRaw('LOWER(TRIM(roles.name)) = ?', ['doctor'])
                        ->when($activeBranchId, fn ($query) => $query->where('clinic_branch_user_role.clinic_branch_id', $activeBranchId));
                })
                ->value('id');

            abort_unless($professionalId, 404, 'El profesional seleccionado no pertenece a esta clínica.');
        }

        $professionals = $doctor ? collect() : User::query()
            ->where('customer_id', $customerId)
            ->whereHas('branchRoles', function ($roles) use ($customerId, $branchId) {
                $roles->where('roles.customer_id', $customerId)->whereRaw('LOWER(TRIM(roles.name)) = ?', ['doctor'])
                    ->when($branchId, fn ($query) => $query->where('clinic_branch_user_role.clinic_branch_id', $branchId));
            })
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branches) => $branches->whereKey($branchId)))
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (User $professional) => [
                'id' => $professional->id,
                'name' => $professional->name,
                'status' => (int) $professional->status,
            ])->values();

        // Pacientes atendidos
        $patientsQuery = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->where('status', 3)
            ->whereYear('start_at', $year);

        if ($month) {
            $patientsQuery->whereMonth('start_at', $month);
        }

        $totalPatients = $patientsQuery
            ->distinct('patient_id')
            ->count('patient_id');


        // Atenciones realizadas
        $appointmentsQuery = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->where('status', 3)
            ->whereYear('start_at', $year);

        if ($month) {
            $appointmentsQuery->whereMonth('start_at', $month);
        }

        $totalAppointments = $appointmentsQuery->count();

        // citas agendadas
        $totalBookings  = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->where('status', 0)
            ->whereYear('start_at', $year);

        if ($month) {
            $totalBookings->whereMonth('start_at', $month);
        }

        $totalBookings = $totalBookings->count();

        // citas agendadas
        $ConfirmBookings  = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->where('status', 1)
            ->whereYear('start_at', $year);

        if ($month) {
            $ConfirmBookings->whereMonth('start_at', $month);
        }

        $ConfirmBookings = $ConfirmBookings->count();

        $todayStatusCounts = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->whereDate('start_at', today()->toDateString())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $todayCounts = [];
        for ($status = 0; $status <= 5; $status++) {
            $todayCounts[$status] = (int) ($todayStatusCounts[$status] ?? 0);
        }

        // Una próxima cita pendiente por profesional; después se muestran las tres más cercanas.
        $rankedUpcomingAppointments = Appointment::query()
            ->where('appointments.customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('appointments.clinic_branch_id', $branchId))
            ->whereNotNull('appointments.professional_id')
            ->whereIn('appointments.status', [0, 1])
            ->where('appointments.start_at', '>=', now())
            ->whereDoesntHave('encounter')
            ->when($professionalId, fn ($query) => $query->where('appointments.professional_id', $professionalId))
            ->select('appointments.id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY appointments.professional_id ORDER BY appointments.start_at, appointments.id) as appointment_position');

        $nextAppointments = Appointment::query()
            ->joinSub($rankedUpcomingAppointments, 'ranked_upcoming_appointments', fn ($join) =>
                $join->on('appointments.id', '=', 'ranked_upcoming_appointments.id'))
            ->where(
                'ranked_upcoming_appointments.appointment_position',
                ($doctor || $professionalId !== null) ? '<=' : '=',
                ($doctor || $professionalId !== null) ? 3 : 1
            )
            ->with(['professional:id,name', 'patient:id,customer_id,clinic_branch_id,name,rut', 'specialty:id,name'])
            ->orderBy('appointments.start_at')
            ->orderBy('appointments.id')
            ->limit(3)
            ->get()
            ->map(function (Appointment $appointment, int $index) use ($doctor) {
                $startsAt = Carbon::parse($appointment->start_at)->timezone(config('app.timezone'));
                $canStart = $doctor && (int) $appointment->professional_id === (int) $doctor->id;

                return [
                    'appointment_id' => (int) $appointment->id,
                    'patient_id' => (int) $appointment->patient_id,
                    'professional' => $appointment->professional?->name ?? 'Profesional no asignado',
                    'patient' => $appointment->patient?->name ?? 'Paciente no disponible',
                    'patient_rut' => $appointment->patient?->identification_label,
                    'specialty' => $appointment->specialty?->name ?? 'Sin especialidad',
                    'date' => $startsAt->format('d/m/Y'),
                    'time' => $startsAt->format('H:i'),
                    'status_code' => (int) $appointment->status,
                    'status' => (int) $appointment->status === 1 ? 'Confirmada' : 'Agendada',
                    'position' => $index + 1,
                    'can_start' => (bool) $canStart,
                    'patient_url' => route('patients.medical-record.show', array_filter([
                        'customer' => request()->route('customer'),
                        'id' => $appointment->patient_id,
                        'start_encounter' => $canStart ? $appointment->id : null,
                    ], fn ($value) => $value !== null)),
                ];
            })
            ->values();

        if ($month) {

            // Por día
            $chartData = Appointment::query()
                ->where('customer_id', $customerId)
                ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
                ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                ->whereYear('start_at', $year)
                ->whereMonth('start_at', $month)
                ->selectRaw('DAY(start_at) as period')
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw('DAY(start_at), status')
                ->orderByRaw('DAY(start_at)')
                ->get();
        } else {

            // Por mes
            $chartData = Appointment::query()
                ->where('customer_id', $customerId)
                ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
                ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                ->whereYear('start_at', $year)
                ->selectRaw('MONTH(start_at) as period')
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw('MONTH(start_at), status')
                ->orderByRaw('MONTH(start_at)')
                ->get();
        }

        $branchStatistics = [];
        if ($canViewAllBranches) {
            $branchAppointmentStats = DB::table('appointments')
                ->where('customer_id', $customerId)
                ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                ->whereYear('start_at', $year)
                ->when($month, fn ($query) => $query->whereMonth('start_at', $month))
                ->whereNotNull('clinic_branch_id')
                ->select('clinic_branch_id')
                ->selectRaw('COUNT(*) as appointments_total')
                ->selectRaw('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as attended_total')
                ->groupBy('clinic_branch_id')
                ->get()->keyBy('clinic_branch_id');

            $branchPatientStats = DB::table('patients')
                ->where('customer_id', $customerId)
                ->whereYear('created_at', $year)
                ->when($month, fn ($query) => $query->whereMonth('created_at', $month))
                ->whereNotNull('clinic_branch_id')
                ->select('clinic_branch_id')
                ->selectRaw('COUNT(*) as patients_total')
                ->groupBy('clinic_branch_id')
                ->get()->keyBy('clinic_branch_id');

            $branchStatistics[] = [
                'id' => null,
                'name' => 'Todas las sedes',
                'patients' => (int) DB::table('patients')->where('customer_id', $customerId)
                    ->whereYear('created_at', $year)->when($month, fn ($query) => $query->whereMonth('created_at', $month))->count(),
                'appointments' => (int) DB::table('appointments')->where('customer_id', $customerId)
                    ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                    ->whereYear('start_at', $year)->when($month, fn ($query) => $query->whereMonth('start_at', $month))->count(),
                'attended' => (int) DB::table('appointments')->where('customer_id', $customerId)
                    ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                    ->where('status', 3)->whereYear('start_at', $year)
                    ->when($month, fn ($query) => $query->whereMonth('start_at', $month))->count(),
            ];

            ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get(['id', 'name'])
                ->each(function (ClinicBranch $branch) use (&$branchStatistics, $branchAppointmentStats, $branchPatientStats) {
                    $branchStatistics[] = [
                        'id' => (int) $branch->id,
                        'name' => $branch->name,
                        'patients' => (int) ($branchPatientStats->get($branch->id)->patients_total ?? 0),
                        'appointments' => (int) ($branchAppointmentStats->get($branch->id)->appointments_total ?? 0),
                        'attended' => (int) ($branchAppointmentStats->get($branch->id)->attended_total ?? 0),
                    ];
                });
        }

        /*
|--------------------------------------------------------------------------
| Pacientes atendidos por previsión
|--------------------------------------------------------------------------
*/

        $patientsByInsurance = DB::table('insurances')
            ->leftJoin('patients', function ($join) use ($customerId) {
                $join->on('insurances.id', '=', 'patients.insurance_id')
                    ->where('patients.customer_id', $customerId);
            })
            ->leftJoin('appointments', function ($join) use ($year, $month, $customerId, $professionalId, $branchId) {

                $join->on(
                    'patients.id',
                    '=',
                    'appointments.patient_id'
                )
                    ->where('appointments.status', 3)
                    ->where('appointments.customer_id', $customerId)
                    ->whereYear('appointments.start_at', $year);

                if ($professionalId) {
                    $join->where('appointments.professional_id', $professionalId);
                }

                if ($branchId) {
                    $join->where('appointments.clinic_branch_id', $branchId);
                }

                if ($month) {
                    $join->whereMonth('appointments.start_at', $month);
                }
            })
            ->where(function ($query) use ($customerId) {
                $query->where('insurances.customer_id', $customerId)
                    ->orWhereNull('insurances.customer_id');
            })
            ->select(
                'insurances.id',
                'insurances.name as insurance'
            )
            ->selectRaw('COUNT(DISTINCT appointments.patient_id) as total')
            ->groupBy(
                'insurances.id',
                'insurances.name'
            )
            ->orderBy('insurances.name')
            ->get();


      
    /*
|--------------------------------------------------------------------------
| Atenciones por especialidad
|--------------------------------------------------------------------------
*/

$appointmentsBySpecialty = DB::table('specialties')
    ->leftJoin('appointments', function ($join) use ($year, $month, $customerId, $professionalId, $branchId) {

        $join->on(
            'specialties.id',
            '=',
            'appointments.specialty_id'
        )
        ->where('appointments.customer_id', $customerId)
        ->where('appointments.status', 3)
        ->whereYear('appointments.start_at', $year);

        if ($professionalId) {
            $join->where('appointments.professional_id', $professionalId);
        }

        if ($branchId) {
            $join->where('appointments.clinic_branch_id', $branchId);
        }

        if ($month) {
            $join->whereMonth(
                'appointments.start_at',
                $month
            );
        }
    })
    ->where('specialties.customer_id', $customerId)
    ->select(
        'specialties.id',
        'specialties.catalog_id',
        'specialties.name as specialty'
    )
    ->selectRaw('COUNT(appointments.id) as total')
    ->groupBy(
        'specialties.id',
        'specialties.catalog_id',
        'specialties.name'
    )
    ->orderBy('specialties.name')
    ->get()
    ->groupBy(fn ($row) => $row->catalog_id ? 'catalog:'.$row->catalog_id : 'legacy:'.$row->id)
    ->map(function ($rows) {
        $first = $rows->first();
        $first->specialty = app(\App\Services\SpecialtyCatalog::class)->name($first->catalog_id) ?? $first->specialty;
        $first->total = $rows->sum('total');
        return $first;
    })->values();

$patientsQuery = $professionalId
    ? DB::table('appointments')
        ->where('customer_id', $customerId)
        ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
        ->where('professional_id', $professionalId)
        ->where('status', 3)
        ->whereYear('start_at', $year)
    : DB::table('patients')
        ->where('customer_id', $customerId)
        ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
        ->whereYear('created_at', $year);

if ($month) {
    $patientsQuery->whereMonth($professionalId ? 'start_at' : 'created_at', $month);
}

$patientPeriodColumn = $professionalId ? 'start_at' : 'created_at';
$patientTotalExpression = $professionalId ? 'COUNT(DISTINCT patient_id)' : 'COUNT(*)';

if ($month) {

    $patientsRegistered = $patientsQuery
        ->selectRaw("DAY({$patientPeriodColumn}) as period")
        ->selectRaw("{$patientTotalExpression} as total")
        ->groupByRaw("DAY({$patientPeriodColumn})")
        ->orderByRaw("DAY({$patientPeriodColumn})")
        ->get();

} else {

    $patientsRegistered = $patientsQuery
        ->selectRaw("MONTH({$patientPeriodColumn}) as period")
        ->selectRaw("{$patientTotalExpression} as total")
        ->groupByRaw("MONTH({$patientPeriodColumn})")
        ->orderByRaw("MONTH({$patientPeriodColumn})")
        ->get();
}
        return response()->json([
            'totalPatients' => $totalPatients,
            'totalAppointments' => $totalAppointments,
            'totalBookings' => $totalBookings,
            'ConfirmBookings' => $ConfirmBookings,
            'todayAppointments' => [
                'total' => array_sum($todayCounts),
                'scheduled' => $todayCounts[0],
                'confirmed' => $todayCounts[1],
                'inProgress' => $todayCounts[2],
                'attended' => $todayCounts[3],
                'noShow' => $todayCounts[4],
                'cancelled' => $todayCounts[5],
            ],
            'nextAppointments' => $nextAppointments,
            'chartData' => $chartData,
            'patientsByInsurance' => $patientsByInsurance,
            'appointmentsBySpecialty' => $appointmentsBySpecialty,
            'patientsRegistered' => $patientsRegistered,
            'branchStatistics' => $branchStatistics,
            'professionals' => $professionals,
        ]);
    }

    private function canViewAllBranches(?User $user, int $customerId): bool
    {
        return $user !== null
            && \App\Support\ClinicBranchContext::canAdministerAllBranches($user, $customerId);
    }

    private function activeBranchId(int $customerId): ?int
    {
        if ((int) session('active_branch_customer_id') !== $customerId || !session()->has('active_branch_id')) {
            return null;
        }

        $branchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->whereKey((int) session('active_branch_id'))
            ->value('id');

        return $branchId === null ? null : (int) $branchId;
    }

    private function headquartersBranchId(int $customerId): ?int
    {
        $branchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->value('id');

        return $branchId === null ? null : (int) $branchId;
    }

    private function loggedInDoctor($customerId): ?User
    {
        $user = auth()->user();
        $activeBranchId = $this->activeBranchId((int) $customerId);
        if (!$user || !$activeBranchId || !$user->hasClinicRole('Doctor', (int) $customerId, $activeBranchId)) {
            return null;
        }

        abort_unless(
            (int) $user->customer_id === (int) $customerId && (int) $user->status === 1,
            403,
            'La cuenta profesional no está habilitada para esta clínica.'
        );

        return $user;
    }
}
