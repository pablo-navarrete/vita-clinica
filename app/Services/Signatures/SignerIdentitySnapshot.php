<?php

namespace App\Services\Signatures;

use App\Models\User;
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use App\Services\Identification\Storage\IdentifierSubject;
use Illuminate\Support\Facades\DB;

/** Document identity is neither proof of identity nor a professional licence. */
final class SignerIdentitySnapshot
{
    public function lockTenant(User $user): void
    {
        abort_unless(DB::transactionLevel() > 0, 403);
        // Same first lock as all identifier writers; then operation, encounter and user.
        abort_unless(DB::table('customers')->where('id', app('customer')->id)->lockForUpdate()->first(), 403);
    }

    public function capture(User $user, bool $requireActiveDocument = true): array
    {
        abort_unless((int) $user->customer_id === (int) app('customer')->id, 403);
        $subject = new IdentifierSubject('user', (int) $user->id, (int) $user->customer_id);
        $document = app(IdentifierRepositoryFactory::class)->make()->primarySnapshot($subject, $user);
        $missingPrimary = $document === null && DB::table('user_identifiers')->where('customer_id', $user->customer_id)
            ->where('user_id', $user->id)->exists();
        abort_if($missingPrimary && $requireActiveDocument, 409, 'Configura un documento principal activo antes de preparar la firma.');
        if ($missingPrimary) {
            $document = ['reference' => null, 'country' => null, 'type' => 'unavailable',
                'namespace' => 'unavailable', 'normalized' => null, 'display' => null,
                'normalization_version' => null, 'validation_status' => 'missing_primary', 'identity_verified' => false];
        }
        // No inference, validation or automatic migration of legacy RUT values.
        $document ??= ['reference' => null, 'country' => null, 'type' => 'legacy_rut',
            'namespace' => 'legacy:users.rut', 'normalized' => null, 'display' => $user->rut,
            'normalization_version' => null, 'validation_status' => 'legacy_unverified', 'identity_verified' => false];

        return ['id' => (int) $user->id, 'name' => $user->name, 'document' => $document];
    }

    public function label(array $identity): string
    {
        if (! isset($identity['document'])) {
            return 'RUT: '.($identity['rut'] ?? 'No registrado');
        }
        $document = $identity['document'];
        $label = $document['namespace'] === 'CL:RUT' ? 'RUT' : match ($document['type']) {
            'passport' => 'Pasaporte', 'national_id' => 'Documento nacional',
            'legacy_rut' => 'RUT histórico', default => 'Documento',
        };

        return $label.': '.($document['display'] ?: 'No registrado').(! empty($document['country']) ? ' ('.$document['country'].')' : '');
    }

    public function fhirIdentifier(array $identity): ?array
    {
        $doc = $identity['document'] ?? null;
        if ((! $doc && filled($identity['rut'] ?? null)) || (($doc['type'] ?? null) === 'legacy_rut' && filled($doc['display'] ?? null))) {
            return ['system' => 'https://vita-clinica.cl/fhir/NamingSystem/legacy-users-rut',
                'value' => $doc['display'] ?? $identity['rut'], 'type' => ['text' => 'Identificador histórico sin reclasificar']];
        }
        if (! $doc || empty($doc['normalized']) || empty($doc['country'])) {
            return null;
        }

        return ['system' => 'https://vita-clinica.cl/fhir/NamingSystem/identity/'.rawurlencode($doc['country']).'/'.rawurlencode($doc['type']),
            'value' => $doc['normalized'], 'type' => ['text' => $doc['namespace']]];
    }
}
