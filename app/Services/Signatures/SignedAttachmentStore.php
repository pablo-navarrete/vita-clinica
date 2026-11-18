<?php
namespace App\Services\Signatures;

use App\Models\Encounter;
use App\Models\EncounterDocumentVersion;
use App\Models\EncounterSigningOperation;
use App\Models\ProfessionalSignatureAsset;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignedAttachmentStore
{
    /** Read only the immutable visual copies belonging to this verified document. */
    public function visualImages(\App\Models\EncounterSignature $signature): array
    {
        $snapshot = app(EncounterSignatureVerifier::class)->verify($signature);
        $manifest = collect($signature->documentVersion->attachment_manifest);
        $images = [];
        foreach ($snapshot['visual_assets'] ?? [] as $visual) {
            $file = $manifest->firstWhere('key', 'visual-' . $visual['id']);
            abort_unless($file && hash_equals($visual['sha256'], $file['sha256'])
                && $file['bytes'] <= 16 * 1024 * 1024, 409, 'Firma visual inconsistente.');
            $bytes = '';
            foreach ($this->chunks($file) as $chunk) $bytes .= $chunk;
            $info = @getimagesizefromstring($bytes);
            abort_unless($info && in_array($info['mime'], ['image/png', 'image/jpeg'], true), 409, 'Firma visual inválida.');
            // PDF/A-1 does not support transparency. Flatten only this rendering copy;
            // the authenticated historical bytes and their hash remain unchanged.
            $source = @imagecreatefromstring($bytes);
            abort_unless($source, 409, 'Firma visual inválida.');
            $canvas = imagecreatetruecolor($info[0], $info[1]);
            try {
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                imagealphablending($canvas, true);
                imagecopy($canvas, $source, 0, 0, 0, 0, $info[0], $info[1]);
                ob_start();
                try { imagepng($canvas); $rendered = ob_get_contents(); }
                finally { ob_end_clean(); }
            } finally {
                imagedestroy($source);
                imagedestroy($canvas);
            }
            $images[] = ['kind' => $visual['kind'], 'src' => 'data:image/png;base64,' . base64_encode($rendered)];
        }
        return $images;
    }

    public function prepare(EncounterSigningOperation $operation, Encounter $encounter, array $snapshot): array
    {
        $manifest = [];
        foreach ($encounter->documents as $document) {
            $this->copy($operation, 'document-' . $document->id, $document->disk, $document->path, $document->sha256, $manifest);
        }
        return array_merge($manifest, $this->prepareVisuals($operation, $snapshot));
    }

    public function prepareVisuals(EncounterSigningOperation $operation, array $snapshot): array
    {
        $manifest = [];
        foreach ($snapshot['visual_assets'] as $visual) {
            $asset = ProfessionalSignatureAsset::where('customer_id', $operation->customer_id)
                ->where('user_id', $operation->user_id)->findOrFail($visual['id']);
            $this->copy($operation, 'visual-' . $asset->id, 'professional_signatures', $asset->path, $asset->sha256, $manifest);
        }
        return $manifest;
    }

    private function copy(EncounterSigningOperation $operation, string $key, string $disk, string $path, string $hash, array &$manifest): void
    {
        $source = Storage::disk($disk);
        if (!$source->exists($path)) $this->invalid();
        $target = $operation->customer_id . '/' . $operation->id . '/' . $key . '.enc';
        $storage = Storage::disk('signed_encounters');
        $input = $source->readStream($path);
        $encrypted = tmpfile();
        if (!is_resource($input) || !is_resource($encrypted)) throw new \RuntimeException('Cannot prepare signed attachment.');
        $digest = hash_init('sha256'); $size = 0; $index = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) throw new \RuntimeException('Cannot read signed attachment.');
                if ($chunk === '') break;
                hash_update($digest, $chunk); $size += strlen($chunk);
                $line = Crypt::encryptString($target . "\n" . $index++ . "\n" . $chunk) . "\n";
                if (fwrite($encrypted, $line) !== strlen($line)) throw new \RuntimeException('Cannot stage signed attachment.');
            }
            if (!hash_equals($hash, hash_final($digest))) $this->invalid();
            rewind($encrypted);
            $storage->put($target, $encrypted, 'private');
        } finally { fclose($input); fclose($encrypted); }
        $entry = ['key' => $key, 'path' => $target, 'sha256' => $hash, 'bytes' => $size, 'chunks' => $index, 'format' => 'vita-encrypted-chunks-v1'];
        $this->verifyFile($entry);
        $manifest[] = $entry;
    }

    public function verify(EncounterDocumentVersion $version): void
    {
        foreach ($version->attachment_manifest as $file) $this->verifyFile($file);
    }

    public function responseForDocument(\App\Models\PatientDocument $document, bool $inline)
    {
        return $this->responseForCustomerDocument($document, (int) app('customer')->id, $inline);
    }

    public function responseForCustomerDocument(\App\Models\PatientDocument $document, int $customerId, bool $inline = false)
    {
        abort_unless((int) $document->customer_id === $customerId, 403);
        $signature = $document->encounter?->signature;
        if (!$signature) return null;
        app(EncounterSignatureVerifier::class)->verifyForCustomer($signature, $customerId);
        $entry = collect($signature->documentVersion->attachment_manifest)->firstWhere('key', 'document-' . $document->id);
        abort_unless($entry, 409, 'El documento no forma parte de la versión firmada.');
        return response()->stream(function () use ($entry) {
            foreach ($this->chunks($entry) as $chunk) echo $chunk;
        }, 200, [
            'Content-Type' => $inline ? $document->mime_type : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode(basename($document->original_name)),
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function verifyFile(array $file): void
    {
        foreach ($this->chunks($file) as $chunk) { /* Authenticate every chunk and the complete digest. */ }
    }

    private function chunks(array $file): \Generator
    {
        $disk = Storage::disk('signed_encounters');
        if (!$disk->exists($file['path'])) $this->invalid();
        $input = $disk->readStream($file['path']);
        $digest = hash_init('sha256'); $index = 0; $size = 0;
        try {
            while (($line = fgets($input, 4 * 1024 * 1024)) !== false) {
                $plain = Crypt::decryptString(rtrim($line, "\n"));
                $prefix = $file['path'] . "\n" . $index++ . "\n";
                if (!str_starts_with($plain, $prefix)) $this->invalid();
                $chunk = substr($plain, strlen($prefix));
                hash_update($digest, $chunk); $size += strlen($chunk);
                yield $chunk;
            }
            if ($index !== $file['chunks'] || $size !== $file['bytes'] || !hash_equals($file['sha256'], hash_final($digest))) $this->invalid();
        } finally { fclose($input); }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['signature' => 'Un archivo falta o no supera la verificación de integridad. No se finalizó la atención.']);
    }
}
