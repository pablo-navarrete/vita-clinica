<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {

        return view('customer.dashboard.index');
    }

    public function statistics(Request $request)
    {
        $year = $request->integer('year');
        $month = $request->integer('month');

        // Pacientes atendidos
        $patientsQuery = Appointment::query()
            ->where('customer_id', app('customer')->id)
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
            ->where('customer_id', app('customer')->id)
            ->where('status', 3)
            ->whereYear('start_at', $year);

        if ($month) {
            $appointmentsQuery->whereMonth('start_at', $month);
        }

        $totalAppointments = $appointmentsQuery->count();

        // citas agendadas
        $totalBookings  = Appointment::query()
            ->where('customer_id', app('customer')->id)
            ->where('status', 0)
            ->whereYear('start_at', $year);

        if ($month) {
            $totalBookings->whereMonth('start_at', $month);
        }

        $totalBookings = $totalBookings->count();

        // citas agendadas
        $ConfirmBookings  = Appointment::query()
            ->where('customer_id', app('customer')->id)
            ->where('status', 1)
            ->whereYear('start_at', $year);

        if ($month) {
            $ConfirmBookings->whereMonth('start_at', $month);
        }

        $ConfirmBookings = $ConfirmBookings->count();

        if ($month) {

            // Por día
            $chartData = Appointment::query()
                ->where('customer_id', app('customer')->id)
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
                ->where('customer_id', app('customer')->id)
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
            ->leftJoin('patients', 'insurances.id', '=', 'patients.insurance_id')
            ->leftJoin('appointments', function ($join) use ($year, $month) {

                $join->on(
                    'patients.id',
                    '=',
                    'appointments.patient_id'
                )
                    ->where('appointments.status', 3)
                    ->whereYear('appointments.start_at', $year);

                if ($month) {
                    $join->whereMonth('appointments.start_at', $month);
                }
            })
            ->where(function ($query) {
                $query->where('insurances.customer_id', app('customer')->id)
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
    ->leftJoin('appointments', function ($join) use ($year, $month) {

        $join->on(
            'specialties.id',
            '=',
            'appointments.specialty_id'
        )
        ->where('appointments.customer_id', app('customer')->id)
        ->where('appointments.status', 3)
        ->whereYear('appointments.start_at', $year);

        if ($month) {
            $join->whereMonth(
                'appointments.start_at',
                $month
            );
        }
    })
    ->where('specialties.customer_id', app('customer')->id)
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

$patientsQuery = DB::table('patients')
    ->where('customer_id', app('customer')->id)
    ->whereYear('created_at', $year);

if ($month) {
    $patientsQuery->whereMonth('created_at', $month);
}

if ($month) {

    $patientsRegistered = $patientsQuery
        ->selectRaw('DAY(created_at) as period')
        ->selectRaw('COUNT(*) as total')
        ->groupByRaw('DAY(created_at)')
        ->orderByRaw('DAY(created_at)')
        ->get();

} else {

    $patientsRegistered = $patientsQuery
        ->selectRaw('MONTH(created_at) as period')
        ->selectRaw('COUNT(*) as total')
        ->groupByRaw('MONTH(created_at)')
        ->orderByRaw('MONTH(created_at)')
        ->get();
}
        return response()->json([
            'totalPatients' => $totalPatients,
            'totalAppointments' => $totalAppointments,
            'totalBookings' => $totalBookings,
            'ConfirmBookings' => $ConfirmBookings,
            'chartData' => $chartData,
            'patientsByInsurance' => $patientsByInsurance,
            'appointmentsBySpecialty' => $appointmentsBySpecialty,
            'patientsRegistered' => $patientsRegistered,
        ]);
    }
}
