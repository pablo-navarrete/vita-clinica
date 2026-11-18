<?php
namespace Tests\Feature;

use App\Models\{ClinicBranch, Customer, Encounter, EncounterDocumentVersion, EncounterSignature, EncounterSigningOperation, Patient, PatientDocument, Role, User};
use App\Services\Signatures\{EncounterSigningService, EncounterSignatureVerifier};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class EncounterSigningTest extends TestCase
{
    use \Tests\Support\SigningFixtures;

    public function test_signing_without_visual_image_encrypts_exact_version_and_prevents_replay(): void
    {
        $document = $this->attachment();
        $op = $this->prepare();
        $this->sign($op)->assertOk()->assertSessionHas('success', 'La atención quedó finalizada y firmada.');
        $signature = EncounterSignature::where('encounter_id', $this->encounter->id)->firstOrFail();
        $this->assertSame(2, (int) $this->encounter->fresh()->status);
        $this->assertNull($signature->signature_asset_id);
        $this->assertSame($this->doctor->id, $signature->user_id);
        $this->assertTrue($signature->accepted);
        $this->assertSame('password', $signature->authentication_method);
        $snapshot = app(EncounterSignatureVerifier::class)->verify($signature);
        $this->assertSame('Diagnóstico firmado', $snapshot['clinical_assessment']);
        $this->assertStringNotContainsString('Diagnóstico', DB::table('encounter_document_versions')->where('id', $signature->document_version_id)->value('payload'));
        $this->assertStringNotContainsString('correct-password', json_encode($signature->getRawOriginal()));
        foreach (Storage::disk('signed_encounters')->allFiles() as $path) $this->assertStringNotContainsString('private attachment bytes', Storage::disk('signed_encounters')->get($path));
        Storage::disk('local')->delete($document->path);
        $response = app(\App\Services\Signatures\SignedAttachmentStore::class)->responseForDocument($document, false);
        ob_start(); $response->sendContent(); $plain = ob_get_clean();
        $this->assertSame('private attachment bytes', $plain);
        $this->sign($op)->assertUnprocessable();
        $this->assertSame(1, EncounterSignature::where('encounter_id', $this->encounter->id)->count());
        $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record?_vita_frame=1&_vita_content=1')->assertOk()->assertSee('Firma Electrónica Simple');
    }

    public function test_acceptance_password_and_rate_limits_are_required_and_leave_encounter_open(): void
    {
        try {
            DB::table('encounters')->where('id', $this->encounter->id)->update(['status' => 2]);
            $this->fail('An unsigned SQL finalization must be rejected.');
        } catch (\Illuminate\Database\QueryException $exception) { $this->assertNotEmpty($exception->errorInfo); }
        $this->patchJson($this->endpoint('finish'), ['clinical_assessment' => 'Legacy bypass'])->assertUnprocessable();
        $op = $this->prepare();
        $this->sign($op, ['acceptance' => 0])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) $this->sign($op, ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->sign($op)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame(1, (int) $this->encounter->fresh()->status);
        $this->assertSame(0, EncounterSignature::where('encounter_id', $this->encounter->id)->count());
    }

    public function test_stale_preview_tampered_attachment_and_expiration_cannot_finalize(): void
    {
        $document = $this->attachment(); $op = $this->prepare();
        $this->encounter->update(['chief_complaint' => 'Changed']);
        $this->sign($op)->assertUnprocessable();
        $op = $this->prepare();
        Storage::disk('local')->put($document->path, 'altered bytes');
        $this->sign($op)->assertUnprocessable();
        EncounterSigningOperation::findOrFail($op)->update(['expires_at' => now()->subMinute()]);
        $this->sign($op)->assertUnprocessable();
        $this->assertSame(1, (int) $this->encounter->fresh()->status);
    }

    public function test_other_doctors_admins_foreign_tenants_and_wrong_branch_are_denied(): void
    {
        $op = $this->prepare();
        foreach ([$this->user(), $this->user('Administrador')] as $other) {
            $this->actingAs($other)->postJson($this->endpoint('signature-preview'), ['clinical_assessment' => 'x'])->assertForbidden();
            $this->sign($op)->assertNotFound();
        }
        $global = User::create(['name' => 'Global', 'email' => Str::uuid() . '@example.test', 'rut' => Str::uuid(), 'phone' => '123', 'password' => 'correct-password', 'status' => 1]);
        $global->roles()->attach(Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador'])->id);
        $this->actingAs($global)->postJson($this->endpoint('signature-preview'), ['clinical_assessment' => 'x'])->assertForbidden();
        $this->actingAs($this->doctor);
        $foreignClinic = Customer::create(['name' => 'Foreign', 'legal_name' => 'Foreign', 'domain' => 'foreign-' . Str::lower(Str::random(10)), 'holder' => 'Test', 'rut' => Str::uuid(), 'phone' => '123', 'email' => 'foreign@example.test', 'status' => 1]);
        $foreignRoot = 'http://' . $foreignClinic->domain . '.' . config('app.domain');
        $this->postJson(str_replace($this->root, $foreignRoot, $this->endpoint('signature-preview')), ['clinical_assessment' => 'x'])->assertForbidden();
        $this->actingAs($this->doctor);
        $branch = ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Wrong branch', 'status' => 1]);
        $this->doctor->branches()->attach($branch->id, ['status' => 1]);
        $this->withSession(['active_branch_customer_id' => $this->clinic->id, 'active_branch_id' => $branch->id]);
        $this->postJson($this->endpoint('signature-preview'), ['clinical_assessment' => 'x'])->assertForbidden();
    }

    public function test_signed_rows_and_observations_are_protected_even_from_direct_sql(): void
    {
        $doc = $this->attachment(); $op = $this->prepare(); $this->sign($op)->assertOk();
        $this->postJson($this->endpoint('observations'), ['observation' => 'Unsigned'])->assertStatus(409);
        $this->patchJson($this->endpoint('cancel'), ['cancellation_reason' => 'cancel'])->assertNotFound();
        foreach ([
            fn () => DB::table('encounters')->where('id', $this->encounter->id)->update(['clinical_assessment' => 'Tampered']),
            fn () => DB::table('encounter_document_versions')->where('encounter_id', $this->encounter->id)->update(['sha256' => str_repeat('0', 64)]),
            fn () => DB::table('encounter_signatures')->where('encounter_id', $this->encounter->id)->delete(),
            fn () => DB::table('patient_documents')->where('id', $doc->id)->delete(),
            fn () => DB::table('patients')->where('id', $this->patient->id)->delete(),
        ] as $write) {
            try { $write(); $this->fail('Signed records must reject mutations.'); }
            catch (\Illuminate\Database\QueryException $exception) { $this->assertNotEmpty($exception->errorInfo); }
        }
    }

    public function test_failure_after_file_preparation_rolls_back_and_cleanup_preserves_committed_files(): void
    {
        $this->attachment(); $op = $this->prepare();
        EncounterSignature::creating(function () { throw new \RuntimeException('Simulated evidence failure'); });
        try { app(EncounterSigningService::class)->sign($this->doctor, $this->patient->id, $this->encounter->id, $op, 'correct-password', true); $this->fail('Must fail'); }
        catch (\RuntimeException $exception) { $this->assertSame('Simulated evidence failure', $exception->getMessage()); }
        finally { EncounterSignature::flushEventListeners(); }
        $this->assertSame(1, (int) $this->encounter->fresh()->status);
        $this->assertSame(0, EncounterDocumentVersion::where('encounter_id', $this->encounter->id)->count());
        $this->assertNotEmpty(Storage::disk('signed_encounters')->allFiles());
        EncounterSigningOperation::findOrFail($op)->update(['expires_at' => now()->subMinute()]);
        $this->artisan('signatures:cleanup')->assertSuccessful();
        $this->assertSame([], Storage::disk('signed_encounters')->allFiles());
        $new = $this->prepare(); $this->sign($new)->assertOk();
        $paths = Storage::disk('signed_encounters')->allFiles();
        EncounterSigningOperation::findOrFail($new)->update(['expires_at' => now()->subDay()]);
        $this->artisan('signatures:cleanup')->assertSuccessful();
        $this->assertSame($paths, Storage::disk('signed_encounters')->allFiles());
    }

    public function test_appointment_updates_only_with_success_and_visual_versions_are_preserved(): void
    {
        Storage::fake('professional_signatures');
        app(\App\Services\Signatures\ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, [
            'method' => 'fes_vita', 'signature' => \Illuminate\Http\UploadedFile::fake()->image('signature.png', 500, 180),
        ]);
        $specialty = \App\Models\Specialty::create(['customer_id' => $this->clinic->id, 'name' => 'Test specialty', 'status' => 1]);
        $appointment = \App\Models\Appointment::create(['customer_id' => $this->clinic->id, 'clinic_branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id, 'professional_id' => $this->doctor->id, 'specialty_id' => $specialty->id,
            'start_at' => now(), 'end_at' => now()->addMinutes(30), 'status' => 2]);
        $this->encounter->update(['appointment_id' => $appointment->id, 'specialty_id' => $specialty->id]);
        $op = $this->prepare();
        $this->sign($op, ['password' => 'wrong'])->assertUnprocessable();
        $this->assertSame(2, (int) $appointment->fresh()->status);
        $appointment->update(['status' => 4]); // Existing scheduler can do this while an encounter remains open.
        $this->sign($op)->assertOk();
        $this->assertSame(3, (int) $appointment->fresh()->status);
        $signature = EncounterSignature::where('encounter_id', $this->encounter->id)->firstOrFail();
        $assetId = $signature->signature_asset_id; $this->assertNotNull($assetId);
        app(\App\Services\Signatures\ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, ['method' => 'fes_vita', 'remove_signature' => 1]);
        $snapshot = app(EncounterSignatureVerifier::class)->verify($signature);
        $this->assertSame($assetId, $snapshot['visual_assets'][0]['id']);
        $this->doctor->update(['name' => 'Nombre cambiado después de firmar']);
        $fhir = $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record/export/fhir-r4')->assertOk()->getContent();
        $this->assertStringContainsString('Diagnóstico firmado', $fhir);
        $this->assertStringContainsString('Doctor test', $fhir);
        $html = view('customer.patient.medical-record-export-pdfa', [
            'patient' => $this->patient->load('encounters'), 'clinic' => $this->clinic, 'record' => null, 'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString('Firma Electrónica Simple (VITA)', $html);
        foreach ([
            fn () => DB::table('appointments')->where('id', $appointment->id)->delete(),
            fn () => DB::table('specialties')->where('id', $specialty->id)->delete(),
        ] as $delete) {
            try { $delete(); $this->fail('Signed references must be retained.'); }
            catch (\Illuminate\Database\QueryException $exception) { $this->assertNotEmpty($exception->errorInfo); }
        }
    }

    public function test_storage_failure_cannot_create_evidence_and_corruption_is_detected(): void
    {
        $this->attachment(); $op = $this->prepare();
        $this->mock(\App\Services\Signatures\SignedAttachmentStore::class)->shouldReceive('prepare')->once()->andThrow(new \RuntimeException('Storage failure'));
        $this->app->forgetInstance(EncounterSigningService::class);
        try { app(EncounterSigningService::class)->sign($this->doctor, $this->patient->id, $this->encounter->id, $op, 'correct-password', true); $this->fail('Must fail'); }
        catch (\RuntimeException $exception) { $this->assertSame('Storage failure', $exception->getMessage()); }
        $this->assertSame(1, (int) $this->encounter->fresh()->status);
        $this->assertSame(0, EncounterSignature::where('encounter_id', $this->encounter->id)->count());
        $this->app->forgetInstance(\App\Services\Signatures\SignedAttachmentStore::class);
        $this->sign($op)->assertOk();
        $signature = EncounterSignature::where('encounter_id', $this->encounter->id)->firstOrFail();
        $file = $signature->documentVersion->attachment_manifest[0];
        Storage::disk('signed_encounters')->put($file['path'], 'corrupt');
        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        app(EncounterSignatureVerifier::class)->verify($signature);
    }

    public function test_existing_unsigned_history_remains_unchanged_and_is_not_signed_retroactively(): void
    {
        $history = Encounter::where('status', 2)->whereDoesntHave('signatures')->limit(5)->get();
        if ($history->isEmpty()) $this->markTestSkipped('Requires at least one historical finalized encounter.');
        $before = $history->mapWithKeys(fn ($row) => [$row->id => $row->getRawOriginal()])->all();
        $this->sign($this->prepare())->assertOk();
        foreach ($before as $id => $attributes) {
            $old = Encounter::findOrFail($id);
            $this->assertSame($attributes, $old->getRawOriginal());
            $this->assertFalse($old->signatures()->exists());
        }
    }
}
