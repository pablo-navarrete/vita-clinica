<?php

namespace App\Http\Controllers\customer;
use App\Http\Controllers\Controller;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfessionalScheduleController extends Controller
{

   public function getSchedule($customer, $professional)
{
    $schedules = ProfessionalSchedule::where('customer_id', app('customer')->id)
    ->where('user_id', $professional)
    ->orderByRaw("
        CASE day_of_week
            WHEN 1 THEN 1
            WHEN 2 THEN 2
            WHEN 3 THEN 3
            WHEN 4 THEN 4
            WHEN 5 THEN 5
            WHEN 6 THEN 6
            WHEN 0 THEN 7
        END
    ")
    ->get();

    return response()->json($schedules);
}
   public function store(Request $request)
{
       
    $validator = Validator::make(
        $request->all(),
        [
            'day_of_week' => 'required|integer|between:0,6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
        ],
        [
            'day_of_week.required' => 'Debe seleccionar un día.',
            'day_of_week.integer' => 'El día seleccionado no es válido.',
            'start_time.required' => 'Debe ingresar la hora de inicio.',
            'start_time.date_format' => 'La hora de inicio no tiene un formato válido.',
            'end_time.required' => 'Debe ingresar la hora de término.',
            'end_time.date_format' => 'La hora de término no tiene un formato válido.',
            'end_time.after' => 'La hora de término debe ser posterior a la hora de inicio.',
        ]
    );


    if ($validator->fails()) {

        return response()->json([
            'errors' => $validator->errors()
        ], 422);

    }



    $professionalId = request()->route('professional');


    $professional = User::where('customer_id', app('customer')->id)
        ->where('id', $professionalId)
        ->firstOrFail();



    /*
    |--------------------------------------------------------------------------
    | Validar cruces de horarios existentes
    |--------------------------------------------------------------------------
    */

    $overlap = ProfessionalSchedule::where('customer_id', app('customer')->id)
        ->where('user_id', $professional->id)
        ->where('day_of_week', $request->day_of_week)
        ->where(function ($query) use ($request) {

            $query->where(function ($q) use ($request) {

                // Nuevo horario empieza dentro de uno existente
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);

            });

        })
        ->exists();



    if ($overlap) {

        return response()->json([

            'success' => false,

            'message' => 'El horario ingresado se cruza con otro horario existente.'

        ], 422);

    }



    $schedule = ProfessionalSchedule::create([

        'customer_id' => app('customer')->id,

        'user_id' => $professional->id,

        'day_of_week' => $request->day_of_week,

        'start_time' => $request->start_time,

        'end_time' => $request->end_time,

    ]);



    return response()->json([

        'success' => true,

        'data' => $schedule,

        'message' => 'Horario creado correctamente.'

    ]);

}
}
