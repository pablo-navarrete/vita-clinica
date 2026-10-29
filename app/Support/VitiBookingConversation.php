<?php

namespace App\Support;

use App\Http\Controllers\customer\AppointmentController;
use App\Http\Controllers\customer\PatientController;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Server-owned drafts; no patient/appointment is written without a reviewed confirmation. */
class VitiBookingConversation
{
    public function handle(Request $request, int $customerId, int $branchId): ?array
    {
        $text = trim($request->string('message')->toString());
        $message = Str::ascii(mb_strtolower($text));
        $key = "viti-booking:{$request->user()->id}:{$customerId}:{$branchId}";
        return Cache::lock($key . ':lock', 30)->block(5, function () use ($request, $customerId, $branchId, $text, $message, $key) {
            $encrypted = Cache::get($key);
            $state = $encrypted ? json_decode(Crypt::decryptString($encrypted), true) : [];
            if (preg_match('/^(cancelar|cancela|salir|empezar de nuevo|reiniciar)( el agendamiento)?[.!]*$/', $message)) {
                Cache::forget($key);
                return ['reply' => 'Cancelé el agendamiento pendiente. No guardaré una cita sin tu confirmación.'];
            }
            $bookingIntent = (bool) preg_match('/\b(agenda(me|r|lo|la)?|agendame|agendar|reservar|reserva(me)?|crea(r)? (una )?cita)\b/', $message);
            $availabilityIntent = $this->asksForAvailability($message);
            if (!$state && !$bookingIntent && !$availabilityIntent) return null;
            $this->permission($request, $customerId, $branchId, 'appointments.create');
            $state = $state ?: ['mode' => $bookingIntent ? 'booking' : 'availability'];
            if ($bookingIntent) $state['mode'] = 'booking';
            $stage = $state['stage'] ?? '';
            if ($availabilityIntent && !$this->dateFromText($message)
                && $stage !== 'create_patient' && $stage !== 'reason' && !str_starts_with($stage, 'patient_')) {
                // A fresh availability question must not reuse a previously rejected date.
                unset($state['date'], $state['time'], $state['offered_slots']);
                $state['mode'] = 'availability';
                $state['stage'] = 'date';
            }
            try {
                $reply = $this->advance($request, $state, $text, $message, $customerId, $branchId);
            } catch (ValidationException $exception) {
                $reply = implode("\n", array_merge(...array_values($exception->errors())));
            }
            if (!empty($state['done'])) Cache::forget($key);
            else Cache::put($key, Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR)), now()->addMinutes(20));
            return ['reply' => $reply, 'actions' => !empty($state['done']) ? [[
                'label' => 'Ver agenda', 'url' => route('customers.appointments.index', ['customer' => $request->route('customer')]),
            ]] : []];
        });
    }

    private function advance(Request $request, array &$s, string $text, string $message, int $clinic, int $branch): string
    {
        $stage = $s['stage'] ?? null;
        $yes = (bool) preg_match('/^(si|si,? confirmo|confirmo|confirmar|confirmar cita|confirmar paciente|si agregar|agregar|si agregalo|si registralo|si por favor)[.!]*$/', $message);
        if ($stage === 'create_patient') {
            if (!$yes) return 'Este paciente no está registrado en esta clínica. ¿Quieres agregarlo? Responde sí o escribe cancelar.';
            $this->permission($request, $clinic, $branch, 'patients.create');
            $s['new_patient'] = ['rut' => $s['rut']];
            $s['stage'] = 'patient_name';
            return 'Indica el nombre completo del paciente.';
        }
        if (str_starts_with($stage ?? '', 'patient_')) {
            return $this->patientRegistration($request, $s, $text, $message, $yes, $clinic, $branch);
        }
        if ($stage === 'confirm') {
            if (!$yes) {
                if (preg_match('/^(no|corregir|cambiar)/', $message)) {
                    unset($s['time']); $s['stage'] = 'time';
                    return 'No he creado la cita. Indica otra fecha u hora, o escribe cancelar para empezar de nuevo.';
                }
                return $this->summary($s, $clinic, $branch) . "\nResponde confirmar para guardar, o cancelar.";
            }
            return $this->saveAppointment($request, $s, $clinic, $branch);
        }

        if ($date = $this->dateFromText($message)) {
            if (($s['date'] ?? null) !== $date) unset($s['time']);
            $s['date'] = $date;
        }
        if (preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', $message, $match)) $s['time'] = sprintf('%02d:%02d', $match[1], $match[2]);
        elseif (preg_match('/\ba las? ([01]?\d|2[0-3])\b/', $message, $match)) $s['time'] = sprintf('%02d:00', $match[1]);
        if (preg_match('/\b(?:\d{1,2}\.\d{3}\.\d{3}|\d{7,8})-?[\dkK]\b/', $text, $match)) {
            $normalized = $this->normalizeRut($match[0]);
            if (($s['rut'] ?? null) !== $normalized) unset($s['patient_id']);
            $s['rut'] = $normalized;
        }
        if (!empty($s['rut']) && empty($s['patient_id'])) {
            $this->permission($request, $clinic, $branch, 'patients.view');
            $patient = $this->patientByRut($clinic, $s['rut']);
            if (!$patient) {
                $s['stage'] = 'create_patient';
                return "No existe un paciente con RUT {$s['rut']} en esta clínica. ¿Quieres agregarlo? Responde sí o cancelar.";
            }
            $s['patient_id'] = $patient->id;
        }
        $professionals = User::where('customer_id', $clinic)->where('status', 1)
            ->whereHas('branches', fn ($q) => $q->where('clinic_branches.id', $branch)->where('clinic_branch_user.status', true))
            ->whereHas('branchRoles', fn ($q) => $q->where('roles.name', 'Doctor')->where('clinic_branch_user_role.clinic_branch_id', $branch))
            ->orderBy('name')->get();
        // Match the agenda controller: a doctor books their own schedule.
        $isDoctor = $request->user()->roles()->where('roles.name', 'Doctor')->exists();
        if ($isDoctor) $professionals = $professionals->where('id', $request->user()->id)->values();
        $availableSpecialties = $professionals->flatMap(fn ($p) => $p->specialtiesForBranch($clinic, $branch)
            ->where('specialties.status', 1)->get())->unique('id')->sortBy('name')->values();
        $namedSpecialties = $availableSpecialties->filter(fn ($item) => $this->mentionsName($message, $item->name));
        if ($namedSpecialties->count() === 1) $s['specialty_id'] = $namedSpecialties->first()->id;
        if (!$isDoctor) {
            if ($stage === 'booking_specialty' && ctype_digit($message) && $availableSpecialties->get((int) $message - 1)) {
                $s['specialty_id'] = $availableSpecialties->get((int) $message - 1)->id;
            }
            if ($availableSpecialties->count() === 1) $s['specialty_id'] = $availableSpecialties->first()->id;
            // Remember a named doctor even when the specialty still needs clarification.
            $namedDoctors = $professionals->filter(fn ($p) => $this->mentionsName($message, $p->name));
            if ($namedDoctors->count() === 1) $s['professional_id'] = $namedDoctors->first()->id;
            if (!$availableSpecialties->contains('id', $s['specialty_id'] ?? null)) {
                $s['stage'] = 'booking_specialty';
                return $availableSpecialties->isEmpty() ? 'No hay especialidades activas con médicos disponibles en esta sede.'
                    : "¿Qué especialidad necesitas? Puedes indicar su nombre o número:\n" . $availableSpecialties->map(fn ($item, $i) => ($i + 1) . '. ' . $item->name)->implode("\n");
            }
            $professionals = $professionals->filter(fn ($p) => $p->specialtiesForBranch($clinic, $branch)
                ->where('specialties.status', 1)->where('specialties.id', $s['specialty_id'])->exists())->values();
            if (!$professionals->contains('id', $s['professional_id'] ?? null)) unset($s['professional_id']);
        }
        if ($stage === 'professional' && ctype_digit($message)) {
            $selected = $professionals->get((int) $message - 1);
            if ($selected) $s['professional_id'] = $selected->id;
        }
        $matching = $professionals->filter(function ($professional) use ($message) {
            $name = Str::ascii(mb_strtolower($professional->name));
            return str_contains($message, $name) || collect(explode(' ', $name))->filter(fn ($part) => strlen($part) >= 4 && preg_match('/\b' . preg_quote($part, '/') . '\b/', $message))->isNotEmpty();
        });
        if ($matching->count() === 1 && !isset($s['professional_id'])) $s['professional_id'] = $matching->first()->id;
        if ($professionals->count() === 1) $s['professional_id'] = $professionals->first()->id;
        $professional = $professionals->firstWhere('id', $s['professional_id'] ?? null);
        if (!$professional) {
            unset($s['professional_id'], $s['specialty_id']);
            $s['stage'] = 'professional';
            return $professionals->isEmpty() ? 'No hay profesionales activos asignados a esta sede.' : "¿Con qué profesional? Responde con su número:\n" . $professionals->values()->map(fn ($p, $i) => ($i + 1) . '. ' . $p->name)->implode("\n");
        }
        if (empty($s['date'])) {
            if (($s['mode'] ?? null) === 'availability') {
                $firstDay = Carbon::now(config('app.timezone', 'America/Santiago'))->startOfDay();
                for ($offset = 0; $offset < 30; $offset++) {
                    $candidate = $firstDay->copy()->addDays($offset)->toDateString();
                    if ($this->slots($request, array_merge($s, ['date' => $candidate]), $branch)) {
                        $s['date'] = $candidate;
                        break;
                    }
                }
                if (empty($s['date'])) {
                    $s['stage'] = 'date';
                    return "No encontré horas disponibles para {$professional->name} en los próximos 30 días en esta sede. Puedes indicar otra fecha para consultar.";
                }
            }
        }
        if (empty($s['date'])) {
            $s['stage'] = 'date';
            return '¿Para qué día? Puedes decir próximo lunes, mañana o una fecha como 02/11/2026.';
        }
        $slots = $this->slots($request, $s, $branch);
        if (!$slots) {
            unset($s['time']); $s['stage'] = 'date';
            return "No hay horas disponibles para {$professional->name} el {$this->displayDate($s['date'])} en esta sede. ¿Qué otra fecha quieres consultar?";
        }
        $slotChoice = $this->slotChoice($message);
        if ($stage === 'time' && $slotChoice !== null && isset(($s['offered_slots'] ?? [])[$slotChoice - 1])) {
            $s['time'] = $s['offered_slots'][$slotChoice - 1]; $s['mode'] = 'booking';
        }
        if (empty($s['time']) || !in_array($s['time'], array_column($slots, 'value'), true)) {
            $unavailable = !empty($s['time']); unset($s['time']);
            $s['offered_slots'] = array_column($slots, 'value'); $s['stage'] = 'time';
            return ($unavailable ? "Esa hora no está disponible.\n" : '') . "Horas disponibles para {$professional->name} el {$this->displayDate($s['date'])} (30 minutos):\n"
                . collect($slots)->map(fn ($slot, $i) => ($i + 1) . '. ' . $slot['label'])->implode("\n")
                . "\nIndica la hora o el número. Para reservar, dime también el RUT del paciente.";
        }
        $s['mode'] = 'booking';
        if (empty($s['patient_id'])) { $s['stage'] = 'rut'; return '¿Cuál es el RUT del paciente? Puedes escribirlo con puntos y guion.'; }
        $specialties = $professional->specialtiesForBranch($clinic, $branch)->where('specialties.status', 1)->orderBy('specialties.name')->get();
        if ($stage === 'specialty' && ctype_digit($message) && $specialties->get((int) $message - 1)) $s['specialty_id'] = $specialties->get((int) $message - 1)->id;
        if ($specialties->count() === 1) $s['specialty_id'] = $specialties->first()->id;
        if (!$specialties->contains('id', $s['specialty_id'] ?? null)) {
            $s['stage'] = 'specialty';
            return $specialties->isEmpty() ? 'Este profesional no tiene especialidades activas asignadas a la sede.' : "Selecciona la especialidad por número:\n" . $specialties->values()->map(fn ($item, $i) => ($i + 1) . '. ' . $item->name)->implode("\n");
        }
        if (str_contains($message, 'telemedicina') || str_contains($message, 'video')) $s['modality'] = 'telemedicine';
        elseif (str_contains($message, 'presencial')) $s['modality'] = 'in_person';
        if (empty($s['modality'])) { $s['stage'] = 'modality'; return '¿La cita será presencial o por telemedicina?'; }
        if ($stage === 'reason') $s['reason'] = in_array($message, ['omitir', 'sin motivo']) ? '' : $text;
        if (!array_key_exists('reason', $s)) { $s['stage'] = 'reason'; return '¿Cuál es el motivo de la cita? Puedes escribir omitir si no deseas indicarlo.'; }
        $s['stage'] = 'confirm';
        return $this->summary($s, $clinic, $branch) . "\nResponde confirmar para guardar la cita, o cancelar. Aún no está reservada.";
    }

    private function slots(Request $request, array $s, int $branch): array
    {
        $child = $this->operationRequest($request, ['professional_id' => $s['professional_id'], 'date' => $s['date'], 'clinic_branch_id' => $branch]);
        return app(AppointmentController::class)->availability($child)->getData(true);
    }

    private function slotChoice(string $message): ?int
    {
        // Remove the patient's RUT before interpreting an option number.
        $choice = preg_replace('/\b(?:\d{1,2}\.\d{3}\.\d{3}|\d{7,8})-?[\dkK]\b/', '', $message);
        if (preg_match('/^\s*(?:(?:opcion|numero|la|el)\s+)?([1-9]\d*)\s*(?:(?:con\s+)?(?:el\s+)?rut\s*:?)?\s*$/', $choice, $match)) {
            return (int) $match[1];
        }
        return null;
    }

    private function asksForAvailability(string $message): bool
    {
        $message = Str::ascii(mb_strtolower($message));
        return (bool) preg_match('/\b(horarios?|disponibilidad|disponibles?|cupos?|horas? libres|fechas? libres)\b'
            . '|\b(dame|deme|dime|muestrame|busca|buscame|necesito|quiero|quisiera|tienes|tiene|hay)\b.{0,45}\b(una? |otra? |la |las |proxima? )?(horas?|fechas?|turnos?)\b'
            . '|\b(cuando|que dia|a que hora)\b.{0,45}\b(puedo|puede|podria|agendar|reservar|atender|atencion|cita)\b/u', $message);
    }

    private function mentionsName(string $message, string $name): bool
    {
        $name = Str::ascii(mb_strtolower(trim($name)));
        return $name !== '' && (bool) preg_match('/(?<![a-z0-9])' . preg_quote($name, '/') . '(?![a-z0-9])/', $message);
    }

    private function saveAppointment(Request $request, array &$s, int $clinic, int $branch): string
    {
        $this->permission($request, $clinic, $branch, 'appointments.create');
        if (!in_array($s['time'], array_column($this->slots($request, $s, $branch), 'value'), true)) {
            unset($s['time']); $s['stage'] = 'time';
            return 'Esa hora dejó de estar disponible. No creé la cita. Indica otra hora o fecha para volver a revisar.';
        }
        Patient::where('customer_id', $clinic)->findOrFail($s['patient_id']);
        $child = $this->operationRequest($request, array_merge(collect($s)->only(['patient_id', 'professional_id', 'specialty_id', 'date', 'time', 'modality', 'reason'])->all(), ['clinic_branch_id' => $branch]));
        $response = app(AppointmentController::class)->store($child);
        $result = $response->getData(true);
        if ($response->getStatusCode() >= 400 || empty($result['success'])) return $result['message'] ?? 'No pude crear la cita. Revisa los datos y confirma nuevamente.';
        $s['done'] = true;
        return "Cita agendada correctamente.\n" . $this->summary($s, $clinic, $branch);
    }

    private function patientRegistration(Request $request, array &$s, string $text, string $message, bool $yes, int $clinic, int $branch): string
    {
        $this->permission($request, $clinic, $branch, 'patients.create');
        $fields = ['name' => 'Nombre completo', 'birth_date' => 'Fecha de nacimiento (DD/MM/AAAA)', 'gender' => 'Sexo (Masculino o Femenino)', 'phone' => 'Teléfono', 'email' => 'Correo electrónico', 'address' => 'Dirección'];
        if ($s['stage'] === 'patient_confirm') {
            if (!$yes) return 'Responde confirmar paciente para registrarlo con los datos mostrados, o cancelar.';
            $existing = $this->patientByRut($clinic, $s['rut']);
            if ($existing) $s['patient_id'] = $existing->id;
            else {
                $child = $this->operationRequest($request, $s['new_patient']);
                $response = DB::transaction(fn () => app(PatientController::class)->store($child));
                $data = $response->getData(true);
                if ($response->getStatusCode() >= 400) return implode("\n", array_merge(...array_values($data['errors'] ?? [['No pude registrar al paciente.']])));
                $s['patient_id'] = $data['data']['id'];
            }
            unset($s['new_patient']); $s['stage'] = 'resume';
            return 'Paciente registrado. ' . $this->advance($request, $s, '', '', $clinic, $branch);
        }
        $field = substr($s['stage'], strlen('patient_'));
        $value = $text;
        if ($field === 'birth_date') {
            try {
                $date = Carbon::createFromFormat('!d/m/Y', $text);
                if ($date->format('d/m/Y') !== $text) return 'Indica una fecha de nacimiento válida con formato DD/MM/AAAA.';
                $value = $date->format('Y-m-d');
            } catch (\Throwable $e) { return 'Indica una fecha de nacimiento válida con formato DD/MM/AAAA.'; }
        }
        if ($field === 'gender') $value = match ($message) { 'masculino', 'hombre', 'm' => 'Masculino', 'femenino', 'mujer', 'f' => 'Femenino', default => '' };
        $rules = match ($field) {
            'birth_date' => 'required|date|before_or_equal:today', 'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20', 'gender' => 'required|in:Masculino,Femenino', default => 'required|string|max:255',
        };
        $validation = Validator::make([$field => $value], [$field => $rules]);
        if ($validation->fails()) return 'Revisa el dato. ' . $fields[$field] . ': ' . $validation->errors()->first();
        $s['new_patient'][$field] = $value;
        foreach ($fields as $next => $label) {
            if (!isset($s['new_patient'][$next])) { $s['stage'] = 'patient_' . $next; return $label . ' del paciente:'; }
        }
        $s['stage'] = 'patient_confirm';
        return "Revisa el registro del paciente:\nRUT: {$s['rut']}\n" . collect($fields)->map(fn ($label, $key) => $label . ': ' . $s['new_patient'][$key])->implode("\n") . "\nResponde confirmar paciente para guardarlo, o cancelar. La cita todavía no se creará.";
    }

    private function displayDate(string $date): string
    {
        return Carbon::parse($date, config('app.timezone', 'America/Santiago'))
            ->locale('es')->translatedFormat('l d \\d\\e F');
    }

    private function summary(array $s, int $clinic, int $branch): string
    {
        $patient = Patient::where('customer_id', $clinic)->findOrFail($s['patient_id']);
        $professional = User::where('customer_id', $clinic)->findOrFail($s['professional_id']);
        $branchName = \App\Models\ClinicBranch::where('customer_id', $clinic)->findOrFail($branch)->name;
        $specialty = \App\Models\Specialty::where('customer_id', $clinic)->findOrFail($s['specialty_id'])->name;
        return "Paciente: {$patient->name} · RUT {$patient->rut}\nProfesional: {$professional->name}\nEspecialidad: {$specialty}\nSede: {$branchName}\nFecha: {$this->displayDate($s['date'])} · {$s['time']} (30 minutos)\nModalidad: " . ($s['modality'] === 'telemedicine' ? 'Telemedicina' : 'Presencial') . "\nMotivo: " . ($s['reason'] ?: 'Sin motivo indicado');
    }

    private function permission(Request $request, int $clinic, int $branch, string $permission): void
    {
        abort_unless(ClinicBranchContext::hasPermissionInBranch($request->user(), $clinic, $branch, $permission), 403, 'Tu usuario no tiene permiso para esta operación.');
    }

    private function operationRequest(Request $request, array $data): Request
    {
        $child = $request->duplicate([], $data);
        $child->headers->set('Content-Type', 'application/json');
        $child->headers->set('Accept', 'application/json');
        $child->setJson(new \Symfony\Component\HttpFoundation\InputBag($data));
        return $child;
    }

    private function normalizeRut(string $rut): string
    {
        $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
        return substr($rut, 0, -1) . '-' . substr($rut, -1);
    }

    private function patientByRut(int $clinic, string $rut): ?Patient
    {
        return Patient::where('customer_id', $clinic)->whereRaw("UPPER(REPLACE(REPLACE(rut, '.', ''), '-', '')) = ?", [str_replace('-', '', $rut)])->first();
    }

    public function dateFromText(string $message): ?string
    {
        $now = Carbon::now(config('app.timezone', 'America/Santiago'))->startOfDay();
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $message, $match)) {
            try { $date = Carbon::createFromFormat('!Y-m-d', $match[1]); if ($date->format('Y-m-d') === $match[1]) return $date->toDateString(); } catch (\Throwable $e) {}
        }
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?\b/', $message, $m)) {
            try {
                $raw = sprintf('%02d/%02d/%04d', $m[1], $m[2], $m[3] ?? $now->year);
                $date = Carbon::createFromFormat('!d/m/Y', $raw);
                if ($date->format('d/m/Y') !== $raw) return null;
                if (!isset($m[3]) && $date->lt($now)) $date->addYear();
                return $date->toDateString();
            } catch (\Throwable $e) { return null; }
        }
        if (preg_match('/\bmanana\b/', $message)) return $now->addDay()->toDateString();
        if (preg_match('/\bhoy\b/', $message)) return $now->toDateString();
        foreach (['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'] as $day => $name) {
            if (preg_match('/\b' . $name . '\b/', $message)) return $now->next($day)->toDateString();
        }
        return null;
    }
}
