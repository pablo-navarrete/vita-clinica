<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Support\ClinicBranchContext;
use Yajra\DataTables\Facades\DataTables;


class ProfessionalScheduleController extends Controller
{
    public function indexMine(Request $request)
    {
        $schedules = $this->myScheduleData($request)->getData();
        $activeBranch = ClinicBranchContext::defaultBranch($request->user(), (int) app('customer')->id);
        $customerId = (int) app('customer')->id;
        $activeBranchId = $this->activeBranchId();
        $canManageSchedules = ClinicBranchContext::canManageSchedulesInBranch($request->user(), $customerId, $activeBranchId);
        $isHeadquarters = ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId);
        $branches = $isHeadquarters
            ? ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)
            : collect([$activeBranch]);
        $professional = $request->user()->only(['id', 'name']);
        return view('customer.user.schedules', compact('schedules', 'activeBranch', 'activeBranchId', 'canManageSchedules', 'isHeadquarters', 'branches', 'professional'));
    }

    public function getSchedule($customer, $professional)
    {
        $customerId = (int) app('customer')->id;
        $activeBranchId = $this->activeBranchId();
        $headquartersBranchId = (int) ClinicBranch::query()
            ->where('customer_id', $customerId)->where('status', true)->orderBy('id')->value('id');
        $allBranches = request()->boolean('all_branches')
            && ClinicBranchContext::canAdministerAllBranches(request()->user(), $customerId)
            && ClinicBranchContext::hasPermissionInBranch(request()->user(), $customerId, $activeBranchId, 'schedules.manage');
        $branchId = $allBranches ? null : $this->scheduleBranchId(request(), request()->query('branch_id', $activeBranchId));
        $professionalUser = User::query()->where('customer_id', $customerId)->whereKey($professional)->firstOrFail();
        $professionalBranchIds = $professionalUser->branches()
            ->where('clinic_branches.customer_id', $customerId)
            ->where('clinic_branches.status', true)
            ->pluck('clinic_branches.id')->map(fn ($id) => (int) $id)->all();
        $scheduleQuery = ProfessionalSchedule::query()
            ->join('clinic_branches', 'clinic_branches.id', '=', 'professional_schedules.clinic_branch_id')
            ->where('professional_schedules.customer_id', $customerId)
            ->where('professional_schedules.user_id', $professional)
            ->where('clinic_branches.customer_id', $customerId)
            ->where('clinic_branches.status', true)
            ->when($allBranches, fn ($query) => $query->whereIn('professional_schedules.clinic_branch_id', $professionalBranchIds))
            ->select('professional_schedules.*', 'clinic_branches.name as branch_name')
            ->when(!$allBranches, fn ($query) => $query->where('professional_schedules.clinic_branch_id', $branchId))
            ->orderBy('clinic_branches.id')
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
            ->orderBy('professional_schedules.start_time')
            ->get();

        $schedules = $scheduleQuery;

        $schedules->each(function ($schedule) use ($professional, $customerId) {

            $schedule->future_appointments_count = Appointment::where('customer_id', $customerId)
                ->where('clinic_branch_id', $schedule->clinic_branch_id)
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

        return response()->json($schedules)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /** Return only the schedules owned by the authenticated professional. */
    public function myScheduleData(Request $request)
    {
        $user = $request->user();
        $customerId = app('customer')->id;
        abort_unless((int) $user->customer_id === (int) $customerId && (int) $user->status === 1, 403);
        $branchId = $this->activeBranchId();

        $schedules = ProfessionalSchedule::where('customer_id', $customerId)
            ->where('clinic_branch_id', $branchId)
            ->where('user_id', $user->id)
            ->orderByRaw('CASE day_of_week WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 4 WHEN 5 THEN 5 WHEN 6 THEN 6 WHEN 0 THEN 7 END')
            ->orderBy('start_time')
            ->get();

        $schedules->each(function ($schedule) use ($user, $customerId, $branchId) {
            $schedule->future_appointments_count = Appointment::where('customer_id', $customerId)
                ->where('clinic_branch_id', $branchId)
                ->where('professional_id', $user->id)
                ->where('start_at', '>=', now())
                ->whereNotIn('status', [4, 5])
                ->whereRaw('DAYOFWEEK(start_at) = ?', [$schedule->day_of_week === 0 ? 1 : $schedule->day_of_week + 1])
                ->whereTime('start_at', '>=', $schedule->start_time)
                ->whereTime('end_at', '<=', $schedule->end_time)
                ->count();
        });

        return response()->json($schedules)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request)
    {
        $this->ensureScheduleAdministrator($request);

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
        $branchId = $this->scheduleBranchId($request, $request->input('clinic_branch_id'));


        $professional = User::where('customer_id', app('customer')->id)
            ->where('id', $professionalId)
            ->firstOrFail();

        if (!$professional->branches()->where('clinic_branches.id', $branchId)->where('clinic_branches.status', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El profesional no tiene asignada la sede seleccionada.',
            ], 422);
        }



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

                    'message' => 'El horario se cruza con otro horario del profesional en alguna de sus sedes.'

            ], 422);
        }



        $schedule = ProfessionalSchedule::create([

            'customer_id' => app('customer')->id,

            'clinic_branch_id' => $branchId,

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
        $this->ensureScheduleAdministrator($request);

        $id = $request->route('id');
        $branchId = $this->scheduleBranchId($request, $request->input('clinic_branch_id'));

        $schedule = ProfessionalSchedule::where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $branchId)
            ->where('id', $id)
            ->firstOrFail();

        $appointments = Appointment::where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $schedule->clinic_branch_id)
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

    public function update(Request $request)
    {
        $this->ensureScheduleAdministrator($request);
        $branchId = $this->scheduleBranchId($request, $request->input('clinic_branch_id'));
        $schedule = ProfessionalSchedule::query()
            ->where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $branchId)
            ->whereKey($request->route('id'))
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ], [
            'end_time.after' => 'La hora de término debe ser posterior a la hora de inicio.',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointments = Appointment::query()->where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $branchId)
            ->where('professional_id', $schedule->user_id)
            ->where('start_at', '>=', now())->whereNotIn('status', [4, 5])
            ->whereRaw('DAYOFWEEK(start_at) = ?', [(int) $schedule->day_of_week === 0 ? 1 : (int) $schedule->day_of_week + 1])
            ->whereTime('start_at', '>=', $schedule->start_time)
            ->whereTime('end_at', '<=', $schedule->end_time)->exists();
        if ($appointments) {
            return response()->json(['message' => 'No se puede modificar este horario porque tiene citas futuras. Gestiona primero las citas afectadas.'], 422);
        }

        $overlap = ProfessionalSchedule::query()->where('customer_id', app('customer')->id)
            ->where('user_id', $schedule->user_id)->where('day_of_week', $request->day_of_week)
            ->where('id', '!=', $schedule->id)
            ->where('start_time', '<', $request->end_time)
            ->where('end_time', '>', $request->start_time)->exists();
        if ($overlap) {
            return response()->json(['message' => 'El horario se cruza con otro horario del profesional en alguna de sus sedes.'], 422);
        }

        $schedule->update($request->only('day_of_week', 'start_time', 'end_time'));
        return response()->json(['success' => true, 'data' => $schedule, 'message' => 'Horario actualizado correctamente.']);
    }

    private function ensureScheduleAdministrator(Request $request): void
    {
        $customerId = (int) app('customer')->id;
        $branchId = $this->activeBranchId();
        $allowed = ClinicBranchContext::canManageSchedulesInBranch($request->user(), $customerId, $branchId);
        abort_unless($allowed, 403, 'No tienes permiso para administrar horarios en esta sede.');
    }


    public function affectedAppointments(Request $request)
    {

        $id = $request->route('schedule');
        $scheduleQuery = ProfessionalSchedule::where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $this->activeBranchId())
            ->where('id', $id);

        if ($request->routeIs('customers.my-schedules.affected-appointments')) {
            $scheduleQuery->where('user_id', $request->user()->id);
        }

        $schedule = $scheduleQuery->firstOrFail();

        $affectedAppointmentsCount = Appointment::where('customer_id', app('customer')->id)
        ->where('clinic_branch_id', $schedule->clinic_branch_id)
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
            ->where('clinic_branch_id', $this->activeBranchId())
            ->where('id', $id);

        if ($request->routeIs('customers.my-schedules.affected-appointments.data')) {
            $scheduleQuery->where('user_id', $request->user()->id);
        }

        $schedule = $scheduleQuery->firstOrFail();

        $query = Appointment::with([
            'patient:id,customer_id,clinic_branch_id,name,phone,email,rut',
            'specialty',
            'professional',
        ])
            ->where('customer_id', app('customer')->id)
            ->where('clinic_branch_id', $schedule->clinic_branch_id)

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
            ->editColumn('patient', fn ($row) => $row->patient ? app(\App\Services\Identification\PatientIdentification::class)->decorate($row->patient)->toArray() : null)

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

                return $appointment->patient?->identification_label ?? '-';
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
                $patientRut = $appointment->patient?->identification_label ?? 'Sin RUT';
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

    private function activeBranchId(): int
    {
        $customerId = (int) app('customer')->id;
        abort_unless((int) session('active_branch_customer_id') === $customerId, 422, 'Selecciona una sede activa.');

        $branchId = ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->whereKey((int) session('active_branch_id'))
            ->value('id');

        abort_unless($branchId, 422, 'La sede seleccionada no está disponible.');
        return (int) $branchId;
    }

    private function scheduleBranchId(Request $request, mixed $requestedBranchId = null): int
    {
        $customerId = (int) app('customer')->id;
        $activeBranchId = $this->activeBranchId();
        $headquartersBranchId = (int) ClinicBranch::query()
            ->where('customer_id', $customerId)->where('status', true)->orderBy('id')->value('id');
        $requestedBranchId = $requestedBranchId !== null ? (int) $requestedBranchId : $activeBranchId;
        $availableBranchIds = ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)
            ? ClinicBranchContext::centralAdministrationBranches($request->user(), $customerId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [$activeBranchId];

        abort_unless(in_array($requestedBranchId, $availableBranchIds, true), 403, 'No tienes acceso a la sede seleccionada.');
        if (!ClinicBranchContext::canAdministerAllBranches($request->user(), $customerId)) {
            abort_unless($requestedBranchId === $activeBranchId, 403, 'Desde esta sede solo puedes administrar sus propios horarios.');
        }
        abort_unless(ClinicBranchContext::hasPermissionInBranch($request->user(), $customerId, $activeBranchId, 'schedules.manage'), 403, 'No tienes permiso para administrar horarios.');

        return $requestedBranchId;
    }
}
