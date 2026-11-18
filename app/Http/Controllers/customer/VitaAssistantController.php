<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicBranch;
use App\Models\Encounter;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WebsiteFooter;
use App\Models\WebsiteNavigationButton;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VitaAssistantController extends Controller
{
    /** Answers public visitors using only information configured for this clinic. */
    public function askPublic(Request $request, string $customer): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
        ]);

        $clinic = app('customer');
        abort_unless($clinic && (string) $clinic->domain === $customer, 404);

        $message = Str::ascii(mb_strtolower(trim($validated['message']), 'UTF-8'));
        $customerId = (int) $clinic->id;
        $specialties = Specialty::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderBy('name')
            ->pluck('name');
        $footer = WebsiteFooter::query()->where('customer_id', $customerId)->first();
        $topbar = WebsiteTopbar::query()->where('customer_id', $customerId)->first();
        $navigation = WebsiteNavigationButton::query()->where('customer_id', $customerId)->first();
        $socialSettings = WebsiteSocialSetting::query()->where('customer_id', $customerId)->first();
        $publicMenus = Menu::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['name', 'url']);
        $bookingEnabled = (bool) ($navigation?->public_booking_enabled ?? false);

        $matchingSpecialty = $specialties->first(function (string $specialty) use ($message) {
            $terms = preg_split(
                '/[^a-z0-9]+/',
                Str::ascii(mb_strtolower($specialty, 'UTF-8')),
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

            return $terms !== [] && $this->hasAnyTerm($message, $terms);
        });

        if ($matchingSpecialty) {
            return response()->json([
                'reply' => "Sí, {$clinic->name} publica {$matchingSpecialty} entre sus especialidades.",
                'actions' => $this->publicQuickActions($customer, $bookingEnabled),
            ]);
        }

        $asksForServices = $this->hasAnyTerm($message, ['servicio', 'servicios', 'especialidad', 'especialidades', 'atienden', 'tratan', 'ofrecen', 'tratamiento', 'terapia']);
        $asksForContact = $this->hasAnyTerm($message, ['contacto', 'contactar', 'telefono', 'celular', 'correo', 'email', 'direccion', 'ubicacion', 'whatsapp', 'redes', 'queda', 'llegar', 'mapa']);
        $asksForBooking = $this->hasAnyTerm($message, ['cita', 'citas', 'agendar', 'agenda', 'reservar', 'reserva', 'hora', 'horario', 'disponible']);

        if ($this->hasAnyTerm($message, ['hola', 'buenas', 'buenos']) && ! $asksForServices && ! $asksForContact && ! $asksForBooking) {
            return response()->json([
                'reply' => "Hola, soy Viti, el asistente de {$clinic->name}. Puedo ayudarte a consultar servicios, datos de contacto y como solicitar una cita.",
                'actions' => $this->publicQuickActions($customer, $bookingEnabled),
            ]);
        }

        if ($asksForServices) {
            return response()->json([
                'reply' => $specialties->isNotEmpty()
                    ? 'Estos son los servicios y especialidades publicados por la clínica: ' . $specialties->implode(', ') . '.'
                    : 'La clínica aún no ha publicado sus especialidades. Puedes consultar directamente con el equipo en los datos de contacto.',
                'actions' => $this->publicQuickActions($customer, $bookingEnabled),
            ]);
        }

        if ($asksForContact) {
            $details = [];
            $phone = $topbar?->phone ?: $footer?->phone;
            $email = $topbar?->email ?: $footer?->email;
            if (filled($phone)) $details[] = 'Teléfono: ' . $phone;
            if (filled($email)) $details[] = 'Correo: ' . $email;
            if (filled($footer?->address)) $details[] = 'Dirección: ' . $footer->address;

            $socialLinks = collect($socialSettings?->social_links ?? [])
                ->filter(fn ($social) => is_array($social) && filled($social['url'] ?? null))
                ->map(fn ($social) => ucfirst((string) ($social['network'] ?? 'Red social')) . ': ' . $social['url'])
                ->values();
            $details = array_merge($details, $socialLinks->all());

            return response()->json([
                'reply' => $details
                    ? "Puedes contactar a {$clinic->name} por estos medios:\n" . implode("\n", $details)
                    : 'La clínica todavía no ha publicado datos de contacto en su web. Revisa sus páginas para encontrar más información.',
                'actions' => $this->publicQuickActions($customer, $bookingEnabled),
            ]);
        }

        if ($asksForBooking) {
            return response()->json([
                'reply' => $bookingEnabled
                    ? 'Puedes revisar los profesionales, horarios disponibles y solicitar tu cita desde el formulario de agendamiento. La reserva se confirma al completar el formulario.'
                    : 'El agendamiento en línea no está habilitado en este momento. Puedes contactar directamente a la clínica para consultar por una hora.',
                'actions' => $bookingEnabled
                    ? [['label' => 'Buscar horas y agendar', 'url' => route('customer.public-booking.index', ['customer' => $customer])]]
                    : $this->publicQuickActions($customer, false),
            ]);
        }

        $pageActions = $publicMenus
            ->filter(fn (Menu $menu) => filled($menu->url) && str_starts_with($menu->url, '/'))
            ->take(4)
            ->map(fn (Menu $menu) => ['label' => Str::title(mb_strtolower($menu->name)), 'url' => url($menu->url)])
            ->values()
            ->all();

        return response()->json([
            'reply' => ($specialties->isNotEmpty()
                    ? 'Los servicios y especialidades disponibles son: ' . $specialties->implode(', ') . '. '
                    : 'Puedo orientarte sobre los servicios de la clínica. ')
                . 'También puedo mostrarte los datos de contacto o ayudarte a solicitar una cita. ¿Qué necesitas encontrar?',
            'actions' => array_merge($pageActions, $this->publicQuickActions($customer, $bookingEnabled)),
        ]);
    }

    public function ask(Request $request, string $customer): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
        ]);

        $clinic = app('customer');
        abort_unless($clinic && (string) $clinic->domain === $customer, 404);

        $user = $request->user();
        $isSuperAdmin = $user->roles()->where('roles.name', 'Super Administrador')->exists();
        $isDoctor = $user->roles()->where('roles.name', 'Doctor')->exists();
        $isClinicAdmin = $user->roles()->where('roles.name', 'Administrador')->exists();
        abort_unless(
            $isSuperAdmin || ((int) $user->customer_id === (int) $clinic->id && ($isDoctor || $isClinicAdmin)),
            403,
            'No tienes acceso a la agenda de esta clínica.'
        );

        $activeBranchId = $this->activeBranchId((int) $clinic->id);
        abort_unless($activeBranchId !== null, 403, 'No hay una sede activa seleccionada para esta clínica.');
        $headquartersBranchId = ClinicBranch::query()
            ->where('customer_id', $clinic->id)
            ->orderBy('id')
            ->value('id');
        $canViewAllBranches = ($isSuperAdmin || $isClinicAdmin)
            && $headquartersBranchId !== null
            && $activeBranchId === (int) $headquartersBranchId;
        $branchId = $canViewAllBranches ? null : $activeBranchId;

        $message = Str::ascii(mb_strtolower(trim($validated['message']), 'UTF-8'));
        $asksIdentity = preg_match('/\b(quien soy|como me llamo|cual es mi nombre|sabes mi nombre|sabes quien soy|con quien (hablas|estas hablando)|con que usuario|que usuario soy|mi (rol|perfil))\b/', $message);
        if ($asksIdentity) {
            $activeBranch = ClinicBranch::where('customer_id', $clinic->id)->find($activeBranchId);
            $identityRoles = $isSuperAdmin
                ? collect(['Super Administrador'])
                : $user->clinicRolesForBranch((int) $clinic->id, $activeBranchId)->pluck('roles.name')->unique()->values();
            $identity = "Tu sesión corresponde a {$user->name}. Tu rol es " . $identityRoles->implode(', ')
                . ". Estás en {$clinic->name}" . ($activeBranch ? ", sede {$activeBranch->name}" : '') . '.';
            return response()->json(['reply' => $identity]);
        }
        if (preg_match('/^(hola|buenas|buenos dias|buenas tardes|buenas noches)([ ,.!]+viti)?[ ,.!?]*$/', $message)) {
            $activeBranch = ClinicBranch::where('customer_id', $clinic->id)->find($activeBranchId);
            return response()->json(['reply' => "Hola, {$user->name}. Soy Viti, tu asistente en {$clinic->name}. "
                . ($activeBranch ? "Tienes seleccionada la sede {$activeBranch->name}. " : '')
                . '¿En qué puedo ayudarte?']);
        }

        $cancellationReply = app(\App\Support\VitiAppointmentCancellation::class)->handle($request, (int) $clinic->id, $activeBranchId);
        if ($cancellationReply !== null) return response()->json($cancellationReply);
        $bookingReply = app(\App\Support\VitiBookingConversation::class)->handle($request, (int) $clinic->id, $activeBranchId);
        if ($bookingReply !== null) return response()->json($bookingReply);

        $query = Appointment::query()
            ->where('customer_id', $clinic->id)
            ->when($branchId, fn ($appointments) => $appointments->where('clinic_branch_id', $branchId))
            ->whereHas('patient', fn ($patients) => $patients->where('customer_id', $clinic->id))
            ->whereHas('professional', fn ($professionals) => $professionals->where('customer_id', $clinic->id))
            ->whereHas('specialty', fn ($specialties) => $specialties->where('customer_id', $clinic->id))
            ->with([
                'patient:id,name',
                'professional:id,name',
                'specialty:id,name',
            ]);

        if ($isDoctor) {
            $query->where('professional_id', $user->id);
        }

        $customerId = (int) $clinic->id;
        $timezone = config('app.timezone', 'America/Santiago');
        $asksSensitivePatientInfo = $this->hasAnyTerm($message, ['prevision', 'diagnostico', 'diagnostica', 'ficha', 'historial']);
        $asksPatientAppointment = $this->hasAnyTerm($message, ['paciente'])
            && $this->hasAnyTerm($message, ['cita', 'citas', 'atencion', 'control', 'ultima', 'ultimo', 'proxima', 'proximo']);
        $asksAppointmentQuestion = $this->hasAnyTerm($message, ['cita', 'citas', 'atencion', 'control']);
        if ($asksSensitivePatientInfo || $asksPatientAppointment || $asksAppointmentQuestion) {
            $patient = $this->findMentionedPatient($validated['message'], $customerId, $isDoctor ? (int) $user->id : null, $branchId);
            if ($patient) {
                return response()->json($this->patientSummary($patient, $message, $customerId, $isDoctor ? (int) $user->id : null, $customer, $branchId));
            }
            if ($asksSensitivePatientInfo || $asksPatientAppointment) {
                return response()->json(['reply' => 'Para consultar datos de un paciente, escribe su nombre completo o RUT. Solo puedo mostrar pacientes de esta clínica y, como profesional, pacientes asociados a tus atenciones.']);
            }
        }

        $asksPatientTotal = $this->hasAnyTerm($message, ['paciente', 'pacientes'])
            && $this->hasAnyTerm($message, ['total', 'totales', 'cuantos', 'cuantas', 'cantidad', 'numero']);
        if ($asksPatientTotal) {
            $patients = Patient::query()->where('customer_id', $customerId);
            if ($branchId) {
                $patients->where(function ($scope) use ($branchId) {
                    $scope->where('clinic_branch_id', $branchId)
                        ->orWhereHas('appointments', fn ($appointments) => $appointments->where('clinic_branch_id', $branchId));
                });
            }
            if ($isDoctor) {
                $patients->where(function ($scope) use ($user, $customerId) {
                    $scope->whereHas('appointments', fn ($appointments) => $appointments->where('customer_id', $customerId)->where('professional_id', $user->id))
                        ->orWhereHas('encounters', fn ($encounters) => $encounters->where('customer_id', $customerId)->where('professional_id', $user->id));
                });
            }

            return response()->json(['reply' => 'Hay ' . $patients->count() . ($isDoctor ? ' pacientes asociados a tus atenciones.' : ' pacientes registrados en la clínica.')]);
        }

        $asksPatientsByProfessional = $this->hasAnyTerm($message, ['paciente', 'pacientes'])
            && $this->hasAnyTerm($message, ['profesional', 'profesionales', 'doctor', 'doctores', 'medico', 'medicos']);
        if ($asksPatientsByProfessional) {
            $patientRows = (clone $query)->reorder()->get(['professional_id', 'patient_id'])
                ->unique(fn ($row) => $row->professional_id . ':' . $row->patient_id);
            $professionalIds = $patientRows->pluck('professional_id')->unique()->values();
            $professionals = User::query()->where('customer_id', $customerId)->whereIn('id', $professionalIds)->orderBy('name')->get(['id', 'name']);
            $counts = $patientRows->groupBy('professional_id')->map->count();

            return response()->json(['reply' => $professionals->isEmpty()
                ? 'No encuentro pacientes asociados a profesionales en esta clínica.'
                : "Pacientes por profesional:\n" . $professionals->map(fn (User $professional) => $professional->name . ': ' . $counts->get($professional->id, 0))->implode("\n")]);
        }

        if ($this->hasAnyTerm($message, ['disponible', 'disponibilidad', 'horario', 'horarios', 'hora', 'horas'])) {
            $professionals = $this->professionalsForAssistant($customerId, $isDoctor ? (int) $user->id : null, $message, $branchId);
            if ($professionals->isEmpty()) {
                return response()->json(['reply' => 'No encontré profesionales activos para consultar sus horarios.']);
            }
            $range = $this->availabilityRange($message, $timezone);
            $summaries = $professionals->map(fn (User $professional) => $this->availabilitySummary($customerId, $professional, $range['start'], $range['end'], $timezone));
            return response()->json(['reply' => $summaries->implode("\n\n")]);
        }

        $asksAppointmentCount = $this->hasAnyTerm($message, ['cita', 'citas', 'agenda', 'agendamiento'])
            && ($this->hasAnyTerm($message, ['total', 'totales', 'cuantos', 'cuantas', 'cantidad', 'numero', 'resumen'])
                || $this->hasAnyTerm($message, ['dia', 'hoy', 'semana', 'semanal', 'mes', 'mensual']));
        if ($asksAppointmentCount) {
            $today = Carbon::now($timezone);
            $total = (clone $query)->count();
            $todayCount = (clone $query)->whereDate('start_at', $today->toDateString())->count();
            $upcoming = (clone $query)->where('start_at', '>=', $today)->whereIn('status', [0, 1, 2])->count();
            $period = $this->availabilityRange($message, $timezone);
            $periodCount = (clone $query)->where('start_at', '>=', $period['start'])->where('start_at', '<', $period['end'])->count();
            $periodLabel = $period['start']->diffInDays($period['end']) <= 1
                ? 'hoy'
                : ($period['start']->diffInDays($period['end']) <= 7 ? 'esta semana' : 'este mes');
            return response()->json(['reply' => "Resumen de citas:\nRegistradas: {$total}\nPara hoy: {$todayCount}\nEn {$periodLabel}: {$periodCount}\nPróximas activas: {$upcoming}"]);
        }

        if ($this->hasAnyTerm($message, ['hoy'])) {
            $appointments = (clone $query)
                ->whereDate('start_at', today())
                ->whereIn('status', [0, 1, 2])
                ->orderBy('start_at')
                ->limit(10)
                ->get();

            return response()->json([
                'reply' => $appointments->isEmpty()
                    ? 'No hay citas agendadas para hoy en esta clínica.'
                    : "Citas de hoy:\n" . $appointments->map(fn (Appointment $appointment) => $this->formatAppointment($appointment))->implode("\n"),
            ]);
        }

        if ($this->hasAnyTerm($message, ['ultima', 'ultimo', 'reciente', 'atendida', 'atendido', 'realizada', 'realizado', 'anterior', 'pasada'])) {
            $appointment = (clone $query)
                ->where('status', 3)
                ->where('start_at', '<=', now())
                ->orderByDesc('start_at')
                ->first();

            return response()->json([
                'reply' => $appointment
                    ? 'La última cita atendida fue:\n' . $this->formatAppointment($appointment)
                    : 'No encuentro citas atendidas anteriores para esta clínica.',
            ]);
        }

        $asksForMoreThanOne = $this->hasAnyTerm($message, ['citas'])
            && $this->hasAnyTerm($message, ['proximas', 'siguientes', 'futuras', 'agenda']);
        if ($asksForMoreThanOne) {
            $appointments = (clone $query)
                ->where('start_at', '>=', now())
                ->whereIn('status', [0, 1, 2])
                ->orderBy('start_at')
                ->limit(5)
                ->get();

            return response()->json([
                'reply' => $appointments->isEmpty()
                    ? 'No hay próximas citas agendadas en esta clínica.'
                    : "Estas son las próximas citas:\n" . $appointments->map(fn (Appointment $appointment) => $this->formatAppointment($appointment))->implode("\n"),
            ]);
        }

        if ($this->hasAnyTerm($message, ['proxima', 'proximo', 'siguiente', 'sigue', 'viene', 'atendera'])) {
            $appointment = (clone $query)
                ->where('start_at', '>=', now())
                ->whereIn('status', [0, 1, 2])
                ->orderBy('start_at')
                ->first();

            return response()->json([
                'reply' => $appointment
                    ? 'La próxima cita es:\n' . $this->formatAppointment($appointment)
                    : 'No hay próximas citas agendadas en esta clínica.',
                'actions' => $appointment
                    ? $this->appointmentActions($appointment, $user, $customer)
                    : [],
            ]);
        }

        return response()->json([
            'reply' => 'Puedo consultar pacientes totales, pacientes por profesional, citas por día, semana o mes, horarios disponibles y datos de un paciente como su previsión, última cita y último diagnóstico. ¿Qué deseas revisar?',
        ]);
    }

    private function findMentionedPatient(string $message, int $customerId, ?int $professionalId, ?int $branchId): ?Patient
    {
        $patients = Patient::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where(function ($scope) use ($branchId) {
                $scope->where('clinic_branch_id', $branchId)
                    ->orWhereHas('appointments', fn ($appointments) => $appointments->where('clinic_branch_id', $branchId));
            }))
            ->when($professionalId, fn ($query) => $query->where(function ($scope) use ($professionalId, $customerId) {
                $scope->whereHas('appointments', fn ($appointments) => $appointments->where('customer_id', $customerId)->where('professional_id', $professionalId))
                    ->orWhereHas('encounters', fn ($encounters) => $encounters->where('customer_id', $customerId)->where(function ($assigned) use ($professionalId) {
                        $assigned->where('professional_id', $professionalId)
                            ->orWhere(function ($legacy) use ($professionalId) {
                                $legacy->whereNull('professional_id')->whereHas('appointment', fn ($appointment) => $appointment->where('professional_id', $professionalId));
                            });
                    }));
            }))
            ->with('insurance:id,name,customer_id')
            ->get(['id', 'customer_id', 'name', 'insurance_id']);

        if ($document = app(\App\Services\Patients\PatientDocumentMention::class)->parse($message)) {
            $resolved = app(\App\Services\Patients\PatientRegistrationService::class)->lookup(request()->user(), $document);
            return $resolved ? $patients->firstWhere('id', $resolved->id) : null;
        }
        $folded = Str::ascii(mb_strtolower($message, 'UTF-8'));
        return $patients->first(function (Patient $patient) use ($folded) {
            $name = Str::ascii(mb_strtolower(trim($patient->name), 'UTF-8'));
            return strlen($name) >= 5 && str_contains($folded, $name);
        });
    }

    private function patientSummary(Patient $patient, string $message, int $customerId, ?int $professionalId, string $customer, ?int $branchId): array
    {
        $asksInsurance = $this->hasAnyTerm($message, ['prevision', 'seguro', 'isapre', 'fonasa']);
        $asksDiagnosis = $this->hasAnyTerm($message, ['diagnostico', 'diagnostica', 'evaluacion', 'clinica', 'ficha', 'historial']);
        $asksAppointment = $this->hasAnyTerm($message, ['cita', 'citas', 'agenda', 'atencion', 'control', 'ultima', 'ultimo', 'proxima', 'proximo']);
        $lines = ['Paciente: ' . $patient->name];

        if ($asksInsurance) {
            $insurance = $patient->insurance;
            $insuranceName = $insurance && ($insurance->customer_id === null || (int) $insurance->customer_id === $customerId)
                ? $insurance->name
                : null;
            $lines[] = 'Previsión: ' . ($insuranceName ?? 'No registrada');
        }

        if ($asksAppointment) {
            $appointments = Appointment::query()->where('customer_id', $customerId)->where('patient_id', $patient->id)
                ->when($branchId, fn ($query) => $query->where('clinic_branch_id', $branchId))
                ->when($professionalId, fn ($query) => $query->where('professional_id', $professionalId))
                ->with(['professional:id,name', 'specialty:id,name']);
            $nextAppointment = (clone $appointments)->where('start_at', '>=', now())->whereIn('status', [0, 1, 2])->orderBy('start_at')->first();
            $lastAppointment = (clone $appointments)->where('start_at', '<', now())->orderByDesc('start_at')->first();
            $lines[] = 'Próxima cita: ' . ($nextAppointment ? $this->formatAppointment($nextAppointment) : 'No tiene citas futuras activas.');
            $lines[] = 'Última cita: ' . ($lastAppointment ? $this->formatAppointment($lastAppointment) : 'No hay citas anteriores registradas.');
        }

        if ($asksDiagnosis) {
            $encounter = Encounter::query()->where('customer_id', $customerId)->where('patient_id', $patient->id)
                ->whereNotNull('clinical_assessment')->where('clinical_assessment', '!=', '')
                ->when($branchId, fn ($query) => $query->where(function ($scope) use ($branchId) {
                    $scope->whereHas('appointment', fn ($appointment) => $appointment->where('clinic_branch_id', $branchId))
                        ->orWhere(function ($unlinked) use ($branchId) {
                            $unlinked->whereNull('appointment_id')
                                ->whereHas('patient', fn ($patientQuery) => $patientQuery->where('clinic_branch_id', $branchId));
                        });
                }))
                ->when($professionalId, fn ($query) => $query->where(function ($scope) use ($professionalId) {
                    $scope->where('professional_id', $professionalId)
                        ->orWhereHas('appointment', fn ($appointment) => $appointment->where('professional_id', $professionalId));
                }))
                ->with(['professional:id,name', 'specialty:id,name'])
                ->orderByDesc('started_at')->first();
            $lines[] = $encounter
                ? 'Última evaluación clínica (' . Carbon::parse($encounter->started_at)->timezone(config('app.timezone'))->format('d/m/Y') . '): ' . $encounter->clinical_assessment
                : 'No encuentro una evaluación clínica registrada para este paciente.';
        }

        return [
            'reply' => implode("\n", $lines),
            'actions' => [[
                'label' => 'Abrir ficha clínica',
                'url' => route('patients.medical-record.show', ['customer' => $customer, 'id' => $patient->id]),
            ]],
        ];
    }

    private function professionalsForAssistant(int $customerId, ?int $professionalId, string $message, ?int $branchId)
    {
        $professionals = User::query()->where('customer_id', $customerId)->where('status', 1)
            ->whereHas('roles', fn ($roles) => $roles->where('roles.name', 'Doctor'))
            ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branches) => $branches->whereKey($branchId)))
            ->when($professionalId, fn ($query) => $query->whereKey($professionalId))
            ->orderBy('name')->get(['id', 'name']);
        if ($professionalId) return $professionals;

        $matching = $professionals->filter(fn (User $professional) => str_contains(
            $message,
            Str::ascii(mb_strtolower(trim($professional->name), 'UTF-8'))
        ))->values();
        return $matching->isNotEmpty() ? $matching : $professionals;
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

    private function availabilityRange(string $message, string $timezone): array
    {
        $now = Carbon::now($timezone);
        if ($this->hasAnyTerm($message, ['mes', 'mensual', 'mensuales'])) {
            $start = $now->copy()->startOfMonth();
            return ['start' => $start, 'end' => $start->copy()->addMonth()];
        }
        if ($this->hasAnyTerm($message, ['semana', 'semanal', 'semanales'])) {
            $start = $now->copy()->startOfWeek(Carbon::MONDAY);
            return ['start' => $start, 'end' => $start->copy()->addWeek()];
        }

        $weekdays = ['domingo' => Carbon::SUNDAY, 'lunes' => Carbon::MONDAY, 'martes' => Carbon::TUESDAY, 'miercoles' => Carbon::WEDNESDAY, 'jueves' => Carbon::THURSDAY, 'viernes' => Carbon::FRIDAY, 'sabado' => Carbon::SATURDAY];
        foreach ($weekdays as $name => $weekday) {
            if (str_contains($message, $name)) {
                $date = $now->copy()->startOfDay();
                if ($date->dayOfWeek !== $weekday) $date->next($weekday);
                return ['start' => $date, 'end' => $date->copy()->addDay()];
            }
        }

        $date = $this->hasAnyTerm($message, ['manana']) ? $now->copy()->addDay()->startOfDay() : $now->copy()->startOfDay();
        return ['start' => $date, 'end' => $date->copy()->addDay()];
    }

    private function availabilitySummary(int $customerId, User $professional, Carbon $rangeStart, Carbon $rangeEnd, string $timezone): string
    {
        $schedules = ProfessionalSchedule::query()->where('customer_id', $customerId)->where('user_id', $professional->id)->get()->groupBy('day_of_week');
        $busyAppointments = Appointment::query()->where('customer_id', $customerId)->where('professional_id', $professional->id)
            ->whereIn('status', [0, 1, 2])->where('start_at', '<', $rangeEnd)->where('end_at', '>', $rangeStart)
            ->get(['start_at', 'end_at']);
        $now = Carbon::now($timezone);
        $days = [];

        for ($day = $rangeStart->copy()->startOfDay(); $day->lt($rangeEnd); $day->addDay()) {
            $slots = [];
            foreach ($schedules->get($day->dayOfWeek, collect()) as $schedule) {
                $slotStart = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->start_time)->format('H:i:s'), $timezone);
                $scheduleEnd = Carbon::parse($day->toDateString() . ' ' . Carbon::parse($schedule->end_time)->format('H:i:s'), $timezone);
                while ($slotStart->copy()->addMinutes(30)->lte($scheduleEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes(30);
                    $busy = $busyAppointments->contains(fn ($appointment) =>
                        $slotStart->lt(Carbon::parse($appointment->end_at, $timezone))
                        && $slotEnd->gt(Carbon::parse($appointment->start_at, $timezone))
                    );
                    if (!$busy && $slotStart->gt($now)) $slots[] = $slotStart->format('H:i');
                    $slotStart->addMinutes(30);
                }
            }
            $slots = array_values(array_unique($slots));
            sort($slots);
            if ($slots) $days[] = ['date' => $day->copy(), 'slots' => $slots];
        }

        $total = array_sum(array_map(fn ($day) => count($day['slots']), $days));
        $daysInRange = $rangeStart->diffInDays($rangeEnd);
        $period = $daysInRange <= 1
            ? 'el ' . $rangeStart->locale('es')->translatedFormat('l j \\d\\e F')
            : ($daysInRange <= 7 ? 'esta semana' : 'este mes');
        $lines = ["{$professional->name}: {$total} horario(s) disponible(s) {$period}."];
        foreach (array_slice($days, 0, 5) as $day) {
            $lines[] = $day['date']->locale('es')->translatedFormat('l j \\d\\e F') . ': ' . implode(', ', array_slice($day['slots'], 0, 8)) . (count($day['slots']) > 8 ? ' y más' : '');
        }
        if (count($days) > 5) $lines[] = 'Hay horarios en ' . count($days) . ' días del período.';
        return implode("\n", $lines);
    }

    private function formatAppointment(Appointment $appointment): string
    {
        $date = Carbon::parse($appointment->start_at)
            ->timezone(config('app.timezone'))
            ->locale('es');
        $dateLabel = ucfirst($date->translatedFormat('l, j \\d\\e F \\d\\e Y \\a \\l\\a\\s H:i'));
        $professional = $appointment->professional?->name ?? 'Profesional sin asignar';
        $patient = $appointment->patient?->name ?? 'Paciente';
        $specialty = $appointment->specialty?->name;
        $status = match ((int) $appointment->status) {
            0 => 'Agendada',
            1 => 'Confirmada',
            2 => 'En atención',
            3 => 'Atendida',
            4 => 'No asistió',
            5 => 'Cancelada',
            default => 'Estado pendiente',
        };

        return sprintf(
            "%s\nPaciente: %s\nProfesional: %s%s\nEstado: %s",
            $dateLabel,
            $patient,
            $professional,
            $specialty ? "\nEspecialidad: {$specialty}" : '',
            $status
        );
    }

    /** Match common Spanish intent words with accent-insensitive spelling and small typos. */
    private function hasAnyTerm(string $message, array $terms): bool
    {
        $words = preg_split('/[^a-z0-9]+/', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            foreach ($terms as $term) {
                if ($word === $term) {
                    return true;
                }

                $length = max(strlen($word), strlen($term));
                $allowedDistance = $length >= 10 ? 2 : 1;
                if ($length >= 5
                    && abs(strlen($word) - strlen($term)) <= $allowedDistance
                    && levenshtein($word, $term) <= $allowedDistance) {
                    return true;
                }
            }
        }

        return false;
    }

    private function publicQuickActions(string $customer, bool $bookingEnabled): array
    {
        $actions = [
            ['label' => 'Ver servicios', 'url' => url('/#servicios')],
            ['label' => 'Ver contacto', 'url' => url('/#contacto')],
        ];

        if ($bookingEnabled) {
            $actions[] = [
                'label' => 'Buscar horas y agendar',
                'url' => route('customer.public-booking.index', ['customer' => $customer]),
            ];
        }

        return $actions;
    }

    private function appointmentActions(Appointment $appointment, $user, string $customer): array
    {
        $isProfessional = $user->roles()->where('roles.name', 'Doctor')->exists();
        $canAttend = $isProfessional && (int) $appointment->professional_id === (int) $user->id;
        $startAt = Carbon::parse($appointment->start_at)->timezone(config('app.timezone'));

        $appointmentUrl = route('customers.appointments.index', [
            'customer' => $customer,
            'appointment_id' => $appointment->id,
            'professional_id' => $appointment->professional_id,
            'date' => $startAt->format('Y-m-d'),
        ]);

        $patientUrl = route('patients.medical-record.show', array_filter([
            'customer' => $customer,
            'id' => $appointment->patient_id,
            'start_encounter' => $canAttend ? $appointment->id : null,
        ], fn ($value) => $value !== null));

        return [
            ['label' => 'Ver en Agenda', 'url' => $appointmentUrl],
            ['label' => $canAttend ? 'Atender cita' : 'Ver ficha del paciente', 'url' => $patientUrl],
        ];
    }
}
