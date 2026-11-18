<?php

namespace Tests\Feature;

use App\Models\{Customer, Specialty, User};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FileSpecialtyConcurrencyTest extends TestCase
{
    public function test_mysql_fresh_schema_and_parallel_assignments_create_one_local_reference(): void
    {
        $original = config('database.connections.mysql.database');
        $database = 'vita_specialty_test_'.bin2hex(random_bytes(6)); $processes = [];
        $barrier = storage_path('framework/testing/'.$database); mkdir($barrier, 0700, true);
        DB::statement("CREATE DATABASE `$database`");
        try {
            foreach (DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']) as $row) {
                $table = array_values((array)$row)[0];
                DB::statement("CREATE TABLE `$database`.`$table` LIKE `$original`.`$table`");
            }
            config(['database.connections.mysql.database' => $database]); DB::purge('mysql');
            $clinic = Customer::create(['name' => 'Test', 'legal_name' => 'Test', 'holder' => 'Test', 'rut' => 'test', 'phone' => '123', 'email' => 'test@example.test']);
            $branch = $clinic->branches()->create(['name' => 'Principal', 'status' => 1]);
            $users = collect([1, 2])->map(function ($i) use ($clinic, $branch) {
                $u = User::create(['customer_id' => $clinic->id, 'name' => 'Test', 'rut' => 'test'.$i, 'phone' => '123',
                    'email' => 'test'.$i.'@example.test', 'password' => 'password-test', 'status' => 1]);
                $u->branches()->attach($branch->id, ['status' => 1]); return $u;
            });
            $legacy = Specialty::create(['customer_id' => $clinic->id, 'name' => 'PEDIATRIA', 'status' => 1]);
            $unknown = Specialty::create(['customer_id' => $clinic->id, 'name' => 'Unknown legacy', 'status' => 0]);
            $users[0]->specialties()->attach($legacy->id, ['customer_id' => $clinic->id, 'clinic_branch_id' => $branch->id]);
            $pivot = DB::table('specialty_user')->first();
            $before = DB::table('specialties')->orderBy('id')->get();
            $this->assertNull($unknown->fresh()->catalog_id);
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('specialties', ['catalog_id', 'public_visible']));
            $this->assertEquals($pivot, DB::table('specialty_user')->first());
            $id = (int) array_search('Cardiología', config('specialties.catalog'), true);
            foreach ($users as $user) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/file-specialty-worker.php'), $database,
                    (string)$user->id, (string)$branch->id, (string)$id, $barrier]);
                $p->setTimeout(30); $p->start(); $processes[] = $p;
            }
            $deadline = microtime(true) + 15;
            while (count(glob($barrier.'/*.ready')) < 2 && microtime(true) < $deadline) usleep(10000);
            $this->assertCount(2, glob($barrier.'/*.ready')); file_put_contents($barrier.'/go', '1');
            foreach ($processes as $p) { $p->wait(); $this->assertTrue($p->isSuccessful(), $p->getErrorOutput()); }
            $this->assertSame(1, Specialty::where('customer_id', $clinic->id)->where('catalog_id', $id)->count());
            $local = Specialty::where('catalog_id', $id)->firstOrFail();
            $this->assertSame(2, DB::table('specialty_user')->where('specialty_id', $local->id)->count());
            $this->assertTrue(Specialty::whereKey($legacy->id)->exists());
            foreach ($before as $row) $this->assertEquals((array)$row, (array)DB::table('specialties')->where('id', $row->id)->first());
        } finally {
            foreach ($processes as $p) if ($p->isRunning()) $p->stop();
            DB::disconnect('mysql'); config(['database.connections.mysql.database' => $original]); DB::purge('mysql');
            // Only the verified, generated database name is removed.
            DB::statement("DROP DATABASE IF EXISTS `$database`");
        }
    }
}
