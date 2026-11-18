<?php
namespace App\Services\Signatures;

use App\Models\{Encounter, User};

class EncounterAddendumPolicy
{
    public function authorize(User $user, Encounter $encounter): void
    {
        app(EncounterSigningPolicy::class)->authorize($user, $encounter);
        abort_unless((int) $encounter->status === 2 && $encounter->signature, 409, 'Las adendas requieren una atención finalizada y firmada.');
        abort_unless((int) $encounter->signature->user_id === (int) $user->id, 403, 'Solo el firmante original puede agregar adendas.');
    }

    public function allows(?User $user, Encounter $encounter): bool
    {
        if (!$user) return false;
        try { $this->authorize($user, $encounter); return true; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { return false; }
    }
}
