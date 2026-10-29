<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ClinicAppointmentMessenger
{
    public function send(
        Appointment $appointment,
        string $event,
        ?string $actionUrl = null,
        bool $sendEmail = true,
        bool $sendWhatsApp = true
    ): array
    {
        $appointment->loadMissing(['patient', 'professional', 'specialty', 'clinicBranch']);
        $patient = $appointment->patient;
        $customerId = (int) $appointment->customer_id;
        $settings = \App\Models\WebsiteTopbar::query()->where('customer_id', $customerId)->first();
        $channels = $settings?->telemedicine_delivery ?: 'email';
        $customerSlug = (string) request()->route('customer');
        $confirmationUrl = $actionUrl ?: ($event === 'created'
            ? URL::temporarySignedRoute('customer.appointment.confirm.page', now()->addDays(2), [
                'customer' => $customerSlug,
                'appointmentId' => $appointment->id,
            ])
            : null);

        $emailSent = false;
        if ($sendEmail && in_array($channels, ['email', 'both'], true) && filled($patient?->email)) {
            try {
                Mail::send('customer.notifications.appointment', [
                    'appointment' => $appointment,
                    'patient' => $patient,
                    'clinicName' => app('customer')->name ?? config('app.name'),
                    'event' => $event,
                    'confirmationUrl' => $confirmationUrl,
                    'replyEmail' => $settings?->reply_email,
                ], function ($message) use ($patient, $settings, $event) {
                    $message->to($patient->email, $patient->name)
                        ->subject($this->subject($event));
                    if (filled($settings?->reply_email)) {
                        $message->replyTo($settings->reply_email);
                    }
                });
                $emailSent = true;
            } catch (Throwable $exception) {
                Log::warning('No se pudo enviar aviso de cita por correo.', [
                    'appointment_id' => $appointment->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        $whatsappSent = false;
        if ($sendWhatsApp && in_array($channels, ['whatsapp', 'both'], true)
            && $appointment->whatsapp_consent_at
            && filled($patient?->phone)) {
            $whatsappSent = $this->sendWhatsAppTemplate($appointment, $event, $confirmationUrl);
        }

        return [
            'email_sent' => $emailSent,
            'whatsapp_sent' => $whatsappSent,
            'confirmation_url' => $confirmationUrl,
        ];
    }

    private function sendWhatsAppTemplate(Appointment $appointment, string $event, ?string $confirmationUrl): bool
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.graph_version');
        $templateName = config('services.whatsapp.templates.' . $event);

        if (blank($token) || blank($phoneNumberId) || blank($version) || blank($templateName)) {
            Log::notice('WhatsApp no enviado: falta configurar WhatsApp Business Cloud API o la plantilla aprobada.', [
                'appointment_id' => $appointment->id,
                'event' => $event,
            ]);
            return false;
        }

        $phone = preg_replace('/\D+/', '', (string) $appointment->patient?->phone);
        if (strlen($phone) === 9 && str_starts_with($phone, '9')) {
            $phone = '56' . $phone;
        }

        $clinicName = app('customer')->name ?? config('app.name');
        $date = $appointment->start_at?->timezone(config('app.timezone', 'America/Santiago'))->format('d/m/Y H:i') ?? '';
        $parameters = match ($event) {
            'created' => [
                $appointment->patient?->name ?? 'Paciente',
                $clinicName,
                $date,
                $appointment->professional?->name ?? '',
                $confirmationUrl ?? '',
                $this->clinicWhatsApp($appointment->customer_id),
            ],
            'telemedicine' => [
                $appointment->patient?->name ?? 'Paciente',
                $clinicName,
                $date,
                $confirmationUrl ?? '',
                $this->clinicWhatsApp($appointment->customer_id),
            ],
            default => [
                $appointment->patient?->name ?? 'Paciente',
                $clinicName,
                $this->eventText($event),
                $date,
                $appointment->professional?->name ?? '',
                $this->clinicWhatsApp($appointment->customer_id),
            ],
        };

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(12)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => config('services.whatsapp.template_language', 'es')],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($text) => ['type' => 'text', 'text' => (string) $text], $parameters),
                        ]],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('WhatsApp rechazo el aviso de cita.', [
                    'appointment_id' => $appointment->id,
                    'event' => $event,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Fallo el envio de WhatsApp para una cita.', [
                'appointment_id' => $appointment->id,
                'event' => $event,
                'exception' => get_class($exception),
            ]);
            return false;
        }
    }

    private function subject(string $event): string
    {
        return match ($event) {
            'created' => 'Confirma tu cita en ' . (app('customer')->name ?? config('app.name')),
            'updated' => 'Actualizacion de tu cita',
            'cancelled' => 'Cancelacion de tu cita',
            'in_progress' => 'Inicio de tu atencion',
            'telemedicine' => 'Enlace para tu teleconsulta',
            default => 'Aviso de tu cita',
        };
    }

    private function eventText(string $event): string
    {
        return match ($event) {
            'updated' => 'actualizada',
            'cancelled' => 'cancelada',
            'confirmed' => 'confirmada',
            'in_progress' => 'en atencion',
            'attended' => 'atendida',
            default => 'actualizada',
        };
    }

    private function clinicWhatsApp(int $customerId): string
    {
        $phone = preg_replace('/\D+/', '', (string) \App\Models\WebsiteTopbar::query()
            ->where('customer_id', $customerId)
            ->value('reply_whatsapp'));

        return $phone ? '+' . $phone : 'No informado';
    }
}
