<?php

namespace App\Services\Patients;

use App\Mail\VerifyPatientRegistration;
use App\Support\ClinicActivityLogger;
use Illuminate\Support\Facades\{DB, Mail, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PublicPatientRegistration
{
    public const RECEIVED = 'Recibimos tu solicitud. Revisa tu correo para continuar. La cita todavía no está confirmada.';
    public const PROCESSED = 'La solicitud fue procesada. Si se completó la reserva, recibirás su confirmación por correo. Si necesitas ayuda, contacta a la clínica.';

    public function __construct(private PatientRegistrationService $registration, private PatientIdentityResolver $resolver) {}

    public function payload(object $attempt): array
    {
        return $this->resolver->crypto()->decrypt($attempt->encrypted_payload, $this->binding($attempt));
    }

    private function binding(object $attempt): array
    {
        return ['purpose' => 'patient-registration-attempt', 'operation' => $attempt->operation_id,
            'customer' => (int) $attempt->customer_id, 'branch' => (int) $attempt->clinic_branch_id, 'channel' => $attempt->channel];
    }

    public function submit(array $data, int $branch, ?string $operation = null): void
    {
        $this->prepare($data, $branch, $operation);
    }

    /** Only a redeemed server-side portal grant can substitute for an email challenge. */
    public function prepareFromPortal(string $intent, string $session, array $data, int $branch): array
    {
        abort_unless(DB::transactionLevel()>0,403);
        $payload=app(\App\Services\MiSalud\PortalBookingService::class)->grant($intent,$session,(int)app('customer')->id);
        $personal=array_filter($payload['personal'],fn($value)=>$value!==null && $value!=='');
        $data=array_replace($data,$personal);
        $operation=(string) Str::uuid();
        $token=$this->prepare($data,$branch,$operation,true);
        abort_unless($token,429);
        DB::table('portal_booking_authorizations')->where('id',$intent)->update(['registration_operation'=>$operation,'updated_at'=>now()]);
        return [$operation,$token];
    }

    private function prepare(array $data, int $branch, ?string $operation = null, bool $portal = false): ?string
    {
        $this->registration->enabled('public_booking');
        $document = $this->registration->document($data);
        $this->registration->fields($data, true);
        $operation ??= (string) Str::uuid();
        \Illuminate\Support\Facades\Validator::make(['operation' => $operation], ['operation' => 'required|uuid'])->validate();
        $crypto = $this->resolver->crypto();
        $customer = (int) app('customer')->id;
        $email = mb_strtolower(trim($data['email']));
        $data['email'] = $email;
        $requestHash = $crypto->hashes(['purpose' => 'public-registration-request', 'customer' => $customer, 'branch' => $branch, 'data' => $data]);
        // Limits apply before any document membership query, equally for new and existing patients.
        $key = 'public-registration:'.$customer.':'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 5)) return null;
        RateLimiter::hit($key, 3600);
        $token = bin2hex(random_bytes(32));
        $send = DB::transaction(function () use ($customer, $branch, $operation, $data, $crypto, $requestHash, $document, $token) {
            $this->registration->lockClinic($branch);
            if (DB::table('patient_registration_attempts')->where('operation_id', $operation)->lockForUpdate()->exists()) return false;
            $attempt = (object) ['operation_id' => $operation, 'customer_id' => $customer, 'clinic_branch_id' => $branch, 'channel' => 'public_booking'];
            DB::table('patient_registration_attempts')->insert((array) $attempt + [
                'status' => 'pending', 'encrypted_payload' => $crypto->encrypt($data, $this->binding($attempt)),
                'lookup_key_version' => $crypto->lookupVersion, 'document_hash' => $this->resolver->hashes($customer, $document)[$crypto->lookupVersion],
                'request_hash' => $requestHash[$crypto->lookupVersion], 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(config('patient_registration.verification_minutes')), 'created_at' => now(), 'updated_at' => now(),
            ]);
            return true;
        }, 3);
        if (!$send) return null;
        if ($portal) return $token;
        DB::afterCommit(function () use ($email, $operation, $token) {
        try {
            // Never send verification links to the log mailer or a failover that could log them.
            $transport = config('mail.mailers.'.config('mail.default').'.transport');
            if (!in_array($transport, ['smtp', 'ses', 'mailgun', 'postmark', 'sendmail'], true) && !app()->environment('testing')) throw new \RuntimeException('Delivery unavailable.');
            // Fragment is not sent to the web server or included in HTTP access-log URLs.
            $url = route('customer.public-booking.verify', ['customer' => app('customer')->domain, 'operation' => $operation]).'#'.$token;
            Mail::to($email)->send(new VerifyPatientRegistration($url));
        } catch (\Throwable) {
            DB::table('patient_registration_attempts')->where('operation_id', $operation)->where('status', 'pending')
                ->update(['status' => 'delivery_failed', 'token_hash' => null, 'updated_at' => now()]);
        }
        });
        return null;
    }

    /** Returns only the newly confirmed appointment to the trusted controller, never patient data. */
    public function complete(string $operation, string $token, callable $book): ?\App\Models\Appointment
    {
        $this->registration->enabled('public_booking');
        $candidate = DB::table('patient_registration_attempts')->where('customer_id', app('customer')->id)
            ->where('operation_id', $operation)->where('channel', 'public_booking')->first();
        if (!$candidate) return null;
        return DB::transaction(function () use ($candidate, $operation, $token, $book) {
            $this->registration->lockClinic((int) $candidate->clinic_branch_id);
            $attempt = DB::table('patient_registration_attempts')->where('operation_id', $operation)->lockForUpdate()->first();
            if ($attempt->status !== 'pending') return null;
            if (now()->gte($attempt->expires_at) || $attempt->verification_attempts >= 5) {
                DB::table('patient_registration_attempts')->where('operation_id', $operation)->update(['status' => 'expired', 'token_hash' => null, 'updated_at' => now()]);
                return null;
            }
            if (strlen($token) !== 64 || !hash_equals((string) $attempt->token_hash, hash('sha256', $token))) {
                DB::table('patient_registration_attempts')->where('operation_id', $operation)->increment('verification_attempts');
                return null;
            }
            $attempt->status = 'processing'; $attempt->contact_verified_at = now();
            DB::table('patient_registration_attempts')->where('operation_id', $operation)->update(['status' => 'processing',
                'contact_verified_at' => $attempt->contact_verified_at, 'token_hash' => null, 'updated_at' => now()]);
            $data = $this->payload($attempt);
            try {
                // Savepoint also rolls back patient creation if appointment creation fails.
                $appointment = DB::transaction(fn () => $book($attempt, $data));
                $state = $appointment ? 'completed' : 'review';
            } catch (ValidationException) { $appointment = null; $state = 'unavailable'; }
            DB::table('patient_registration_attempts')->where('operation_id', $operation)->update([
                'status' => $state, 'patient_id' => $appointment?->patient_id, 'appointment_id' => $appointment?->id, 'updated_at' => now(),
                'encrypted_payload' => $appointment ? $this->resolver->crypto()->encrypt(['email' => $data['email']], $this->binding($attempt)) : $attempt->encrypted_payload,
            ]);
            if (!$appointment) ClinicActivityLogger::recordManual((int) $attempt->customer_id, 'Pacientes', 'updated',
                'solicitud de registro', $operation, 'Solicitud pública pendiente de atención administrativa.', [], null, null);
            return $appointment;
        }, 3);
    }

    public function purgeExpired(): int
    {
        $bridge=app(\App\Services\MiSalud\PortalBookingService::class)->purgeExpired();
        if (!\Illuminate\Support\Facades\Schema::hasTable('patient_registration_attempts')) return $bridge;
        // Completed receipts establish trusted contacts and must never be purged here.
        return $bridge + DB::table('patient_registration_attempts')->where('channel', 'public_booking')->whereNull('patient_id')
            ->whereIn('status', ['pending', 'expired', 'delivery_failed', 'review', 'unavailable'])
            ->where('expires_at', '<', now()->subDays(config('patient_registration.pending_retention_days')))->delete();
    }
}
