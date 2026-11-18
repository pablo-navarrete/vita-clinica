<?php

namespace Tests\Integration\Identification;

use App\Models\EncounterSignature;
use App\Services\Identification\Storage\IdentifierRepositoryFactory;
use App\Services\Identification\Storage\IdentifierSubject;
use App\Services\Signatures\CanonicalContentSerializer;
use App\Services\Signatures\EncounterAddendumSnapshotBuilder;
use App\Services\Signatures\EncounterSignatureVerifier;
use App\Services\Signatures\EncounterSnapshotBuilder;
use App\Services\Signatures\SignedDocumentWriter;
use App\Services\Signatures\SigningOperationManager;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

class InternationalSignatureTest extends TestCase
{
    use \Tests\Support\SigningFixtures { setUp as signingSetUp; tearDown as signingTearDown; }

    private bool $started = false;

    protected function setUp(): void
    {
        // Check before any fixture writes, including when invoked outside the runner.
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes'
            || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', getenv('DB_DATABASE') ?: '')) {
            $this->markTestSkipped('Isolated identification schema required.');
        }
        $this->signingSetUp();
        $this->started = true;
        config(['identification' => IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
    }

    protected function tearDown(): void
    {
        if ($this->started) {
            $this->signingTearDown();
        }
    }

    private function document(string $country, string $type, string $number): string
    {
        $admin = $this->user('Administrador');
        return app(IdentifierRepositoryFactory::class)->make()->create(
            new IdentifierSubject('user', $this->doctor->id, $this->clinic->id), $admin,
            $country, $type, $number, 'natural_person', true);
    }

    public static function documents(): array
    {
        return [['CL', 'national_id', '123456785', '12.345.678-5'],
            ['AR', 'passport', '000Ab-19', '000Ab-19'], ['PE', 'national_id', '00012345', '00012345']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('documents')]
    public function test_v3_signatures_freeze_document_and_exports_use_historical_identity(string $country, string $type, string $number, string $display): void
    {
        $reference = $this->document($country, $type, $number);
        $this->doctor->update(['rut' => $country === 'CL' ? $display : null]);
        $operation = $this->prepare();
        $this->sign($operation, ['password' => 'incorrect'])->assertUnprocessable();
        $this->sign($operation)->assertOk();
        $signature = $this->encounter->fresh()->signature;
        $raw = $signature->getRawOriginal();
        $versionRaw = $signature->documentVersion->getRawOriginal();
        $snapshot = app(EncounterSignatureVerifier::class)->verify($signature);
        $this->assertSame(4, $snapshot['schema_version']);
        $this->assertSame($reference, $snapshot['professional']['document']['reference']);
        $this->assertSame($country, $snapshot['professional']['document']['country']);
        $this->assertSame($display, $snapshot['professional']['document']['display']);
        $this->assertFalse($snapshot['professional']['document']['identity_verified']);
        $this->assertStringNotContainsString('lookup_hash', $signature->documentVersion->payload);
        $this->document('ES', 'passport', 'NEW0001');
        $this->doctor->update(['name' => 'Changed current name']);
        $html = view('customer.patient.record-workspace.pdf-signature', ['signature' => $signature])->render();
        $this->assertStringContainsString($display, $html);
        $this->assertStringNotContainsString('NEW0001', $html);
        $url = $this->root.'/patients/'.$this->patient->id.'/medical-record/export/';
        $fhir = $this->get($url.'fhir-r4')->assertOk()->getContent();
        $this->assertStringContainsString($snapshot['professional']['document']['normalized'], $fhir);
        $this->assertStringNotContainsString('Changed current name', $fhir);
        $this->assertStringNotContainsString('NEW0001', $fhir);
        $this->get($url.'pdfa')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($raw, $signature->fresh()->getRawOriginal());
        $this->assertSame($versionRaw, $signature->documentVersion->fresh()->getRawOriginal());
    }

    public function test_changed_document_or_name_invalidates_preview_but_new_preview_can_be_signed(): void
    {
        $this->document('AR', 'passport', '000OLD');
        $op = $this->prepare();
        $this->document('PE', 'national_id', '000NEW');
        $this->sign($op)->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->assertSame(1, (int) $this->encounter->fresh()->status);
        $op = $this->prepare();
        $this->doctor->update(['name' => 'Administratively corrected name']);
        $this->sign($op)->assertUnprocessable();
        $this->sign($this->prepare())->assertOk();
        $this->assertSame('Administratively corrected name', $this->encounter->fresh()->signature->identity['name']);
    }

    public function test_retired_last_document_cannot_silently_fall_back_to_legacy_rut(): void
    {
        $ref = $this->document('AR', 'passport', '000RET');
        $admin = $this->user('Administrador');
        app(IdentifierRepositoryFactory::class)->make()->retire(new IdentifierSubject('user', $this->doctor->id, $this->clinic->id), $admin, $ref, 'Administrative correction');
        $this->postJson($this->endpoint('signature-preview'), ['clinical_assessment' => 'test'])->assertStatus(409);
        $this->assertSame(0, $this->encounter->signatures()->count());
        $display = app(\App\Services\Signatures\SignerIdentitySnapshot::class)->capture($this->doctor, false);
        $this->assertSame('missing_primary', $display['document']['validation_status']);
    }

    public static function races(): array
    {
        return [['sign'], ['edit'], ['patient-sign'], ['patient-edit']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('races')]
    public function test_real_mysql_identity_edit_and_signature_share_the_same_lock(string $mode): void
    {
        $this->document('AR', 'passport', '000BEFORE');
        $admin = $this->user('Administrador');
        $operation = $this->prepare();
        DB::commit(); // All fixtures stay exclusively in this isolated schema.
        DB::beginTransaction();
        DB::table('customers')->where('id', $this->clinic->id)->lockForUpdate()->first();
        $gate = storage_path('framework/testing/sign-identity-'.\Illuminate\Support\Str::uuid());
        if (! is_dir(dirname($gate))) mkdir(dirname($gate), 0700, true);
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/international-signature-worker.php'),
            DB::connection()->getDatabaseName(), (string) $this->clinic->id, (string) $this->doctor->id, (string) $admin->id,
            (string) $this->branch->id, (string) $this->patient->id, (string) $this->encounter->id, $operation, $mode, $gate], base_path());
        $process->setTimeout(25);
        try {
            $process->start();
            $deadline = microtime(true) + 10;
            $waiting = 0;
            while ($waiting < 1 && microtime(true) < $deadline) {
                $waiting = (int) DB::selectOne('SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=? AND l.OBJECT_NAME=?', [DB::connection()->getDatabaseName(), 'customers'])->total;
                if (! $waiting) usleep(10000);
            }
            $this->assertSame(1, $waiting, 'Independent process must be waiting on the actual tenant row lock.');
            if ($mode === 'sign') {
                $this->document('PE', 'national_id', '000CHANGED');
            } elseif ($mode === 'patient-sign') {
                $this->patient->update(['name' => 'Concurrent patient correction']);
            } else {
                app(\App\Services\Signatures\EncounterSigningService::class)->sign($this->doctor, $this->patient->id, $this->encounter->id, $operation, 'correct-password', true);
            }
            DB::commit();
            $process->wait();
            $this->assertSame(0, $process->getExitCode());
            $this->assertSame(in_array($mode, ['sign', 'patient-sign'], true) ? 'stale' : 'edited', trim($process->getOutput()));
            if (in_array($mode, ['sign', 'patient-sign'], true)) {
                $this->assertSame(1, (int) $this->encounter->fresh()->status);
                $this->assertSame(0, $this->encounter->signatures()->count());
            } else {
                $snapshot = app(EncounterSignatureVerifier::class)->verify($this->encounter->fresh()->signature);
                $this->assertSame('000BEFORE', $snapshot['professional']['document']['normalized']);
                $primary = app(IdentifierRepositoryFactory::class)->make()->primary(new IdentifierSubject('user', $this->doctor->id, $this->clinic->id), $this->doctor);
                $this->assertSame($mode === 'patient-edit' ? '000BEFORE' : '000AFTER', $primary['number']);
                $this->assertSame('Signing Patient', $snapshot['patient']['name']);
                if ($mode === 'patient-edit') $this->assertSame('Concurrent patient correction', $this->patient->fresh()->name);
            }
        } finally {
            if ($process->isRunning()) $process->stop();
            if (DB::transactionLevel() > 0) DB::rollBack();
            if (is_file($gate.'.ready')) unlink($gate.'.ready');
        }
    }

    /** Build historical fixtures before closing them, never rewrite signed rows. */
    private function legacy(bool $addendum, int $originalSchema = 1): EncounterSignature
    {
        $snapshot = $addendum
            ? app(EncounterAddendumSnapshotBuilder::class)->build($this->encounter->fresh(), $this->doctor, ['type' => 'observation', 'content' => 'Historical addendum'])
            : app(EncounterSnapshotBuilder::class)->build($this->encounter, $this->doctor, ['clinical_assessment' => 'Historical original']);
        $snapshot['schema_version'] = $addendum ? 2 : $originalSchema;
        $snapshot['patient'] = ['id' => $this->patient->id, 'name' => $this->patient->name, 'rut' => $this->patient->rut];
        if ($snapshot['schema_version'] < 3) $snapshot['professional'] = ['id' => $this->doctor->id, 'name' => 'Historical signer', 'rut' => '12.345.678-5'];
        $payload = app(CanonicalContentSerializer::class)->serialize($snapshot);
        $parent = $addendum ? $this->encounter->fresh()->signature->document_version_id : null;
        $operation = app(SigningOperationManager::class)->prepare($this->doctor, $this->encounter, $payload, $addendum ? 'addendum' : 'original', $parent);
        $signature = app(SignedDocumentWriter::class)->persist($operation, $this->encounter, $this->doctor, $snapshot, $payload, [],
            $addendum ? 'addendum' : 'original', $addendum ? $snapshot['sequence'] : 1, 'historical-fixture', 'Synthetic historical consent', $parent);
        if (! $addendum) {
            $this->encounter->update(['status' => 2, 'ended_at' => $signature->signed_at]);
        }
        return $signature->fresh();
    }

    public function test_v1_original_v2_addendum_and_new_v3_addendum_keep_all_original_bytes(): void
    {
        $original = $this->legacy(false);
        $oldAddendum = $this->legacy(true);
        $originalBytes = $original->documentVersion->getRawOriginal();
        $oldBytes = $oldAddendum->documentVersion->getRawOriginal();
        $originalEvidence = $original->getRawOriginal();
        $oldEvidence = $oldAddendum->getRawOriginal();
        $this->document('AR', 'passport', '000ADD');
        $op = $this->postJson($this->endpoint('addenda/preview'), ['type' => 'complement', 'content' => 'New international addendum'])->assertOk()->json('operation_id');
        $this->postJson($this->endpoint('addenda'), ['operation_id' => $op, 'password' => 'correct-password', 'acceptance' => 1])->assertOk();
        $new = $this->encounter->fresh()->addendumSignatures->last();
        $snapshot = app(EncounterSignatureVerifier::class)->verify($new);
        $this->assertSame(4, $snapshot['schema_version']);
        $this->assertSame(3, $snapshot['sequence']);
        $this->assertSame($original->document_version_id, $snapshot['original_document']['id']);
        $this->assertSame($originalBytes, $original->documentVersion->fresh()->getRawOriginal());
        $this->assertSame($oldBytes, $oldAddendum->documentVersion->fresh()->getRawOriginal());
        $this->assertSame($originalEvidence, $original->fresh()->getRawOriginal());
        $this->assertSame($oldEvidence, $oldAddendum->fresh()->getRawOriginal());
        $this->assertSame(1, app(EncounterSignatureVerifier::class)->verify($original)['schema_version']);
        $this->assertSame(2, app(EncounterSignatureVerifier::class)->verify($oldAddendum)['schema_version']);
        $fhir = $this->get($this->root.'/patients/'.$this->patient->id.'/medical-record/export/fhir-r4')->assertOk()->json();
        $practitioners = collect($fhir['entry'])->pluck('resource')->where('resourceType', 'Practitioner');
        $this->assertCount(2, $practitioners);
        $this->assertCount(3, collect($fhir['entry'])->pluck('resource')->where('resourceType', 'DocumentReference'));
    }

    public function test_v3_original_accepts_a_v4_addendum_without_rewriting_patient_history(): void
    {
        $original = $this->legacy(false, 3);
        $bytes = $original->documentVersion->getRawOriginal();
        $this->patient->update(['name' => 'Current name changed']);
        $op = $this->postJson($this->endpoint('addenda/preview'), ['type' => 'observation', 'content' => 'New v4 addendum'])->assertOk()->json('operation_id');
        $this->postJson($this->endpoint('addenda'), ['operation_id' => $op, 'password' => 'correct-password', 'acceptance' => 1])->assertOk();
        $snapshot = app(EncounterSignatureVerifier::class)->verify($this->encounter->fresh()->addendumSignatures->last());
        $this->assertSame(4, $snapshot['schema_version']);
        $this->assertSame('Signing Patient', $snapshot['patient']['name']);
        $this->assertSame($bytes, $original->documentVersion->fresh()->getRawOriginal());
        $this->assertSame(3, app(EncounterSignatureVerifier::class)->verify($original)['schema_version']);
    }
}
