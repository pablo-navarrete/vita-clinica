<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PortalSecurityLink extends Mailable
{
    public function __construct(public string $securityUrl, public string $purpose) {}
    public function build(): static
    {
        return $this->subject($this->purpose === 'verify' ? 'Verifica tu correo · Mi Salud VITA' : 'Recupera tu acceso · Mi Salud VITA')
            ->view('mi-salud.email');
    }
}
