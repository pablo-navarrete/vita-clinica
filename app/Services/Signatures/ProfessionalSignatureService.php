<?php

namespace App\Services\Signatures;

use App\Models\ProfessionalSignatureAsset;
use App\Models\ProfessionalSignatureProfile;
use App\Models\User;
use App\Support\ClinicActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalSignatureService
{
    public function canManage(?User $user, int $customerId): bool
    {
        if (!$user || (int) $user->customer_id !== $customerId || (int) $user->status !== 1) {
            return false;
        }

        // A physician's configuration belongs to the clinic, not the selected branch.
        return $user->branches()->where('clinic_branches.customer_id', $customerId)
            ->where('clinic_branches.status', 1)->wherePivot('status', 1)
            ->get()->contains(fn ($branch) => $user->hasClinicRole('Doctor', $customerId, (int) $branch->id));
    }

    public function profile(User $user, int $customerId): ?ProfessionalSignatureProfile
    {
        abort_unless($this->canManage($user, $customerId), 403);

        return ProfessionalSignatureProfile::where('customer_id', $customerId)
            ->where('user_id', $user->id)->with(['signatureAsset', 'stampAsset'])->first();
    }

    public function save(User $user, int $customerId, array $data): ProfessionalSignatureProfile
    {
        abort_unless($this->canManage($user, $customerId), 403);
        app(SignatureMethodRegistry::class)->get($data['method']);
        $written = [];

        try {
            return DB::transaction(function () use ($user, $customerId, $data, &$written) {
                // Serialize first creation and replacements even when several branches are open.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $profile = ProfessionalSignatureProfile::firstOrCreate([
                    'customer_id' => $customerId, 'user_id' => $user->id,
                ], ['method' => $data['method']]);
                $before = $profile->only(['method', 'signature_asset_id', 'stamp_asset_id']);
                $profile->method = $data['method'];
                foreach (['signature', 'stamp'] as $kind) {
                    if (!empty($data['remove_' . $kind])) {
                        $profile->{$kind . '_asset_id'} = null;
                    }
                    if (($data[$kind] ?? null) instanceof UploadedFile) {
                        $asset = $this->storeImage($data[$kind], $kind, $user, $customerId, $written);
                        $profile->{$kind . '_asset_id'} = $asset->id;
                    }
                }
                $profile->save();
                $after = $profile->only(['method', 'signature_asset_id', 'stamp_asset_id']);
                if ($before !== $after) {
                    ClinicActivityLogger::recordManual($customerId, 'Firma electrónica', 'updated',
                        'configuración de firma visual', $user->name, 'Actualizó su firma visual o timbre profesional.',
                        array_map(fn ($key) => $key . ': ' . ($before[$key] ?? 'none') . ' -> ' . ($after[$key] ?? 'none'),
                            array_keys($before)), ProfessionalSignatureProfile::class, $profile->id);
                }

                return $profile->load(['signatureAsset', 'stampAsset']);
            });
        } catch (\Throwable $exception) {
            // Only uncommitted new files can be removed; historical assets remain intact.
            foreach ($written as $path) Storage::disk('professional_signatures')->delete($path);
            throw $exception;
        }
    }

    private function storeImage(UploadedFile $file, string $kind, User $user, int $customerId, array &$written): ProfessionalSignatureAsset
    {
        $invalid = fn () => ValidationException::withMessages([$kind => 'Usa una imagen PNG o JPG válida, de hasta 2 MB y entre 32 × 16 y 2400 × 1600 píxeles.']);
        if (!$file->isValid() || $file->getSize() > 2 * 1024 * 1024) throw $invalid();
        $bytes = file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            || $info[0] < 32 || $info[1] < 16 || $info[0] > 2400 || $info[1] > 1600) throw $invalid();
        $source = @imagecreatefromstring($bytes);
        if (!$source) throw $invalid();
        $clean = imagecreatetruecolor($info[0], $info[1]);
        try {
            imagealphablending($clean, false);
            imagesavealpha($clean, true);
            imagefill($clean, 0, 0, imagecolorallocatealpha($clean, 255, 255, 255, 127));
            imagecopy($clean, $source, 0, 0, 0, 0, $info[0], $info[1]);
            ob_start();
            try { imagepng($clean, null, 6); $png = ob_get_contents(); }
            finally { ob_end_clean(); }
        } finally {
            imagedestroy($source);
            imagedestroy($clean);
        }
        // Re-encoding strips metadata, original names and any appended executable content.
        $uuid = (string) Str::uuid();
        $path = $customerId . '/' . $user->id . '/' . $uuid . '.png';
        $written[] = $path;
        Storage::disk('professional_signatures')->put($path, $png, 'private');

        return ProfessionalSignatureAsset::create([
            'uuid' => $uuid, 'customer_id' => $customerId, 'user_id' => $user->id,
            'kind' => $kind, 'path' => $path, 'mime' => 'image/png', 'bytes' => strlen($png),
            'width' => $info[0], 'height' => $info[1], 'sha256' => hash('sha256', $png),
        ]);
    }
}
