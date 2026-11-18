<?php

namespace App\Support;

final class TestingDatabaseGuard
{
    public static function assertIsolated(string $driver, string $database): void
    {
        $allowed = $driver === 'mysql'
            ? preg_match('/\Avita_(?:identification_)?test(?:ing)?(?:_[a-z0-9_]+)?\z/', $database) === 1
            : ($driver === 'sqlite' && ($database === ':memory:' || basename(str_replace('\\', '/', $database)) === 'vita_testing.sqlite'));
        if (!$allowed) {
            throw new \RuntimeException('Tests blocked: configure an isolated vita_testing database. The application database must never be used for tests.');
        }
    }
}
