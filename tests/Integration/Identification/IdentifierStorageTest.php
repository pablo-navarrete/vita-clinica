<?php

namespace Tests\Integration\Identification;

use App\Models\Customer;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Identification\Storage\IdentifierCrypto;
use App\Services\Identification\Storage\IdentifierRepository;
use App\Services\Identification\Storage\IdentifierSubject;
use App\Services\Identification\Storage\VitaIdentifierAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\IdentifierStorageKeys;
use Tests\TestCase;

/** Prepared only. Never migrates a database and is outside the default test suites. */
class IdentifierStorageTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes') {
            $this->markTestSkipped('Requires explicit schema approval; no migrations are executed by this test.');
        }
        $database = DB::connection()->getDatabaseName();
        if (! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', $database)) {
            $this->fail('Use a dedicated vita_identification_test_* MySQL schema.');
        }
        foreach (['customer_identifiers', 'user_identifiers', 'patient_identifiers'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->fail('Approved migrations must already have been applied externally.');
            }
        }
        DB::beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function clinic(): array
    {
        $key = Str::lower(Str::random(16));
        $customer = Customer::create(['name' => 'Identifier test', 'legal_name' => 'Test', 'holder' => 'Test', 'rut' => 'legacy-'.$key, 'domain' => 'ident-'.$key, 'email' => $key.'@test.test', 'phone' => '123', 'status' => 1]);
        $branch = $customer->branches()->create(['name' => 'Principal', 'status' => true]);
        $actor = User::create(['customer_id' => $customer->id, 'name' => 'Identifier admin', 'rut' => 'legacy-'.$key, 'email' => $key.'@admin.test', 'phone' => '123', 'password' => 'synthetic-password', 'status' => 1]);
        $role = Role::create(['customer_id' => $customer->id, 'name' => 'Administrador', 'permissions_configured' => false]);
        $actor->branches()->attach($branch->id, ['status' => true]);
        $actor->branchRoles()->attach($role->id, ['customer_id' => $customer->id, 'clinic_branch_id' => $branch->id]);
        $actor->syncClinicRoleMirror($customer->id);
        session(['active_branch_customer_id' => $customer->id, 'active_branch_id' => $branch->id]);
        app()->instance('customer', $customer);
        $patient = Patient::create(['customer_id' => $customer->id, 'clinic_branch_id' => $branch->id, 'name' => 'Identifier patient', 'rut' => 'legacy-'.$key, 'gender' => 'male']);

        return [$customer, $actor, $patient, $branch];
    }

    private function repository(string $version = 'v1'): IdentifierRepository
    {
        return new IdentifierRepository(DB::connection(), new IdentifierCrypto(IdentifierStorageKeys::config($version)), new VitaIdentifierAuthorizer);
    }

    public function test_store_exact_lookup_primary_retirement_and_legacy_rut_are_preserved(): void
    {
        [$customer,$actor,$patient] = $this->clinic();
        $scope = new IdentifierSubject('patient', $patient->id, $customer->id);
        $repo = $this->repository();
        $before = $patient->fresh()->getAttributes();
        $first = $repo->create($scope, $actor, 'CL', 'national_id', '12.345.678-5', 'natural_person', true);
        $second = $repo->create($scope, $actor, 'AR', 'passport', '001Ab-12');
        $this->assertSame([$first], $repo->findExact($scope, $actor, 'CL', 'national_id', '123456785'));
        $row = DB::table('patient_identifiers')->where('record_uuid', $first)->first();
        $this->assertStringNotContainsString('123456785', $row->encrypted_payload);
        $this->assertSame('12.345.678-5', $repo->read($scope, $actor, $first)['original']);
        $repo->setPrimary($scope, $actor, $second);
        $this->assertSame(1, DB::table('patient_identifiers')->where('patient_id', $patient->id)->where('is_primary', true)->count());
        $repo->retire($scope, $actor, $second, 'Cambio de documento');
        $this->assertSame([], $repo->findExact($scope, $actor, 'AR', 'passport', '001Ab-12'));
        $this->assertSame('001Ab-12', $repo->read($scope, $actor, $second)['original']);
        $this->assertSame($before, $patient->fresh()->getAttributes());
    }

    public function test_patient_document_is_reserved_across_retirement_within_clinic(): void
    {
        [$c,$a,$p,$b] = $this->clinic();
        $repo = $this->repository();
        $one = new IdentifierSubject('patient', $p->id, $c->id);
        $ref = $repo->create($one, $a, 'CL', 'national_id', '123456785');
        $repo->retire($one, $a, $ref, 'Retiro de prueba');
        $second = Patient::create(['customer_id' => $c->id, 'clinic_branch_id' => $b->id, 'name' => 'Second', 'rut' => Str::uuid(), 'gender' => 'male']);
        $this->expectException(\InvalidArgumentException::class);
        $repo->create(new IdentifierSubject('patient', $second->id, $c->id), $a, 'CL', 'tax_id', '12.345.678-5');
    }

    public function test_same_patient_document_is_allowed_in_distinct_clinics_and_foreign_actor_is_denied(): void
    {
        [$one,$actorOne,$patientOne] = $this->clinic();
        $this->repository()->create(new IdentifierSubject('patient', $patientOne->id, $one->id), $actorOne, 'AR', 'national_id', '00123');
        [$two,$actorTwo,$patientTwo] = $this->clinic();
        $scope = new IdentifierSubject('patient', $patientTwo->id, $two->id);
        $this->repository()->create($scope, $actorTwo, 'AR', 'national_id', '00123');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->repository()->findExact($scope, $actorOne, 'AR', 'national_id', '00123');
    }

    public function test_rekey_preserves_plaintext_and_blocks_stale_version_writers(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $ref = $this->repository()->create($scope, $a, 'AR', 'national_id', '00123');
        $payload = $this->repository()->read($scope, $a, $ref);
        $this->assertSame([$ref], $this->repository('v2')->findExact($scope, $a, 'AR', 'national_id', '00123'));
        $this->assertSame($payload, $this->repository('v2')->read($scope, $a, $ref));
        $this->assertSame(1, $this->repository('v2')->rotateScope($scope, $a));
        $this->assertSame($payload['original'], $this->repository('v2')->read($scope, $a, $ref)['original']);
        $this->assertSame([$ref], $this->repository('v2')->findExact($scope, $a, 'AR', 'national_id', '00123'));
        $this->expectException(\RuntimeException::class);
        $this->repository()->create($scope, $a, 'AR', 'passport', 'DIFFERENT');
    }

    public function test_rollback_refuses_to_destroy_even_retired_identifier_records(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $ref = $this->repository()->create($scope, $a, 'AR', 'national_id', '00123');
        $this->repository()->retire($scope, $a, $ref, 'Retiro');
        $migration = require database_path('migrations/2026_11_05_000300_create_international_identifier_storage.php');
        try {
            $migration->down();
            $this->fail('Data must not be dropped');
        } catch (\LogicException) {
            $this->assertTrue(Schema::hasTable('patient_identifiers'));
            $this->assertSame(1, DB::table('patient_identifiers')->where('record_uuid', $ref)->count());
        }
    }

    public function test_database_rejects_owner_tenant_reassignment(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $this->repository()->create($scope, $a, 'AR', 'national_id', '00123');
        [$other] = $this->clinic();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('patients')->where('id', $p->id)->update(['customer_id' => $other->id]);
    }

    private function assertSqlRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('MySQL must enforce this constraint.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23000', '45000', 'HY000']);
        }
    }

    public function test_mysql_enforces_claim_primary_foreign_keys_and_no_physical_deletion(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $repo = $this->repository();
        $ref = $repo->create($scope, $a, 'CL', 'national_id', '123456785', 'natural_person', true);
        $row = (array) DB::table('patient_identifiers')->where('record_uuid', $ref)->first();
        unset($row['id'],$row['primary_owner_id']);
        $duplicate = array_replace($row, ['record_uuid' => (string) Str::uuid(), 'is_primary' => false]);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($duplicate));
        $another = array_replace($row, ['record_uuid' => (string) Str::uuid(), 'lookup_hash' => str_repeat('a', 64), 'claim_hash' => str_repeat('a', 64)]);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($another));
        $foreign = array_replace($duplicate, ['patient_id' => PHP_INT_MAX]);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($foreign));
        $invalidActor = array_replace($another, ['is_primary' => false, 'created_by' => PHP_INT_MAX]);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($invalidActor));
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->where('record_uuid', $ref)->delete());
        $this->assertSqlRejected(fn () => DB::table('patients')->where('id', $p->id)->delete());
        $this->assertSqlRejected(fn () => DB::table('users')->where('id', $a->id)->delete());
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->where('record_uuid', $ref)->update(['issuing_country_code' => 'AR', 'revision' => 2]));
        $repo->retire($scope, $a, $ref, 'Preservar reserva');
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($duplicate));
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->where('record_uuid', $ref)->update(['is_primary' => true, 'revision' => 3]));
        $this->assertSame(1, DB::table('patient_identifiers')->where('patient_id', $p->id)->count());
    }

    public function test_sql_rejects_valid_owner_from_another_clinic_even_with_nonconflicting_hash(): void
    {
        [$first,$actor,$patient] = $this->clinic();
        $ref = $this->repository()->create(new IdentifierSubject('patient', $patient->id, $first->id), $actor, 'AR', 'national_id', '00123');
        [$second,$otherActor,$otherPatient] = $this->clinic();
        $row = (array) DB::table('patient_identifiers')->where('record_uuid', $ref)->first();
        unset($row['id'],$row['primary_owner_id']);
        $row = array_replace($row, ['record_uuid' => (string) Str::uuid(), 'patient_id' => $otherPatient->id, 'lookup_hash' => str_repeat('b', 64), 'claim_hash' => str_repeat('b', 64)]);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert($row));
        $this->assertSame(0, DB::table('patient_identifiers')->where('patient_id', $otherPatient->id)->count());
    }

    public function test_quarantined_history_can_keep_conflicts_but_cannot_become_primary_or_claim_identity(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $ref = $this->repository()->create($scope, $a, 'AR', 'national_id', '00123');
        $row = (array) DB::table('patient_identifiers')->where('record_uuid', $ref)->first();
        unset($row['id'],$row['primary_owner_id']);
        $legacy = array_replace($row, ['record_uuid' => (string) Str::uuid(), 'record_state' => 'legacy_conflict',
            'source_kind' => 'legacy', 'validation_status' => 'legacy_unverified', 'claim_hash' => null,
            'legacy_source_ref' => 'patients:'.$p->id.':rut', 'legacy_classification' => 'ambiguous']);
        DB::table('patient_identifiers')->insert($legacy);
        $second = array_replace($legacy, ['record_uuid' => (string) Str::uuid(), 'legacy_source_ref' => 'patients:other:rut']);
        DB::table('patient_identifiers')->insert($second);
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert(array_replace($second, ['record_uuid' => (string) Str::uuid(), 'legacy_source_ref' => 'patients:third:rut', 'is_primary' => true])));
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert(array_replace($second, ['record_uuid' => (string) Str::uuid(), 'legacy_source_ref' => 'patients:third:rut', 'claim_hash' => $row['claim_hash']])));
        $this->assertSqlRejected(fn () => DB::table('patient_identifiers')->insert(array_replace($second, ['record_uuid' => (string) Str::uuid()])));
        $this->assertSame(3, DB::table('patient_identifiers')->where('patient_id', $p->id)->count());
        $this->assertSame([$ref], $this->repository()->findExact($scope, $a, 'AR', 'national_id', '00123'));
        // These deliberately low-level fixtures test SQL quarantine only, not a historical importer.
    }

    public function test_users_and_customers_do_not_gain_cross_owner_uniqueness(): void
    {
        [$one,$actor,$patient] = $this->clinic();
        [$two,$other] = $this->clinic();
        // Storage-only authorization fixture: access policy itself is covered by the tenant tests.
        $authorization = new class implements \App\Services\Identification\Storage\IdentifierAuthorizer
        {
            public function authorize(User $actor, IdentifierSubject $subject, object $owner, bool $write): void {}
        };
        $repo = new IdentifierRepository(DB::connection(), new IdentifierCrypto(IdentifierStorageKeys::config()), $authorization);
        foreach ([$one, $two] as $customer) {
            $scope = new IdentifierSubject('customer', $customer->id, $customer->id);
            $ref = $repo->create($scope, $actor, 'CL', 'tax_id', '12.345.678-5', 'legal_entity', true);
            $this->assertSame([$ref], $repo->findExact($scope, $actor, 'CL', 'tax_id', '123456785', 'legal_entity'));
        }
        $extra = User::create(['customer_id' => $one->id, 'name' => 'Another account', 'rut' => 'legacy-'.Str::uuid(), 'email' => Str::uuid().'@test.test', 'password' => 'synthetic-password', 'status' => 1]);
        foreach ([$actor, $extra, $other] as $user) {
            $scope = new IdentifierSubject('user', $user->id, $user->customer_id);
            $ref = $repo->create($scope, $actor, 'AR', 'passport', '00123');
            $this->assertSame([$ref], $repo->findExact($scope, $actor, 'AR', 'passport', '00123'));
        }
    }

    public function test_failed_rotation_rolls_back_already_prepared_rows(): void
    {
        [$c,$a,$p] = $this->clinic();
        $scope = new IdentifierSubject('patient', $p->id, $c->id);
        $first = $this->repository()->create($scope, $a, 'AR', 'national_id', '00123');
        $second = $this->repository()->create($scope, $a, 'AR', 'passport', '00123');
        $before = (array) DB::table('patient_identifiers')->where('record_uuid', $first)->first();
        DB::table('patient_identifiers')->where('record_uuid', $second)->update(['encrypted_payload' => '{"key_version":"v1"}', 'revision' => 2]);
        try {
            $this->repository('v2')->rotateScope($scope, $a);
            $this->fail('Tampered payload must abort rotation');
        } catch (\RuntimeException) {
            $this->assertSame($before, (array) DB::table('patient_identifiers')->where('record_uuid', $first)->first());
        }
        $this->assertSame('00123', $this->repository()->read($scope, $a, $first)['original']);
    }

    public function test_exact_hmac_lookup_preserves_foreign_case_accents_and_initial_zeroes_under_database_collation(): void
    {
        [$customer,$actor,$patient] = $this->clinic();
        $scope = new IdentifierSubject('patient', $patient->id, $customer->id);
        $repo = $this->repository();
        $values = ['001Ab-é', '001ab-é', '001Ab-e', '1Ab-é'];
        foreach ($values as $value) {
            $references[$value] = $repo->create($scope, $actor, 'AR', 'national_id', $value);
        }
        foreach ($values as $value) {
            $this->assertSame([$references[$value]], $repo->findExact($scope, $actor, 'AR', 'national_id', $value));
            $this->assertSame($value, $repo->read($scope, $actor, $references[$value])['normalized']);
        }
        $this->assertSame(4, DB::table('patient_identifiers')->where('patient_id', $patient->id)->distinct()->count('lookup_hash'));
        $columns = DB::select('SELECT COLUMN_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME IN (?,?)', [DB::connection()->getDatabaseName(), 'patient_identifiers', 'lookup_hash', 'claim_hash']);
        foreach ($columns as $column) {
            $this->assertSame('ascii_bin', $column->COLLATION_NAME);
        }
    }

    public function test_real_mysql_parallel_creates_produce_one_claim(): void
    {
        [$c,$a,$p,$b] = $this->clinic();
        $second = Patient::create(['customer_id' => $c->id, 'clinic_branch_id' => $b->id, 'name' => 'Concurrent', 'rut' => Str::uuid(), 'gender' => 'male']);
        DB::commit();
        $this->transactionStarted = false; // Committed synthetic fixtures remain only in this approved test schema.
        $gate = storage_path('framework/testing/ident-gate-'.Str::uuid());
        if (! is_dir(dirname($gate))) {
            mkdir(dirname($gate), 0700, true);
        }
        $workers = [];
        try {
            foreach ([$p->id, $second->id] as $id) {
                $worker = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/identifier-storage-worker.php'), DB::connection()->getDatabaseName(), (string) $c->id, (string) $a->id, (string) $b->id, (string) $id, $gate], base_path());
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while ((! is_file($gate.'.'.$p->id.'.ready') || ! is_file($gate.'.'.$second->id.'.ready')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($gate.'.'.$p->id.'.ready');
            $this->assertFileExists($gate.'.'.$second->id.'.ready');
            DB::beginTransaction();
            DB::table('customers')->where('id', $c->id)->lockForUpdate()->first();
            touch($gate);
            $waiting = 0;
            $deadline = microtime(true) + 8;
            while ($waiting < 2 && microtime(true) < $deadline) {
                $waiting = (int) DB::selectOne('SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=? AND l.OBJECT_NAME=?', [DB::connection()->getDatabaseName(), 'customers'])->total;
                if ($waiting < 2) {
                    usleep(10000);
                }
            }
            DB::commit();
            $this->assertGreaterThanOrEqual(2, $waiting, 'Both independent writers must be concurrently waiting on the actual MySQL row lock.');
            foreach ($workers as $worker) {
                $worker->wait();
            }
            $results = array_map(fn ($worker) => trim($worker->getOutput()), $workers);
            sort($results);
            $this->assertSame(['created', 'duplicate'], $results);
            $this->assertSame(1, DB::table('patient_identifiers')->where('customer_id', $c->id)->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }if (is_file($gate)) {
                unlink($gate);
            }
            foreach ([$p->id, $second->id] as $id) {
                if (is_file($gate.'.'.$id.'.ready')) {
                    unlink($gate.'.'.$id.'.ready');
                }
            }
        }
    }
}
