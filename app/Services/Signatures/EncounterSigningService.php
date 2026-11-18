<?php
namespace App\Services\Signatures;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\EncounterSignature;
use App\Models\EncounterSigningOperation;
use App\Models\Patient;
use App\Models\User;
use App\Support\PatientAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EncounterSigningService
{
    public const ACCEPTANCE_VERSION = 'vita-fes-consent-v1';
    public const ACCEPTANCE = 'He revisado el contenido completo y sus adjuntos. Confirmo su exactitud y acepto firmar electrónicamente esta atención mediante Firma Electrónica Simple (VITA).';

    public function __construct(private EncounterSigningPolicy $policy, private EncounterSnapshotBuilder $builder,
        private CanonicalContentSerializer $serializer, private SigningIdentityVerifier $identity, private SignedAttachmentStore $files) {}

    public function prepare(User $user, int $patientId, int $encounterId, array $data): EncounterSigningOperation
    {
        return DB::transaction(function () use ($user, $patientId, $encounterId, $data) {
            app(SignerIdentitySnapshot::class)->lockTenant($user);
            $encounter = $this->encounter($patientId, $encounterId);
            $this->policy->authorize($user->fresh(), $encounter);
            $this->open($encounter);
            $payload = $this->serializer->serialize($this->builder->build($encounter, $user->fresh(), $data));
            return app(SigningOperationManager::class)->prepare($user, $encounter, $payload);
        });
    }

    public function sign(User $user, int $patientId, int $encounterId, string $operationId, string $password, bool $accepted): EncounterSignature
    {
        app(SigningOperationManager::class)->requireAcceptance($accepted);
        // Verification is repeated inside the locked operation against the current user's hash.
        return DB::transaction(function () use ($user, $patientId, $encounterId, $operationId, $password) {
            app(SignerIdentitySnapshot::class)->lockTenant($user);
            $op = app(SigningOperationManager::class)->lock($user, $encounterId, $operationId, 'original');
            $encounter = $this->encounter($patientId, $encounterId);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->policy->authorize($user, $encounter);
            $this->open($encounter);
            $this->identity->verify($user, $password);
            $appointment = null;
            if ($encounter->appointment_id) {
                $appointment = Appointment::withoutGlobalScopes()->where('customer_id', $op->customer_id)
                    ->where('patient_id', $patientId)->lockForUpdate()->findOrFail($encounter->appointment_id);
                abort_unless((int) $appointment->professional_id === (int) $user->id
                    && (int) $appointment->clinic_branch_id === (int) $encounter->clinic_branch_id, 409);
                // Preserve existing behavior: the scheduler may mark an expired active visit as no-show (4).
                // Signing its real encounter still records attendance. An explicit cancellation (5) must win its race.
                if (!in_array((int) $appointment->status, [0, 1, 2, 3, 4], true)) $this->stale();
            }
            $snapshot = json_decode($op->payload, true, 512, JSON_THROW_ON_ERROR);
            // Lock identity rows used by the snapshot; document writers lock the encounter first.
            Patient::whereKey($patientId)->lockForUpdate()->firstOrFail();
            $payload = $this->serializer->serialize($this->builder->build($encounter, $user, $snapshot));
            app(SigningOperationManager::class)->assertContent($op, $payload);
            $manifest = $this->files->prepare($op, $encounter, $snapshot);
            $signature = app(SignedDocumentWriter::class)->persist($op, $encounter, $user, $snapshot, $payload, $manifest,
                'original', 1, self::ACCEPTANCE_VERSION, self::ACCEPTANCE);
            $at = $signature->signed_at;
            $encounter->update(['professional_id' => $user->id, 'clinical_assessment' => $snapshot['clinical_assessment'],
                'specialty_id' => $snapshot['specialty']['id'], 'treatment_plan' => $snapshot['treatment_plan'], 'status' => 2, 'ended_at' => $at]);
            if ($appointment) {
                $appointment->update(['status' => 3]);
            }
            PatientAuditLogger::record($encounter->patient, 'finished', 'Atención firmada FES', [], [
                'atención' => '#' . $encounter->id, 'firma' => '#' . $signature->id, 'SHA-256' => $op->content_hash,
            ]);
            // Files already exist and are verified before commit. Never delete in a commit-error handler.
            return $signature;
        });
    }

    private function encounter(int $patientId, int $encounterId): Encounter
    {
        return Encounter::where('customer_id', app('customer')->id)->where('patient_id', $patientId)
            ->lockForUpdate()->findOrFail($encounterId);
    }

    private function open(Encounter $encounter): void
    {
        if ((int) $encounter->status !== 1 || $encounter->signatures()->exists()) $this->stale();
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['signature' => 'La atención cambió, ya fue cerrada o la vista previa expiró. Revisa nuevamente el contenido antes de firmar.']);
    }
}
