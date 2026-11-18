<?php
namespace App\Notifications;
use Illuminate\Notifications\{Notification,Messages\MailMessage};
class PaymentFeeReview extends Notification
{
    public function __construct(private string $message) {}
    public function via($notifiable): array { return ['mail']; }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('VITA: revisar comisiones y precios de planes')->line($this->message)
            ->line('Ingresa a SuperAdmin → Gestión comercial → Comisiones de pago. No se han modificado precios automáticamente.');
    }
}
