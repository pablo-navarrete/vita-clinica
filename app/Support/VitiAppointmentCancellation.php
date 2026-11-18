<?php

namespace App\Support;

use App\Http\Controllers\customer\AppointmentController;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\InputBag;

class VitiAppointmentCancellation
{
    public function handle(Request $request, int $clinic, int $branch): ?array
    {
        $message = Str::ascii(mb_strtolower(trim($request->string('message')->toString())));
        $key = "viti-cancellation:{$request->user()->id}:{$clinic}:{$branch}";
        return Cache::lock($key . ':lock', 30)->block(5, function () use ($request, $clinic, $branch, $message, $key) {
            $state = Cache::get($key, []);
            $intent = (bool) preg_match('/\b(cancelar|cancela|anular|anula)\b.*\b(cita|reserva|hora)\b/', $message);
            if (!$state && !$intent) return null;
            if (in_array($message, ['cancelar', 'salir', 'no', 'no cancelar'])) {
                Cache::forget($key);
                return ['reply' => 'Dejé la cita sin cambios.'];
            }
            abort_unless(ClinicBranchContext::hasPermissionInBranch($request->user(), $clinic, $branch, 'appointments.cancel'), 403, 'Tu usuario no tiene permiso para cancelar citas.');
            $query = Appointment::where('customer_id', $clinic)->where('clinic_branch_id', $branch)->whereIn('status', [0, 1]);
            if ($request->user()->roles()->where('roles.name', 'Doctor')->exists()) $query->where('professional_id', $request->user()->id);
            if ($intent) {
                $state = [];
                Cache::forget("viti-booking:{$request->user()->id}:{$clinic}:{$branch}");
            }
            if ($document = app(\App\Services\Patients\PatientDocumentMention::class)->parse($request->string('message')->toString())) {
                $patient = app(\App\Services\Patients\PatientRegistrationService::class)->lookup($request->user(), $document);
                if (!$patient) return ['reply' => 'No se pudo resolver el paciente con ese documento.'];
                $state['patient_id'] = $patient->id;
            }
            if (isset($state['patient_id'])) $query->where('patient_id', $state['patient_id']);
            $date = app(VitiBookingConversation::class)->dateFromText($message);
            if ($date) $state['date'] = $date;
            if (isset($state['date'])) $query->whereDate('start_at', $state['date']);
            if (preg_match('/\bcita\s*#?\s*(\d+)\b/', $message, $id)) $state['selected'] = (int) $id[1];
            if (ctype_digit($message) && isset($state['options'][(int) $message - 1])) $state['selected'] = $state['options'][(int) $message - 1];
            if (isset($state['selected'])) {
                $appointment = (clone $query)->whereKey($state['selected'])->first();
                if (!$appointment) {
                    Cache::forget($key);
                    return ['reply' => 'La cita ya no está disponible para cancelar o no pertenece a tu agenda en esta sede.'];
                }
                if (in_array($message, ['confirmar', 'confirmar cancelacion', 'si, cancelar'])) {
                    $child = $request->duplicate([], ['clinic_branch_id' => $branch]);
                    $child->headers->set('Content-Type', 'application/json');
                    $child->headers->set('Accept', 'application/json');
                    $child->setJson(new InputBag(['clinic_branch_id' => $branch]));
                    $route = clone $request->route();
                    $route->setParameter('id', $appointment->id);
                    $child->setRouteResolver(fn () => $route);
                    $response = app(AppointmentController::class)->cancel($child);
                    if ($response->isSuccessful()) Cache::forget($key);
                    return ['reply' => $response->getData(true)['message']];
                }
                Cache::put($key, $state, now()->addMinutes(10));
                return ['reply' => "¿Confirmas cancelar esta cita?\n" . $this->label($appointment) . "\nEscribe confirmar cancelación para cancelarla, o no para dejarla sin cambios."];
            }
            if (!isset($state['patient_id']) && !isset($state['date'])) {
                Cache::put($key, $state + ['pending' => true], now()->addMinutes(10));
                return ['reply' => 'Indica el RUT del paciente o el número de cita que deseas cancelar (por ejemplo: cita #63).'];
            }
            $appointments = $query->with(['patient', 'professional'])->orderBy('start_at')->limit(20)->get();
            if ($appointments->isEmpty()) {
                Cache::forget($key);
                return ['reply' => 'No encontré citas pendientes o confirmadas para cancelar con esos datos en esta sede.'];
            }
            $state['options'] = $appointments->pluck('id')->all();
            Cache::put($key, $state, now()->addMinutes(10));
            return ['reply' => "¿Qué cita deseas cancelar? Indica el número de opción:\n" . $appointments->map(fn ($a, $i) => ($i + 1) . '. ' . $this->label($a))->implode("\n")];
        });
    }

    private function label(Appointment $appointment): string
    {
        $date = \Carbon\Carbon::parse($appointment->start_at)->locale('es')->translatedFormat('l d \\d\\e F H:i');
        return "Cita #{$appointment->id} · {$appointment->patient?->name} · {$appointment->professional?->name} · {$date}";
    }
}
