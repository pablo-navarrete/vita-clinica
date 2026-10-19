<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Appointment;
use Yajra\DataTables\Facades\DataTables;


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

        $schedules->each(function ($schedule) use ($professional) {

            $schedule->future_appointments_count = Appointment::where('customer_id', app('customer')->id)
                ->where('professional_id', $professional)

                // Solo citas futuras
                ->where('start_at', '>=', now())

                // Citas que no estén canceladas ni marcadas como no asistió
                ->whereNotIn('status', [4, 5])

                // Mismo día de la semana
                ->whereRaw(
                    'DAYOFWEEK(start_at) = ?',
                    [
                        $schedule->day_of_week == 0
                            ? 1
                            : $schedule->day_of_week + 1
                    ]
                )

                // La cita comienza dentro del horario
                ->whereTime('start_at', '>=', $schedule->start_time)
                ->whereTime('end_at', '<=', $schedule->end_time)

                ->count();
        });

        return response()->json($schedules);
    }

    /** Return only the schedules owned by the authenticated professional. */
    public function myScheduleData(Request $request)
    {
        $user = $request->user();
        $customerId = app('customer')->id;

        $schedules = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('user_id', $user->id)
            ->orderByRaw('CASE day_of_week WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 4 WHEN 5 THEN 5 WHEN 6 THEN 6 WHEN 0 THEN 7 END')
            ->orderBy('start_time')
            ->get();

        $schedules->each(function ($schedule) use ($user, $customerId) {
            $schedule->future_appointments_count = Appointment::where('customer_id', $customerId)
                ->where('professional_id', $user->id)
                ->where('start_at', '>=', now())
                ->whereNotIn('status', [4, 5])
                ->whereRaw('DAYOFWEEK(start_at) = ?', [$schedule->day_of_week === 0 ? 1 : $schedule->day_of_week + 1])
                ->whereTime('start_at', '>=', $schedule->start_time)
                ->whereTime('end_at', '<=', $schedule->end_time)
                ->count();
        });

        return response()->json($schedules);
    }

    /** Add availability for the authenticated professional only. */
    public function storeMySchedule(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $customerId = app('customer')->id;
        $user = $request->user();

        $overlap = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('user_id', $user->id)
            ->where('day_of_week', $validated['day_of_week'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'El horario ingresado se cruza con otro horario existente.',
            ], 422);
        }

        $schedule = ProfessionalSchedule::create([
            'customer_id' => $customerId,
            'user_id' => $user->id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $schedule,
            'message' => 'Horario agregado correctamente.',
        ]);
    }

    /** Delete an owned schedule only when it has no future affected appointments. */
    public function destroyMySchedule(Request $request)
    {
        $customerId = app('customer')->id;
        $user = $request->user();
        $id = $request->route('id');

        $schedule = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();

        $appointments = Appointment::where('customer_id', $customerId)
            ->where('professional_id', $user->id)
            ->where('start_at', '>=', now())
            ->whereNotIn('status', [4, 5])
            ->whereRaw('DAYOFWEEK(start_at) = ?', [$schedule->day_of_week === 0 ? 1 : $schedule->day_of_week + 1])
            ->whereTime('start_at', '>=', $schedule->start_time)
            ->whereTime('end_at', '<=', $schedule->end_time)
            ->orderBy('start_at')
            ->get();

        if ($appointments->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'has_appointments' => true,
                'future_appointments_count' => $appointments->count(),
                'message' => 'Este horario tiene citas futuras. Debes gestionar las citas afectadas antes de eliminarlo.',
            ], 422);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Horario eliminado correctamente.',
        ]);
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

    public function destroy(Request $request)
    {
        $id = $request->route('id');

        $schedule = ProfessionalSchedule::where('customer_id', app('customer')->id)
            ->where('id', $id)
            ->firstOrFail();

        $appointments = Appointment::where('customer_id', app('customer')->id)
            ->where('professional_id', $schedule->user_id)
            ->where('start_at', '>=', now())
            ->whereNotIn('status', [4, 5])
            ->whereRaw(
                'DAYOFWEEK(start_at) = ?',
                [
                    $schedule->day_of_week == 0
                        ? 1
                        : $schedule->day_of_week + 1
                ]
            )
            ->whereTime('start_at', '>=', $schedule->start_time)
            ->whereTime('end_at', '<=', $schedule->end_time)
            ->orderBy('start_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Hay citas pendientes
        |--------------------------------------------------------------------------
        */

        if ($appointments->isNotEmpty()) {

            return response()->json([
                'success' => false,
                'has_appointments' => true,

                'professional_id' => $schedule->user_id,
                'schedule_id' => $schedule->id,

                'message' => 'Este horario tiene citas agendadas.',

                'appointments' => $appointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,

                        'patient' => $appointment->patient?->name ?? 'Paciente',

                        'specialty' => $appointment->specialty?->name ?? 'Sin especialidad',

                        'start_at' => $appointment->start_at->format('Y-m-d H:i:s'),

                        'end_at' => $appointment->end_at->format('Y-m-d H:i:s'),
                    ];
                })->values(),

            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | No hay citas: eliminar horario
        |--------------------------------------------------------------------------
        */

        $schedule->delete();

        return response()->json([
            'success' => true,
            'has_appointments' => false,
            'message' => 'Horario eliminado correctamente.'
        ]);
    }


    public function affectedAppointments(Request $request)
    {

        $id = $request->route('schedule');
        $scheduleQuery = ProfessionalSchedule::where('customer_id', app('customer')->id)
            ->where('id', $id);

        if ($request->routeIs('customers.my-schedules.affected-appointments')) {
            $scheduleQuery->where('user_id', $request->user()->id);
        }

        $schedule = $scheduleQuery->firstOrFail();

        $affectedAppointmentsCount = Appointment::where('customer_id', app('customer')->id)
        ->where('professional_id', $schedule->user_id)
        ->where('start_at', '>=', now())
        ->whereNotIn('status', [4, 5])
        ->whereRaw(
            'DAYOFWEEK(start_at) = ?',
            [
                $schedule->day_of_week == 0
                    ? 1
                    : $schedule->day_of_week + 1
            ]
        )
        ->whereTime('start_at', '>=', $schedule->start_time)
        ->whereTime('end_at', '<=', $schedule->end_time)
        ->count();
        

        return view('customer.professional.affected-appointments', [
            'schedule' => $schedule,
            'affectedAppointmentsCount' => $affectedAppointmentsCount,
            'selfService' => $request->routeIs('customers.my-schedules.affected-appointments'),
            'returnUrl' => $request->routeIs('customers.my-schedules.affected-appointments')
                ? route('customers.profile.index', ['customer' => $request->route('customer')])
                : null,
            'affectedAppointmentsDataUrl' => $request->routeIs('customers.my-schedules.affected-appointments')
                ? route('customers.my-schedules.affected-appointments.data', [
                    'customer' => $request->route('customer'),
                    'schedule' => $schedule->id,
                ])
                : url('/customer/schedules/' . $schedule->id . '/affected-appointments/data'),
        ]);
    }

    public function affectedAppointmentsData(Request $request)
    {
        $id = $request->route('schedule');

        $scheduleQuery = ProfessionalSchedule::where('customer_id', app('customer')->id)
            ->where('id', $id);

        if ($request->routeIs('customers.my-schedules.affected-appointments.data')) {
            $scheduleQuery->where('user_id', $request->user()->id);
        }

        $schedule = $scheduleQuery->firstOrFail();

        $query = Appointment::with([
            'patient:id,name,phone,email,rut',
            'specialty',
            'professional',
        ])
            ->where('customer_id', app('customer')->id)

            // Mismo profesional
            ->where('professional_id', $schedule->user_id)

            // Solo citas futuras
            ->where('start_at', '>=', now())

            // Excluir "No asistió" y "Cancelada"
            ->whereNotIn('status', [4, 5])

            // Mismo día de la semana
            ->whereRaw(
                'DAYOFWEEK(start_at) = ?',
                [
                    $schedule->day_of_week == 0
                        ? 1
                        : $schedule->day_of_week + 1
                ]
            )

            // La cita debe estar completamente dentro del horario
            ->whereTime('start_at', '>=', $schedule->start_time)
            ->whereTime('end_at', '<=', $schedule->end_time)

            ->orderBy('start_at');

        return DataTables::of($query)

            /*
        |--------------------------------------------------------------------------
        | PACIENTE
        |--------------------------------------------------------------------------
        */
            ->addColumn('patient', function ($appointment) {

                return $appointment->patient?->name ?? '-';
            })

            /*
        |--------------------------------------------------------------------------
        | RUT
        |--------------------------------------------------------------------------
        */
            ->addColumn('rut', function ($appointment) {

                return $appointment->patient?->rut ?? '-';
            })

       

            /*
        |--------------------------------------------------------------------------
        | ESPECIALIDAD
        |--------------------------------------------------------------------------
        */
            ->addColumn('specialty', function ($appointment) {

                return $appointment->specialty?->name ?? '-';
            })

            /*
        |--------------------------------------------------------------------------
        | FECHA
        |--------------------------------------------------------------------------
        */
            ->addColumn('date', function ($appointment) {

                return $appointment->start_at
                    ? \Carbon\Carbon::parse($appointment->start_at)->format('d/m/Y')
                    : '-';
            })

            /*
        |--------------------------------------------------------------------------
        | HORA
        |--------------------------------------------------------------------------
        */
            ->addColumn('time', function ($appointment) {

                return $appointment->start_at
                    ? \Carbon\Carbon::parse($appointment->start_at)->format('H:i')
                    : '-';
            })

            /*
        |--------------------------------------------------------------------------
        | ESTADO
        |--------------------------------------------------------------------------
        */
            ->addColumn('status', function ($appointment) {

                $statuses = [

                    0 => [
                        'label' => 'Agendado',
                        'color' => '#3B82F6',
                    ],

                    1 => [
                        'label' => 'Confirmada',
                        'color' => '#10B981',
                    ],

                    2 => [
                        'label' => 'En atención',
                        'color' => '#F59E0B',
                    ],

                    3 => [
                        'label' => 'Atendida',
                        'color' => '#6366F1',
                    ],

                    4 => [
                        'label' => 'No asistió',
                        'color' => '#EF4444',
                    ],

                    5 => [
                        'label' => 'Cancelada',
                        'color' => '#6B7280',
                    ],

                ];

                $status = $statuses[$appointment->status] ?? [
                    'label' => 'Desconocido',
                    'color' => '#6B7280',
                ];

                return '
                <span
                    style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 4px 10px;
                        border-radius: 9999px;
                        background-color: ' . $status['color'] . '20;
                        color: ' . $status['color'] . ';
                        font-size: 12px;
                        font-weight: 600;
                        white-space: nowrap;
                    "
                >

                    <span
                        style="
                            width: 6px;
                            height: 6px;
                            border-radius: 50%;
                            background-color: ' . $status['color'] . ';
                        "
                    ></span>

                    ' . e($status['label']) . '

                </span>
            ';
            })

            /*
        |--------------------------------------------------------------------------
        | ACCIONES
        |--------------------------------------------------------------------------
        */
            ->addColumn('actions', function ($appointment) {

                $id = $appointment->id;
                $patientId = $appointment->patient?->id;
                $patientName = $appointment->patient?->name ?? 'Sin nombre';
                $patientRut = $appointment->patient?->rut ?? 'Sin RUT';
                $patientPhone = $appointment->patient?->phone ?? 'Sin teléfono';
                $patientEmail = $appointment->patient?->email ?? 'Sin correo';

                return '
        <div
            x-data="{
                open: false,
                top: 0,
                left: 0,

                toggle(event) {

                    const button = event.currentTarget;
                    const rect = button.getBoundingClientRect();

                    this.open = !this.open;

                    if (!this.open) {
                        return;
                    }

                    this.$nextTick(() => {

                        const menu = this.$refs.menu;

                        if (!menu) {
                            return;
                        }

                        const menuHeight = menu.offsetHeight;
                        const menuWidth = menu.offsetWidth;

                        const spaceBelow =
                            window.innerHeight - rect.bottom;

                        const spaceAbove =
                            rect.top;

                        // Mostrar arriba si no hay espacio abajo
                        if (
                            spaceBelow < menuHeight + 10 &&
                            spaceAbove > menuHeight + 10
                        ) {

                            this.top =
                                rect.top - menuHeight - 8;

                        } else {

                            this.top =
                                rect.bottom + 8;

                        }

                        // Evitar salirse por la derecha
                        this.left = Math.min(
                            rect.right - menuWidth,
                            window.innerWidth - menuWidth - 10
                        );

                        // Evitar salirse por la izquierda
                        this.left = Math.max(
                            10,
                            this.left
                        );

                    });
                }
            }"
            class="relative inline-block"
        >

           
            <button
                type="button"
                @click="toggle($event)"
                class="px-2 py-1 rounded hover:bg-gray-100"
                title="Acciones"
            >

                <i class="fa-solid fa-ellipsis-vertical"></i>

            </button>


         
            <template x-teleport="body">

                <div
                    x-show="open"
                    x-ref="menu"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    x-transition
                    :style="`
                        position: fixed;
                        top: ${top}px;
                        left: ${left}px;
                    `"
                    class="
                        w-52
                        bg-white
                        border
                        rounded-lg
                        shadow-lg
                        z-[99999]
                        overflow-hidden
                    "
                    style="display: none;"
                >

                    
                    <button
                        type="button"
                        @click="open = false"
                        onclick="window.location.href=\'/patients/' . $patientId . '/medical-record\'"
                        class="
                            w-full
                            text-left
                            px-4
                            py-2
                            hover:bg-gray-100
                        "
                    >
                       <i class="fa-regular fa-id-badge mr-2"></i>
                        Ver ficha
                    </button>


                    
                    <button
                        type="button"
                        @click="open = false"
                        onclick=\'contactAppointment(' . json_encode([
                            'id' => $id,
                            'name' => $patientName,
                            'rut' => $patientRut,
                            'phone' => $patientPhone,
                            'email' => $patientEmail,
                        ]) . ')\'
                        class="
                            w-full
                            text-left
                            px-4
                            py-2
                            hover:bg-gray-100
                        "
                    >
                        <i class="fa-solid fa-phone mr-2"></i>
                        Contactar
                    </button>


                 
                    <button
                        type="button"
                        @click="open = false"
                        onclick="rescheduleAppointment(' . $id . ')"
                        class="
                            w-full
                            text-left
                            px-4
                            py-2
                            hover:bg-gray-100
                        "
                    >
                        <i class="fa-solid fa-calendar-days mr-2"></i>
                        Reagendar
                    </button>


                    <hr>


                  
                    <button
                        type="button"
                        @click="open = false"
                        onclick="cancelAppointment(' . $id . ')"
                        class="
                            w-full
                            text-left
                            px-4
                            py-2
                            text-red-600
                            hover:bg-red-50
                        "
                    >
                        <i class="fa-solid fa-ban mr-2"></i>
                        Cancelar
                    </button>

                </div>

            </template>

        </div>
        ';
                })

                /*
            |--------------------------------------------------------------------------
            | COLUMNAS HTML
            |--------------------------------------------------------------------------
            */
                ->rawColumns([
                    'contact',
                    'status',
                    'actions'
                ])

                ->make(true);
    }



}
