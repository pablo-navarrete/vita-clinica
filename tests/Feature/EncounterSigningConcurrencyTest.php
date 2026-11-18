<?php
namespace Tests\Feature;

use App\Models\{ClinicBranch, Customer, Encounter, EncounterSignature, Patient, Role, User};
use App\Services\Signatures\EncounterSigningService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EncounterSigningConcurrencyTest extends TestCase
{
    public function test_real_mysql_processes_serialize_double_sign_cancel_and_attachment_writers(): void
    {
        $original = config('database.connections.mysql.database');
        $database = 'vita_sign_test_' . bin2hex(random_bytes(6));
        $barrier = storage_path('framework/testing/' . $database);
        if (!is_dir($barrier)) mkdir($barrier, 0700, true);
        DB::statement("CREATE DATABASE `{$database}`");
        try {
            // Copy schema only, never patient data. LIKE deliberately does not copy foreign keys.
            foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
                $table = array_values((array) $row)[0];
                DB::statement("CREATE TABLE `{$database}`.`{$table}` LIKE `{$original}`.`{$table}`");
            }
            config(['database.connections.mysql.database' => $database]); DB::purge('mysql');

            (require database_path('migrations/2026_11_04_000100_install_integrity_triggers.php'))->up();
            $clinic = Customer::create(['name' => 'Concurrency', 'legal_name' => 'Concurrency', 'domain' => 'concurrency', 'holder' => 'Test', 'rut' => 'test', 'phone' => '123', 'email' => 'concurrency@example.test', 'status' => 1]);
            $branch = ClinicBranch::create(['customer_id' => $clinic->id, 'name' => 'Concurrent branch', 'status' => 1]);
            $doctor = User::create(['customer_id' => $clinic->id, 'name' => 'Doctor', 'rut' => 'test', 'phone' => '123', 'email' => 'doctor@example.test', 'password' => 'correct-password', 'status' => 1]);
            $doctor->branches()->attach($branch->id, ['status' => 1]);
            $role = Role::create(['customer_id' => $clinic->id, 'name' => 'Doctor']);
            $doctor->branchRoles()->attach($role->id, ['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id]);
            $patient = Patient::create(['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id, 'name' => 'Test', 'rut' => 'test', 'gender' => 'male', 'birth_date' => '1990-01-01']);
            $this->app->instance('customer', $clinic); $this->actingAs($doctor);
            session(['active_branch_customer_id' => $clinic->id, 'active_branch_id' => $branch->id]);
            foreach (['sign', 'cancel', 'attachment'] as $competitor) {
                $encounter = Encounter::create(['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id, 'patient_id' => $patient->id,
                    'professional_id' => $doctor->id, 'status' => 1, 'started_at' => now(), 'modality' => 'in_person']);
                $op = app(EncounterSigningService::class)->prepare($doctor, $patient->id, $encounter->id, ['clinical_assessment' => 'Concurrency diagnosis']);
                $directory = $barrier . '/' . $competitor; mkdir($directory, 0700);
                $processes = [];
                foreach (['sign', $competitor] as $index => $action) {
                    $operationId = $competitor === 'sign' && $index === 1
                        ? app(EncounterSigningService::class)->prepare($doctor, $patient->id, $encounter->id, ['clinical_assessment' => 'Concurrency diagnosis'])->id : $op->id;
                    $process = new Process([PHP_BINARY, base_path('tests/Support/encounter-signing-worker.php'), $database, (string) $doctor->id, (string) $encounter->id, $operationId, $action, $directory]);
                    $process->setTimeout(25); $process->start(); $processes[] = $process;
                }
                $deadline = microtime(true) + 15;
                while (count(glob($directory . '/*.ready')) < 2 && microtime(true) < $deadline) usleep(10000);
                $this->assertCount(2, glob($directory . '/*.ready'), implode('', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
                file_put_contents($directory . '/go', '1');
                $results = [];
                foreach ($processes as $process) {
                    $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                    $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                }
                $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])), json_encode($results));
                $count = EncounterSignature::where('encounter_id', $encounter->id)->count();
                $this->assertLessThanOrEqual(1, $count);
                $this->assertSame((int) $encounter->fresh()->status === 2 ? 1 : 0, $count);
                if ($competitor === 'sign') $this->assertSame(1, $count);
                if ($competitor === 'attachment' && $count) $this->assertSame(0, $encounter->documents()->count());
            }
            $encounter = Encounter::create(['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id, 'patient_id' => $patient->id,
                'professional_id' => $doctor->id, 'status' => 1, 'started_at' => now(), 'modality' => 'in_person']);
            $originalOp = app(EncounterSigningService::class)->prepare($doctor, $patient->id, $encounter->id, ['clinical_assessment' => 'Original preserved']);
            $originalSignature = app(EncounterSigningService::class)->sign($doctor, $patient->id, $encounter->id, $originalOp->id, 'correct-password', true);
            $originalRow = $encounter->fresh()->getRawOriginal();
            $originalVersion = $originalSignature->documentVersion->getRawOriginal();
            foreach (['distinct_addenda', 'same_addendum'] as $scenario) {
                $service = app(\App\Services\Signatures\EncounterAddendumService::class);
                $first = $service->prepare($doctor, $patient->id, $encounter->id, ['type' => 'observation', 'content' => 'Concurrent first']);
                $second = $scenario === 'same_addendum' ? $first : $service->prepare($doctor, $patient->id, $encounter->id, ['type' => 'complement', 'content' => 'Concurrent second']);
                $directory = $barrier . '/' . $scenario; mkdir($directory, 0700);
                $processes = [];
                foreach ([$first, $second] as $operation) {
                    $process = new Process([PHP_BINARY, base_path('tests/Support/encounter-signing-worker.php'), $database, (string) $doctor->id, (string) $encounter->id, $operation->id, 'addendum', $directory]);
                    $process->setTimeout(25); $process->start(); $processes[] = $process;
                }
                $deadline = microtime(true) + 15;
                while (count(glob($directory . '/*.ready')) < 2 && microtime(true) < $deadline) usleep(10000);
                $this->assertCount(2, glob($directory . '/*.ready'));
                file_put_contents($directory . '/go', '1');
                $results = [];
                foreach ($processes as $process) {
                    $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                    $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                }
                $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])), json_encode($results));
                $this->assertSame($originalRow, $encounter->fresh()->getRawOriginal());
                $this->assertSame($originalVersion, $originalSignature->documentVersion->fresh()->getRawOriginal());
            }
            $this->assertSame([2, 3], $encounter->fresh()->addendumSignatures->map(fn ($s) => (int) $s->documentVersion->version)->all());
        } finally {
            DB::disconnect('mysql'); config(['database.connections.mysql.database' => $original]); DB::purge('mysql');
            // The name is generated above and has no relationship to a tenant database.
            DB::statement("DROP DATABASE IF EXISTS `{$database}`");
        }
    }
}
