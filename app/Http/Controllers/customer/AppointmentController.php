<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        $professionals = User::with('specialties')->where('customer_id', app('customer')->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $patients = Patient::where('customer_id', app('customer')->id)
            ->orderBy('name')
            ->get();

        $specialties = Specialty::where('customer_id', app('customer')->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get();
        return view('customer.Appointment.index', compact('professionals', 'patients', 'specialties'));
    }

    public function professionalsBySpecialty(Request $request)
    {
        $professionals = User::where('customer_id', app('customer')->id)
            ->where('status', 1)
            ->whereHas('specialties', function ($q) use ($request) {

                $q->where('specialty_id', $request->specialty_id);
            })
            ->orderBy('name')
            ->get();

        return response()->json($professionals);
    }

    public function events(Request $request)
    {
        $appointments = Appointment::where('customer_id', app('customer')->id)
            ->where('professional_id', $request->professional_id)

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


        $events = $appointments->map(function ($appointment) {

            $color = match ($appointment->status) {
                0 => '#3B82F6', // agendado
                1 => '#10B981', // Confirmada
                2 => '#F59E0B', // En atención
                3 => '#6366F1', // Atendida
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
                    'rut' => $appointment->patient->rut,
                    'reason' => $appointment->reason,
                    'notes' => $appointment->notes,
                    'status' => $appointment->status,
                    'specialty' => $appointment->specialty?->name,

                ],

                'backgroundColor' => $color,

                '       borderColor' => $color,

            ];
        });


        return response()->json($events);
    }

    public function availability(Request $request)
    {
        $request->validate([
            'professional_id' => 'required',
            'date' => 'required|date',
        ]);


        $professionalId = $request->professional_id;

        $date = Carbon::parse($request->date);


        $dayOfWeek = $date->dayOfWeek;


        // TODOS los horarios del profesional ese día
        $schedules = ProfessionalSchedule::where('user_id', $professionalId)
            ->where('customer_id', app('customer')->id)
            ->where('day_of_week', $dayOfWeek)
            ->get();


        if ($schedules->isEmpty()) {

            return response()->json([]);
        }



        // citas existentes ese día

        $appointments = Appointment::where('professional_id', $professionalId)
            ->where('customer_id', app('customer')->id)
            ->whereDate('start_at', $date->format('Y-m-d'))
            ->whereIn('status', [0, 1, 2])
            ->get();



        $available = [];



        foreach ($schedules as $schedule) {


            // construir inicio del bloque

            $start = Carbon::parse(
                $date->format('Y-m-d') . ' ' .
                    Carbon::parse($schedule->start_time)->format('H:i:s')
            );


            $end = Carbon::parse(
                $date->format('Y-m-d') . ' ' .
                    Carbon::parse($schedule->end_time)->format('H:i:s')
            );



            while ($start < $end) {


                $slotStart = $start->copy();

                $slotEnd = $start->copy()->addMinutes(30);



                // revisar si está ocupado

                $occupied = $appointments->contains(function ($appointment) use ($slotStart, $slotEnd) {

                    return
                        $slotStart < Carbon::parse($appointment->end_at)
                        &&
                        $slotEnd > Carbon::parse($appointment->start_at);
                });



                if (!$occupied) {


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



        return response()->json($available);
    }

    public function store(Request $request)
    {

    

        $request->validate([

            'professional_id' => 'required|exists:users,id',

            'patient_id' => 'required|exists:patients,id',

            'specialty_id' => 'required|exists:specialties,id',

            'date' => 'required|date|after_or_equal:today',

            'time' => 'required',

            'reason' => 'nullable|string',

            'notes' => 'nullable|string',

        ]);



        $customerId = app('customer')->id;



        /*
        |--------------------------------------------------------------------------
        | Crear fecha inicio y termino
        |--------------------------------------------------------------------------
        */

        $startAt = Carbon::parse(
            $request->date . ' ' . $request->time
        );


        // citas de 30 minutos

        $endAt = $startAt->copy()->addMinutes(30);



        /*
        |--------------------------------------------------------------------------
        | Validar horario del profesional
        |--------------------------------------------------------------------------
        */

        $dayOfWeek = $startAt->dayOfWeek;


        $available = ProfessionalSchedule::where('customer_id', $customerId)

            ->where('user_id', $request->professional_id)

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
            ->where('professional_id', $request->professional_id)
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

        Appointment::create([

            'customer_id' => $customerId,

            'patient_id' => $request->patient_id,

            'specialty_id' => $request->specialty_id,

            'professional_id' => $request->professional_id,

            'start_at' => $startAt,

            'end_at' => $endAt,

            'status' => 0, // creada

            'reason' => $request->reason,

            'notes' => $request->notes,

        ]);



        return response()->json([

            'success' => true,

            'message' => 'Cita creada correctamente.'

        ]);
    }
}
