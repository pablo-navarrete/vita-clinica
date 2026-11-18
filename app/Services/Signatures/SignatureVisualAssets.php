<?php
namespace App\Services\Signatures;

use App\Models\ProfessionalSignatureProfile;
use App\Models\User;

class SignatureVisualAssets
{
    public function forUser(User $user, int $customerId): array
    {
        $profile = ProfessionalSignatureProfile::where('customer_id', $customerId)->where('user_id', $user->id)->with(['signatureAsset', 'stampAsset'])->first();
        return collect([$profile?->signatureAsset, $profile?->stampAsset])->filter()->map(fn ($asset) => [
            'id' => $asset->id, 'uuid' => $asset->uuid, 'kind' => $asset->kind, 'sha256' => $asset->sha256,
        ])->values()->all();
    }
}
