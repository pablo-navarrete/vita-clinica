<?php
namespace App\Services\Signatures;

use App\Models\{Encounter, EncounterSigningOperation, User};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SigningOperationManager
{
    public function prepare(User $user, Encounter $encounter, string $payload, string $purpose = 'original', ?int $parentId = null): EncounterSigningOperation
    {
        return EncounterSigningOperation::create([
            'id' => (string) Str::uuid(), 'customer_id' => $encounter->customer_id, 'encounter_id' => $encounter->id,
            'user_id' => $user->id, 'content_hash' => hash('sha256', $payload), 'payload' => $payload,
            'purpose' => $purpose, 'parent_version_id' => $parentId, 'expires_at' => now()->addMinutes(10), 'status' => 'prepared',
        ]);
    }

    public function lock(User $user, int $encounterId, string $operationId, string $purpose): EncounterSigningOperation
    {
        $op = EncounterSigningOperation::where('customer_id', app('customer')->id)->where('user_id', $user->id)
            ->where('encounter_id', $encounterId)->lockForUpdate()->findOrFail($operationId);
        if ($op->status !== 'prepared' || $op->expires_at->isPast() || $op->purpose !== $purpose) {
            throw ValidationException::withMessages(['signature' => 'La preparación expiró, ya fue utilizada o corresponde a otra operación. Revisa nuevamente el documento.']);
        }
        return $op;
    }

    public function requireAcceptance(bool $accepted): void
    {
        if (!$accepted) throw ValidationException::withMessages(['acceptance' => 'Debes aceptar expresamente el contenido y su firma electrónica.']);
    }

    public function assertContent(EncounterSigningOperation $op, string $payload): void
    {
        if (!hash_equals($op->content_hash, hash('sha256', $payload))) {
            throw ValidationException::withMessages(['signature' => 'El contenido o su contexto cambió. Revisa una nueva vista previa antes de firmar.']);
        }
    }
}
