<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetLoginPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage())
            ->subject('Restablecer tu contraseña de VITA')
            ->greeting('Hola, ' . $notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace caduca en ' . config('auth.passwords.users.expire', 60) . ' minutos y solo puede utilizarse una vez.')
            ->line('Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña seguirá siendo la misma.')
            ->salutation('Equipo VITA');
    }
}
