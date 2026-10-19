<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;
        $firstPatientYear = DB::table('patients')
            ->where('customer_id', app('customer')->id)
            ->min(DB::raw('YEAR(created_at)'));
        $years = range($currentYear, min((int) ($firstPatientYear ?: $currentYear), $currentYear));

        $user = auth()->user();
        if ($user && $user->customer_id !== null && (int) $user->status !== 1) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'La cuenta profesional está inactiva y no puede iniciar sesión. Contacta al administrador de la clínica.',
            ]);
        }

        $doctor = $this->loggedInDoctor(app('customer')->id);
        $professionals = $doctor
            ? collect()
            : User::query()
                ->where('customer_id', app('customer')->id)
                ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'))
                ->orderBy('name')
                ->get(['id', 'name', 'status']);

        return view('customer.dashboard.index', [
            'isDoctor' => $doctor !== null,
            'professionalName' => $doctor?->name,
            'professionals' => $professionals,
            'currentYear' => $currentYear,
            'years' => $years,
        ]);
    }

    public function statistics(Request $request)
    {
        $year = $request->integer('year');
        $month = $request->integer('month');
        $customerId = app('customer')->id;
        $doctor = $this->loggedInDoctor($customerId);
        $professionalId = $doctor?->id;

        if (!$doctor && $request->filled('professional_id')) {
            $professionalId = User::query()
                ->whereKey($request->integer('professional_id'))
                ->where('customer_id', $customerId)
                ->whereHas('roles', fn ($query) => $query->where('roles.name', 'Doctor'))
                ->value('id');

            abort_unless($professionalId, 404, 'El profesional seleccionado no pertenece a esta clínica.');
        }

        // Pacientes atendidos
        $patientsQuery = Appointment::query()
            ->where('customer_id', $customerId)
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
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->where('status', 1)
            ->whereYear('start_at', $year);

        if ($month) {
            $ConfirmBookings->whereMonth('start_at', $month);
        }

        $ConfirmBookings = $ConfirmBookings->count();

        $todayStatusCounts = Appointment::query()
            ->where('customer_id', $customerId)
            ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
            ->whereDate('start_at', today()->toDateString())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $todayCounts = [];
        for ($status = 0; $status <= 5; $status++) {
            $todayCounts[$status] = (int) ($todayStatusCounts[$status] ?? 0);
        }

        if ($month) {

            // Por día
            $chartData = Appointment::query()
                ->where('customer_id', $customerId)
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
                ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                ->whereYear('start_at', $year)
                ->selectRaw('MONTH(start_at) as period')
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw('MONTH(start_at), status')
                ->orderByRaw('MONTH(start_at)')
                ->get();
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
            ->leftJoin('appointments', function ($join) use ($year, $month, $customerId, $professionalId) {

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
    ->leftJoin('appointments', function ($join) use ($year, $month, $customerId, $professionalId) {

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

        if ($month) {
            $join->whereMonth(
                'appointments.start_at',
                $month
            );
        }
    })
    ->where('specialties.customer_id', $customerId)
    ->where('specialties.status', true)
    ->select(
        'specialties.id',
        'specialties.name as specialty'
    )
    ->selectRaw('COUNT(appointments.id) as total')
    ->groupBy(
        'specialties.id',
        'specialties.name'
    )
    ->orderBy('specialties.name')
    ->get();

$patientsQuery = $professionalId
    ? DB::table('appointments')
        ->where('customer_id', $customerId)
        ->where('professional_id', $professionalId)
        ->where('status', 3)
        ->whereYear('start_at', $year)
    : DB::table('patients')
        ->where('customer_id', $customerId)
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
            'chartData' => $chartData,
            'patientsByInsurance' => $patientsByInsurance,
            'appointmentsBySpecialty' => $appointmentsBySpecialty,
            'patientsRegistered' => $patientsRegistered,
        ]);
    }

    private function loggedInDoctor($customerId): ?User
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->where('roles.name', 'Doctor')->exists()) {
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
