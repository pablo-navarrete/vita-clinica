<?php

namespace Tests\Feature;

use App\Services\Signatures\{ProfessionalSignatureService, SignedAttachmentStore};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncounterPdfSignatureTest extends TestCase
{
    use \Tests\Support\SigningFixtures;

    public function test_pdf_uses_preserved_signature_and_stamp_after_profile_files_are_removed(): void
    {
        Storage::fake('professional_signatures');
        app(ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, [
            'method' => 'fes_vita',
            'signature' => UploadedFile::fake()->image('signature.png', 500, 180),
            'stamp' => UploadedFile::fake()->image('stamp.jpg', 300, 300),
        ]);
        $this->sign($this->prepare())->assertOk();
        $signature = $this->encounter->fresh()->signature;
        $images = app(SignedAttachmentStore::class)->visualImages($signature);
        $this->assertCount(2, $images);
        Storage::disk('professional_signatures')->delete(Storage::disk('professional_signatures')->allFiles());
        $this->assertSame($images, app(SignedAttachmentStore::class)->visualImages($signature));
        $html = view('customer.patient.record-workspace.pdf-signature', compact('signature'))->render();
        $this->assertStringContainsString($images[0]['src'], $html);
        $this->assertStringContainsString($images[1]['src'], $html);
        $this->assertStringContainsString($signature->content_hash, $html);
        $this->assertStringContainsString($this->doctor->rut, $html);
        $pdf = $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record/export/pdfa')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_signature_without_visual_configuration_still_has_pdf_evidence(): void
    {
        $this->sign($this->prepare())->assertOk();
        $signature = $this->encounter->fresh()->signature;
        $this->assertSame([], app(SignedAttachmentStore::class)->visualImages($signature));
        $html = view('customer.patient.record-workspace.pdf-signature', compact('signature'))->render();
        $this->assertStringContainsString($signature->content_hash, $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_transparent_signature_is_rendered_on_white_without_changing_historical_bytes(): void
    {
        Storage::fake('professional_signatures');
        $canvas = imagecreatetruecolor(100, 50);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesetpixel($canvas, 50, 25, imagecolorallocatealpha($canvas, 20, 30, 40, 0));
        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);
        app(ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, [
            'method' => 'fes_vita', 'signature' => UploadedFile::fake()->createWithContent('signature.png', $png),
        ]);
        $this->sign($this->prepare())->assertOk();
        $signature = $this->encounter->fresh()->signature;
        $files = [];
        foreach (Storage::disk('signed_encounters')->allFiles() as $path) {
            $files[$path] = Storage::disk('signed_encounters')->get($path);
        }
        $images = app(SignedAttachmentStore::class)->visualImages($signature);
        $rendered = imagecreatefromstring(base64_decode(explode(',', $images[0]['src'], 2)[1]));
        $this->assertSame(0xffffff, imagecolorat($rendered, 0, 0));
        $this->assertSame((20 << 16) | (30 << 8) | 40, imagecolorat($rendered, 50, 25));
        imagedestroy($rendered);
        foreach ($files as $path => $bytes) $this->assertSame($bytes, Storage::disk('signed_encounters')->get($path));
        app(\App\Services\Signatures\EncounterSignatureVerifier::class)->verify($signature);
    }
}
