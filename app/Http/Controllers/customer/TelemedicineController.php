<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\TelemedicineSession;
use App\Models\WebsiteTopbar;
use App\Support\ClinicAppointmentMessenger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class TelemedicineController extends Controller
{
    public function createWithoutAppointment(Request $request, $customer, $id)
    {
        $customerId = (int) app('customer')->id;
        $doctor = $request->user();
        $branch = \App\Support\ClinicBranchContext::defaultBranch($doctor, $customerId);
        abort_unless((int) $doctor->customer_id === $customerId && (int) $doctor->status === 1
            && $branch && $doctor->hasClinicRole('Doctor', $customerId, $branch->id), 403);
        $patient = \App\Models\Patient::where('customer_id', $customerId)->findOrFail($id);
        $data = $request->validate(['specialty_id' => ['required', 'integer']]);
        $specialty = $doctor->specialtiesForBranch($customerId, $branch->id)->where('specialties.status', 1)->findOrFail($data['specialty_id']);
        $session = \Illuminate\Support\Facades\DB::transaction(function () use ($patient, $doctor, $branch, $specialty, $customerId) {
            \App\Models\Patient::whereKey($patient->id)->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            $existing = TelemedicineSession::where('customer_id', $customerId)->whereNull('appointment_id')
                ->where('patient_id', $patient->id)->where('professional_id', $doctor->id)
                ->where('clinic_branch_id', $branch->id)->where('specialty_id', $specialty->id)
                ->whereIn('status', ['scheduled', 'in_progress'])->whereNull('ended_at')->where('expires_at', '>', now())
                ->whereDoesntHave('encounter')->first();
            return $existing ?? TelemedicineSession::create([
                'customer_id' => $customerId, 'appointment_id' => null, 'patient_id' => $patient->id,
                'professional_id' => $doctor->id, 'clinic_branch_id' => $branch->id, 'specialty_id' => $specialty->id,
                'room_name' => 'vita-' . $customerId . '-' . Str::random(32), 'created_by' => $doctor->id,
                'status' => 'scheduled', 'expires_at' => now()->addHours(4),
            ]);
        });
        return $this->inviteSession($request, $session, $this->sessionContext($session));
    }

    private function sessionContext(TelemedicineSession $session): Appointment
    {
        if ($session->appointment_id) {
            return $session->appointment()->with(['patient', 'professional', 'specialty'])->firstOrFail();
        }
        abort_unless($session->expires_at && $session->patient_id && $session->professional_id && $session->clinic_branch_id, 404);
        $session->loadMissing('patient', 'professional', 'specialty');
        abort_unless($session->patient && (int) $session->patient->customer_id === (int) $session->customer_id
            && $session->professional && (int) $session->professional->customer_id === (int) $session->customer_id, 404);
        // Transient presentation context for the existing room and mail templates. No appointment is persisted.
        $context = new Appointment([
            'customer_id' => $session->customer_id, 'patient_id' => $session->patient_id,
            'professional_id' => $session->professional_id, 'specialty_id' => $session->specialty_id,
            'clinic_branch_id' => $session->clinic_branch_id, 'start_at' => $session->created_at,
            'end_at' => $session->expires_at->copy()->subHours(2), 'status' => 0, 'modality' => 'telemedicine',
        ]);
        $context->setRelation('patient', $session->patient)->setRelation('professional', $session->professional)->setRelation('specialty', $session->specialty);
        return $context;
    }

    public function directStatus(Request $request, $customer, $id, $sessionId)
    {
        $session = TelemedicineSession::where('customer_id', (int) app('customer')->id)
            ->whereNull('appointment_id')->where('patient_id', $id)->findOrFail($sessionId);
        $this->authorizeStaff($request, $this->sessionContext($session));
        return response()->json(['ready' => $session->status === 'in_progress' && $session->patient_consent_at
            && !$session->ended_at && $session->expires_at?->isFuture()])->header('Cache-Control', 'private, no-store');
    }

    private const CONSENT_VERSION = 'telemedicine-consent-v1';
    private const CONSENT_TEXT = 'Confirmo que recibí información sobre esta atención por telemedicina y acepto continuar. Puedo solicitar orientación sobre una atención presencial.';

    public function create(Request $request, $customer, ?int $appointmentId = null)
    {
        $appointmentId ??= (int) $customer;
        $customerId = (int) app('customer')->id;
        $appointment = Appointment::where('customer_id', $customerId)->with(['patient', 'professional', 'specialty'])
            ->findOrFail($appointmentId);
        $this->authorizeStaff($request, $appointment);
        abort_unless(in_array((int) $appointment->status, [0, 1, 2], true), 422, 'La cita no está disponible para telemedicina.');

        $expiresAt = Carbon::parse($appointment->end_at, config('app.timezone'))->addHours(2);
        abort_if($expiresAt->isPast(), 422, 'El horario de esta cita ya finalizó.');

        $session = TelemedicineSession::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'customer_id' => $customerId,
                'room_name' => 'vita-' . $customerId . '-' . Str::random(32),
                'created_by' => $request->user()->id,
                'status' => 'scheduled',
            ]
        );

        abort_unless((int) $session->customer_id === $customerId, 404);
        abort_if($session->status === 'ended', 422, 'La teleconsulta ya fue finalizada.');

        return $this->inviteSession($request, $session, $appointment);
    }

    public function inviteForEncounter(Request $request, int $sessionId)
    {
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->authorizeStaff($request, $appointment);
        return $this->inviteSession($request, $session, $appointment);
    }

    private function inviteSession(Request $request, TelemedicineSession $session, Appointment $appointment)
    {
        $customerId = (int) app('customer')->id;
        $customerSlug = (string) $request->route('customer');
        $expiresAt = Carbon::parse($appointment->end_at, config('app.timezone'))->addHours(2);
        $patientUrl = URL::temporarySignedRoute('telemedicine.patient.room', $expiresAt, [
            'customer' => $customerSlug,
            'sessionId' => $session->id,
        ]);

        $patient = $appointment->patient;
        $patientEmail = trim((string) $patient?->email);
        $clinicName = app('customer')->name ?? config('app.name');
        $clinicSettings = WebsiteTopbar::query()->where('customer_id', $customerId)->first(['reply_email', 'reply_whatsapp', 'telemedicine_delivery']);
        $clinicReplyEmail = filled($clinicSettings?->reply_email) ? trim((string) $clinicSettings->reply_email) : null;
        $delivery = $clinicSettings?->telemedicine_delivery ?: 'email';
        $emailSent = (bool) $session->patient_invitation_sent_at;
        $emailAttempted = in_array($delivery, ['email', 'both'], true);
        if ($emailAttempted && $patientEmail !== '' && !$emailSent) {
            try {
                Mail::send('customer.telemedicine.invitation', [
                        'patient' => $patient,
                        'appointment' => $appointment,
                        'professional' => $appointment->professional,
                        'specialty' => $appointment->specialty,
                        'clinicName' => $clinicName,
                        'patientUrl' => $patientUrl,
                        'session' => $session,
                    ], function ($message) use ($patientEmail, $patient, $clinicReplyEmail) {
                        $message->to($patientEmail, $patient?->name)
                            ->subject('Enlace para tu teleconsulta');
                        if ($clinicReplyEmail) {
                            $message->replyTo($clinicReplyEmail);
                        }
                    });

                $session->forceFill(['patient_invitation_sent_at' => now()])->save();
                $emailSent = true;
            } catch (Throwable $exception) {
                Log::warning('No se pudo enviar la invitación de telemedicina.', [
                    'telemedicine_session_id' => $session->id,
                    'appointment_id' => $appointment->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        $whatsappSent = (bool) $session->patient_whatsapp_invitation_sent_at;
        if (in_array($delivery, ['whatsapp', 'both'], true) && !$whatsappSent) {
            $whatsappResult = app(ClinicAppointmentMessenger::class)->send($appointment, 'telemedicine', $patientUrl, false, true);
            $whatsappSent = $whatsappResult['whatsapp_sent'];
            if ($whatsappSent) {
                $session->forceFill(['patient_whatsapp_invitation_sent_at' => now()])->save();
            }
        }

        return response()->json([
            'room_url' => route('customers.telemedicine.room', ['customer' => $customerSlug, 'sessionId' => $session->id]),
            'session_id' => $session->id,
            'patient_url' => $patientUrl,
            'patient_name' => $appointment->patient?->name,
            'email_sent' => $emailSent,
            'email_missing' => $emailAttempted && $patientEmail === '',
            'clinic_email_missing' => !$clinicReplyEmail,
            'delivery' => $delivery,
            'whatsapp_sent' => $whatsappSent,
            'whatsapp_missing' => in_array($delivery, ['whatsapp', 'both'], true) && !$whatsappSent,
        ]);
    }

    public function staffRoom(Request $request, $customer, ?int $sessionId = null)
    {
        $sessionId ??= (int) $customer;
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->authorizeStaff($request, $appointment);
        $this->assertSessionJoinable($session, $appointment);

        return $this->roomView($request, $session, $appointment, false);
    }

    public function patientRoom(Request $request, $customer, ?int $sessionId = null)
    {
        $sessionId ??= (int) $customer;
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->assertSessionJoinable($session, $appointment);

        return $this->roomView($request, $session, $appointment, true);
    }

    public function staffToken(Request $request, $customer, ?int $sessionId = null)
    {
        $sessionId ??= (int) $customer;
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->authorizeStaff($request, $appointment);
        $this->assertSessionJoinable($session, $appointment);
        $this->assertWithinRoomWindow($appointment);

        return app(\App\Support\TelemedicineWebRtc::class)->handle($request, $session, false);
    }

    public function patientToken(Request $request, $customer, ?int $sessionId = null)
    {
        $sessionId ??= (int) $customer;
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->assertSessionJoinable($session, $appointment);
        $this->assertWithinRoomWindow($appointment);

        $request->validate(['consent' => ['accepted']]);
        if (!$session->patient_consent_at) {
            $session->forceFill([
                'patient_consent_at' => now(),
                'patient_consent_version' => self::CONSENT_VERSION,
                'patient_consent_text' => self::CONSENT_TEXT,
            ])->save();
        }

        return app(\App\Support\TelemedicineWebRtc::class)->handle($request, $session, true);
    }

    public function end(Request $request, $customer, ?int $sessionId = null)
    {
        $sessionId ??= (int) $customer;
        $session = $this->sessionForTenant($sessionId);
        $appointment = $this->sessionContext($session);
        $this->authorizeStaff($request, $appointment);

        broadcast(new \App\Events\TelemedicineSignal($session->room_name, 'staff', '', ['type' => 'ended']));
        $session->forceFill(['status' => 'ended', 'ended_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    private function roomView(Request $request, TelemedicineSession $session, Appointment $appointment, bool $isPatient)
    {
        $customer = (string) $request->route('customer');
        $expiresAt = Carbon::parse($appointment->end_at, config('app.timezone'))->addHours(2);
        $tokenUrl = $isPatient
            ? URL::temporarySignedRoute('telemedicine.patient.token', $expiresAt, ['customer' => $customer, 'sessionId' => $session->id])
            : route('customers.telemedicine.token', ['customer' => $customer, 'sessionId' => $session->id]);

        return view('customer.telemedicine.room', [
            'session' => $session,
            'appointment' => $appointment,
            'isPatient' => $isPatient,
            'tokenUrl' => $tokenUrl,
            'clinicalRecordUrl' => $isPatient ? null : route('patients.medical-record.show', [
                'customer' => $customer,
                'id' => $appointment->patient_id,
                'start_encounter' => $appointment->exists ? $appointment->id : null,
            ]),
            'joinAllowedAt' => Carbon::parse($appointment->start_at, config('app.timezone'))->subMinutes(30)->toIso8601String(),
            'joinExpiresAt' => Carbon::parse($appointment->end_at, config('app.timezone'))->addHours(2)->toIso8601String(),
            'telemedicineWindowOpen' => now(config('app.timezone'))->betweenIncluded(
                Carbon::parse($appointment->start_at, config('app.timezone'))->subMinutes(30),
                Carbon::parse($appointment->end_at, config('app.timezone'))->addHours(2)
            ),
            'endUrl' => $isPatient ? null : route('customers.telemedicine.end', ['customer' => $customer, 'sessionId' => $session->id]),
            'signalingConfigured' => config('broadcasting.default') === 'reverb'
                && filled(config('broadcasting.connections.reverb.key')) && filled(config('broadcasting.connections.reverb.secret')),
        ]);
    }

    private function authorizeStaff(Request $request, Appointment $appointment): void
    {
        $user = $request->user();
        abort_unless($user && (int) $user->status === 1, 403);
        if (!$appointment->exists) {
            $branch = \App\Support\ClinicBranchContext::defaultBranch($user, (int) $appointment->customer_id);
            abort_unless((int) $user->customer_id === (int) $appointment->customer_id
                && (int) $user->id === (int) $appointment->professional_id
                && $branch && (int) $branch->id === (int) $appointment->clinic_branch_id
                && $user->hasClinicRole('Doctor', (int) $appointment->customer_id, $branch->id), 403);
        }

        if ($user->roles()->where('roles.name', 'Doctor')->exists()) {
            abort_unless(
                (int) $user->customer_id === (int) $appointment->customer_id
                    && (int) $appointment->professional_id === (int) $user->id,
                403,
                'Solo el profesional asignado puede ingresar a esta teleconsulta.'
            );
            return;
        }

        abort_unless(
            $user->customer_id === null || (int) $user->customer_id === (int) $appointment->customer_id,
            403,
            'No tienes acceso a esta teleconsulta.'
        );
    }

    private function sessionForTenant(int $sessionId): TelemedicineSession
    {
        return TelemedicineSession::where('customer_id', (int) app('customer')->id)
            ->with('appointment')
            ->findOrFail($sessionId);
    }

    private function assertSessionJoinable(TelemedicineSession $session, Appointment $appointment): void
    {
        abort_if(!$session->appointment_id && (!$session->expires_at || $session->expires_at->isPast()), 410, 'El enlace de esta teleconsulta ha expirado.');
        abort_if($session->status === 'ended' || (int) $appointment->status === 5, 410, 'Esta teleconsulta ya no está disponible.');
    }

    private function assertWithinRoomWindow(Appointment $appointment): void
    {
        $timezone = config('app.timezone', 'America/Santiago');
        $start = Carbon::parse($appointment->start_at, $timezone)->subMinutes(30);
        $end = Carbon::parse($appointment->end_at, $timezone)->addHours(2);
        abort_unless(now($timezone)->betweenIncluded($start, $end), 403, 'La sala estará disponible desde 30 minutos antes de la cita.');
    }

}
