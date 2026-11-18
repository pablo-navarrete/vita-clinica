<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Sent synchronously: the bearer link must not be serialized into a database queue. */
class VerifyPatientRegistration extends Mailable
{
    public function __construct(public string $verificationUrl) {}
    public function build()
    {
        return $this->subject('Verifica tu correo para solicitar una cita')->view('emails.verify-patient-registration');
    }
}
