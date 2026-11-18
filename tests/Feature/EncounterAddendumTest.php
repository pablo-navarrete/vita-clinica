<?php
namespace Tests\Feature;

use App\Models\{Encounter, EncounterDocumentVersion, EncounterSignature, EncounterSigningOperation};
use App\Services\Signatures\{EncounterAddendumService, EncounterSignatureVerifier};
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class EncounterAddendumTest extends TestCase
{
    use \Tests\Support\SigningFixtures;

    private function original(): EncounterSignature
    {
        $this->sign($this->prepare())->assertOk();
        return $this->encounter->fresh()->signature;
    }

    private function addendum(array $data = []): string
    {
        return $this->postJson($this->endpoint('addenda/preview'), array_merge(['type' => 'observation', 'content' => 'Información posterior de prueba'], $data))
            ->assertOk()->assertJsonStructure(['operation_id', 'html', 'hash'])->json('operation_id');
    }

    private function signAddendum(string $op, array $data = [])
    {
        return $this->postJson($this->endpoint('addenda'), array_merge(['operation_id' => $op, 'password' => 'correct-password', 'acceptance' => 1], $data));
    }

    public function test_three_types_preserve_original_and_each_has_its_own_evidence_and_sequence(): void
    {
        $root = $this->original();
        $before = $this->encounter->fresh()->getRawOriginal();
        $originalVersion = $root->documentVersion->getRawOriginal();
        $originalEvidence = $root->getRawOriginal();
        $this->signAddendum($this->addendum())->assertOk()->assertSessionHas('success', 'La adenda quedó guardada y firmada.');
        $this->signAddendum($this->addendum(['type' => 'complement', 'content' => 'Nueva indicación']))->assertOk();
        $this->signAddendum($this->addendum(['type' => 'rectification', 'content' => 'Diagnóstico corregido',
            'rectifies_version_id' => $root->document_version_id, 'target_field' => 'clinical_assessment', 'reason' => 'Error de transcripción']))->assertOk();
        $addenda = $this->encounter->fresh()->addendumSignatures;
        $this->assertCount(3, $addenda);
        $this->assertSame([2, 3, 4], $addenda->map(fn ($s) => (int) $s->documentVersion->version)->all());
        $this->assertSame($before, $this->encounter->fresh()->getRawOriginal());
        $this->assertSame($originalVersion, $root->documentVersion->fresh()->getRawOriginal());
        $this->assertSame($originalEvidence, $root->fresh()->getRawOriginal());
        $snapshots = app(EncounterSignatureVerifier::class)->verifyMany($addenda, true);
        foreach ($addenda as $signature) {
            $this->assertSame($root->document_version_id, $signature->documentVersion->parent_version_id);
            $this->assertSame($this->doctor->id, $signature->user_id);
            $this->assertTrue($signature->accepted);
            $this->assertSame('password', $signature->authentication_method);
            $this->assertSame('vita-fes-addendum-v1', $signature->acceptance_version);
            $this->assertStringNotContainsString('Información', $signature->documentVersion->getRawOriginal('payload'));
        }
        $correction = $snapshots[$addenda->last()->id]['correction'];
        $this->assertSame('Diagnóstico firmado', $correction['previous_text']);
        $this->assertSame('Error de transcripción', $correction['reason']);
        $this->assertSame($root->id, $this->encounter->fresh()->signature->id);
        $this->assertSame(3, DB::table('patient_audit_logs')->where('patient_id', $this->patient->id)->where('section', 'Adenda médica firmada FES')->count());
    }

    public function test_rectification_of_prior_addendum_rejects_missing_reason_wrong_field_or_foreign_document(): void
    {
        $root = $this->original();
        $this->signAddendum($this->addendum(['content' => 'Texto anterior completo']))->assertOk();
        $target = $this->encounter->fresh()->addendumSignatures->first();
        $base = ['type' => 'rectification', 'content' => 'Texto nuevo', 'rectifies_version_id' => $target->document_version_id, 'target_field' => 'content'];
        $this->postJson($this->endpoint('addenda/preview'), $base)->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->endpoint('addenda/preview'), $base + ['reason' => '   '])->assertUnprocessable();
        $this->postJson($this->endpoint('addenda/preview'), array_merge($base, ['reason' => 'Justificación', 'target_field' => 'clinical_assessment']))->assertUnprocessable();
        $this->postJson($this->endpoint('addenda/preview'), array_merge($base, ['reason' => 'Justificación', 'rectifies_version_id' => 99999999]))->assertNotFound();
        $op = $this->addendum($base + ['reason' => 'Aclaración posterior']);
        $this->signAddendum($op)->assertOk();
        $snapshot = app(EncounterSignatureVerifier::class)->verify($this->encounter->fresh()->addendumSignatures->last());
        $this->assertSame('Texto anterior completo', $snapshot['correction']['previous_text']);
        $this->assertSame($target->document_version_id, $snapshot['correction']['document_id']);
    }

    public function test_purpose_isolation_replay_and_stale_parallel_preparations(): void
    {
        $originalOp = $this->prepare(); $this->sign($originalOp)->assertOk();
        $first = $this->addendum(); $second = $this->addendum(['content' => 'Otro contenido']);
        $this->sign($first)->assertUnprocessable();
        $this->signAddendum($originalOp)->assertUnprocessable();
        $this->signAddendum($first)->assertOk();
        $this->signAddendum($first)->assertUnprocessable();
        $this->signAddendum($second)->assertUnprocessable();
        $this->assertCount(1, $this->encounter->fresh()->addendumSignatures);
        $this->signAddendum($this->addendum(['content' => 'Otro contenido']))->assertOk();
        $this->assertCount(2, $this->encounter->fresh()->addendumSignatures);
    }

    public function test_identity_acceptance_limits_expiration_and_unauthorized_users(): void
    {
        $this->original(); $op = $this->addendum();
        $this->signAddendum($op, ['acceptance' => 0])->assertUnprocessable();
        foreach (range(1, 5) as $i) $this->signAddendum($op, ['password' => 'wrong'])->assertUnprocessable();
        $this->signAddendum($op)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertCount(0, $this->encounter->fresh()->addendumSignatures);
        foreach ([$this->user(), $this->user('Administrador')] as $other) {
            $this->actingAs($other)->get($this->endpoint('addenda/create'))->assertForbidden();
            $this->postJson($this->endpoint('addenda/preview'), ['type' => 'complement', 'content' => 'x'])->assertForbidden();
            $this->signAddendum($op)->assertNotFound();
        }
        $this->actingAs($this->doctor);
        EncounterSigningOperation::findOrFail($op)->update(['expires_at' => now()->subMinute()]);
        $this->signAddendum($op)->assertUnprocessable();
    }

    public function test_unsigned_open_cancelled_and_legacy_finished_encounters_cannot_receive_addenda(): void
    {
        $this->get($this->endpoint('addenda/create'))->assertStatus(409);
        $this->postJson($this->endpoint('addenda/preview'), ['type' => 'observation', 'content' => 'x'])->assertStatus(409);
        $this->encounter->update(['status' => 3]);
        $this->get($this->endpoint('addenda/create'))->assertStatus(409);
        $old = Encounter::where('status', 2)->whereDoesntHave('signatures')->first();
        if ($old) {
            $before = $old->getRawOriginal();
            $this->assertNull($old->signature);
            $this->assertSame($before, $old->fresh()->getRawOriginal());
        }
    }

    public function test_immutable_addenda_history_and_exports_keep_original_and_rectification_separate(): void
    {
        $root = $this->original();
        $op = $this->addendum(['type' => 'rectification', 'content' => 'Nuevo diagnóstico de adenda', 'rectifies_version_id' => $root->document_version_id,
            'target_field' => 'clinical_assessment', 'reason' => 'Corrección explícita']);
        $this->signAddendum($op)->assertOk();
        $ad = $this->encounter->fresh()->addendumSignatures->first();
        foreach ([
            fn () => DB::table('encounter_document_versions')->where('id', $ad->document_version_id)->update(['sha256' => str_repeat('0', 64)]),
            fn () => DB::table('encounter_document_versions')->where('id', $ad->document_version_id)->delete(),
            fn () => DB::table('encounter_signatures')->where('id', $ad->id)->delete(),
            fn () => DB::table('encounters')->where('id', $this->encounter->id)->update(['clinical_assessment' => 'Replaced']),
        ] as $write) {
            try { $write(); $this->fail('Signed content must be immutable.'); }
            catch (\Illuminate\Database\QueryException $exception) { $this->assertNotEmpty($exception->errorInfo); }
        }
        $page = $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record?_vita_frame=1&_vita_content=1')->assertOk();
        $page->assertSee('Agregar adenda')->assertSee('Nuevo diagnóstico de adenda')->assertSee('Diagnóstico firmado')->assertSee('Texto anterior conservado');
        $this->get($this->endpoint('addenda/create') . '?_vita_frame=1&_vita_content=1')->assertOk()->assertSee('Firmar y guardar adenda');
        $modal = $this->getJson($this->endpoint('addenda/create'))->assertOk()->assertJsonStructure(['html'])->json('html');
        $this->assertStringContainsString('data-addendum-editor', $modal);
        $this->assertStringContainsString('encounter-addendum-signature-modal', $modal);
        $this->assertStringContainsString('data-addendum-close', $modal);
        $this->assertStringNotContainsString('<!DOCTYPE', $modal);
        $fhir = $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record/export/fhir-r4')->assertOk()->json();
        $docs = collect($fhir['entry'])->pluck('resource')->where('resourceType', 'DocumentReference')->values();
        $this->assertCount(2, $docs);
        $original = json_decode(base64_decode($docs[0]['content'][0]['attachment']['data']), true);
        $addition = json_decode(base64_decode($docs[1]['content'][0]['attachment']['data']), true);
        $this->assertSame('Diagnóstico firmado', $original['clinical_assessment']);
        $this->assertSame('Nuevo diagnóstico de adenda', $addition['content']);
        $this->assertSame('appends', $docs[1]['relatesTo'][0]['code']);
        $html = view('customer.patient.medical-record-export-pdfa', ['patient' => $this->patient->load('encounters'), 'clinic' => $this->clinic,
            'record' => null, 'generatedAt' => now()])->render();
        $this->assertStringContainsString('Adenda firmada independiente', $html);
        $this->assertStringContainsString('Texto anterior conservado', $html);
        $pdf = $this->get($this->root . '/patients/' . $this->patient->id . '/medical-record/export/pdfa')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_failed_evidence_and_cleanup_never_touch_original(): void
    {
        $this->attachment(); $original = $this->original(); $files = Storage::disk('signed_encounters')->allFiles();
        Storage::fake('professional_signatures');
        app(\App\Services\Signatures\ProfessionalSignatureService::class)->save($this->doctor, $this->clinic->id, [
            'method' => 'fes_vita', 'signature' => \Illuminate\Http\UploadedFile::fake()->image('visual.png', 500, 180),
        ]);
        $op = $this->addendum();
        EncounterSignature::creating(fn () => throw new \RuntimeException('Simulated addendum failure'));
        try { app(EncounterAddendumService::class)->sign($this->doctor, $this->patient->id, $this->encounter->id, $op, 'correct-password', true); $this->fail('Must fail'); }
        catch (\RuntimeException $exception) { $this->assertSame('Simulated addendum failure', $exception->getMessage()); }
        finally { EncounterSignature::flushEventListeners(); }
        $this->assertCount(0, $this->encounter->fresh()->addendumSignatures);
        $this->assertSame(1, EncounterDocumentVersion::where('encounter_id', $this->encounter->id)->count());
        $this->assertGreaterThan(count($files), count(Storage::disk('signed_encounters')->allFiles()));
        EncounterSigningOperation::findOrFail($op)->update(['expires_at' => now()->subMinute()]);
        $this->artisan('signatures:cleanup')->assertSuccessful();
        $this->assertSame($files, Storage::disk('signed_encounters')->allFiles());
        app(EncounterSignatureVerifier::class)->verify($original);
        $committedOp = $this->addendum(); $this->signAddendum($committedOp)->assertOk();
        $committedFiles = Storage::disk('signed_encounters')->allFiles();
        EncounterSigningOperation::findOrFail($committedOp)->update(['expires_at' => now()->subDay()]);
        $this->artisan('signatures:cleanup')->assertSuccessful();
        $this->assertSame($committedFiles, Storage::disk('signed_encounters')->allFiles());
        app(EncounterSignatureVerifier::class)->verify($this->encounter->fresh()->addendumSignatures->first());
    }

    public function test_cross_tenant_targets_superadmin_and_wrong_branch_cannot_create_or_sign_addenda(): void
    {
        $original = $this->original(); $op = $this->addendum();
        $saved = ['clinic' => $this->clinic, 'doctor' => $this->doctor, 'branch' => $this->branch,
            'patient' => $this->patient, 'encounter' => $this->encounter, 'root' => $this->root];
        $this->fixtures(); $foreign = $this->original(); $foreignRoot = $this->root;
        foreach ($saved as $key => $value) $this->$key = $value;
        $this->app->instance('customer', $this->clinic); $this->actingAs($this->doctor);
        $this->withSession(['active_branch_customer_id' => $this->clinic->id, 'active_branch_id' => $this->branch->id]);
        $this->postJson($this->endpoint('addenda/preview'), ['type' => 'rectification', 'content' => 'x', 'reason' => 'x',
            'rectifies_version_id' => $foreign->document_version_id, 'target_field' => 'clinical_assessment'])->assertNotFound();
        $this->get(str_replace($this->root, $foreignRoot, $this->endpoint('addenda/create')))->assertForbidden();
        $this->actingAs($this->doctor);
        $otherBranch = \App\Models\ClinicBranch::create(['customer_id' => $this->clinic->id, 'name' => 'Other', 'status' => 1]);
        $this->doctor->branches()->attach($otherBranch->id, ['status' => 1]);
        $this->doctor->branchRoles()->attach(\App\Models\Role::where('customer_id', $this->clinic->id)->where('name', 'Doctor')->value('id'),
            ['customer_id' => $this->clinic->id, 'clinic_branch_id' => $otherBranch->id]);
        $this->withSession(['active_branch_customer_id' => $this->clinic->id, 'active_branch_id' => $otherBranch->id]);
        $this->signAddendum($op)->assertForbidden();
        $global = \App\Models\User::create(['name' => 'Global admin', 'email' => \Illuminate\Support\Str::uuid() . '@example.test',
            'rut' => \Illuminate\Support\Str::uuid(), 'phone' => '123', 'password' => 'correct-password', 'status' => 1]);
        $global->roles()->attach(\App\Models\Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador'])->id);
        $this->actingAs($global)->get($this->endpoint('addenda/create'))->assertForbidden();
        $this->signAddendum($op)->assertNotFound();
        $this->assertSame($original->id, $this->encounter->fresh()->signature->id);
        $this->assertCount(0, $this->encounter->fresh()->addendumSignatures);
    }
}
