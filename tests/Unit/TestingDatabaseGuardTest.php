<?php

namespace Tests\Unit;

use App\Support\TestingDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestingDatabaseGuardTest extends TestCase
{
    #[DataProvider('unsafeDatabases')]
    public function test_live_and_recovery_databases_are_blocked_before_test_traits_run(string $driver, string $database): void
    {
        $this->expectException(\RuntimeException::class);
        TestingDatabaseGuard::assertIsolated($driver, $database);
    }

    public static function unsafeDatabases(): array
    {
        return [['mysql','vita_clinica'],['mysql','vita_recovered_20261115_162754'],['mysql',''],
            ['mysql','vita_testing; DROP DATABASE vita_clinica'],['sqlite','database/database.sqlite'],['pgsql','vita_testing']];
    }

    public function test_explicit_test_databases_remain_usable(): void
    {
        foreach ([['mysql','vita_testing'],['mysql','vita_test'],['mysql','vita_identification_test_20261115_abcd'],
            ['sqlite',':memory:'],['sqlite','C:/workspace/database/vita_testing.sqlite']] as [$driver,$database]) {
            TestingDatabaseGuard::assertIsolated($driver,$database);
            $this->addToAssertionCount(1);
        }
    }
}
