<?php

namespace Tests\Unit\Identification;

use PHPUnit\Framework\TestCase;

class PatientWriterArchitectureTest extends TestCase
{
    public function test_application_patient_writes_are_confined_to_the_registration_service(): void
    {
        $root = dirname(__DIR__, 3).'/app';
        $allowed = realpath($root.'/Services/Patients/PatientRegistrationService.php');
        $violations = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || $file->getRealPath() === $allowed) continue;
            $code = file_get_contents($file->getPathname());
            $symbols = ['Patient'];
            preg_match_all('/Patient\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $code, $aliases);
            $symbols = array_merge($symbols, $aliases[1]);
            foreach ($symbols as $symbol) {
                $class = preg_quote($symbol, '/');
                if (preg_match('/\b'.$class.'::\s*(?:query\(\)\s*->\s*)?(?:create|insert|upsert|firstOrCreate|firstOrNew|updateOrCreate)\s*\(/', $code)
                    || preg_match('/new\s+(?:\\\\?App\\\\Models\\\\)?'.$class.'\b/', $code)) $violations[] = $file->getPathname();
            }
            if (preg_match('/table\([\'"]patients[\'"]\)[^;]*(?:insert|upsert|updateOrInsert)\s*\(/is', $code)
                || preg_match('/insert\s+into\s+[`\'"]?patients\b/i', $code)
                || preg_match('/patients\(\)\s*->\s*(?:create|save|firstOrCreate)/', $code)) $violations[] = $file->getPathname();
        }
        $this->assertSame([], array_unique($violations), 'An application writer bypasses patient registration.');
    }
}
