<?php
// Separate PHP process and MySQL connection for lock/concurrency integration tests.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $database, $userId, $encounterId, $operationId, $action, $barrier] = $argv;
if (!preg_match('/^vita_sign_test_[a-f0-9]+$/', $database)) exit(2);
config(['database.connections.mysql.database' => $database, 'session.driver' => 'array', 'cache.default' => 'array',
    'filesystems.disks.signed_encounters.root' => $barrier . '/files']);
Illuminate\Support\Facades\DB::purge('mysql');
$user = App\Models\User::findOrFail($userId);
$encounter = App\Models\Encounter::findOrFail($encounterId);
$app->instance('customer', $user->customer);
auth()->login($user);
session(['active_branch_customer_id' => $user->customer_id, 'active_branch_id' => $encounter->clinic_branch_id]);
file_put_contents($barrier . '/' . $action . '-' . getmypid() . '.ready', '1');
$deadline = microtime(true) + 15;
while (!file_exists($barrier . '/go')) { if (microtime(true) > $deadline) exit(3); usleep(10000); }
try {
    if ($action === 'addendum') {
        $app->make(App\Services\Signatures\EncounterAddendumService::class)->sign($user, $encounter->patient_id, $encounter->id, $operationId, 'correct-password', true);
    } elseif ($action === 'sign') {
        $app->make(App\Services\Signatures\EncounterSigningService::class)->sign($user, $encounter->patient_id, $encounter->id, $operationId, 'correct-password', true);
    } else {
        Illuminate\Support\Facades\DB::transaction(function () use ($encounter, $action, $user) {
            $row = App\Models\Encounter::lockForUpdate()->findOrFail($encounter->id);
            if ((int) $row->status !== 1) throw new RuntimeException('Already closed');
            // Hold the competing writer lock long enough to overlap the other process.
            usleep(200000);
            if ($action === 'cancel') $row->update(['status' => 3, 'ended_at' => now(), 'cancellation_reason' => 'Concurrency fixture']);
            elseif ($action === 'attachment') App\Models\PatientDocument::create([
                'customer_id' => $row->customer_id, 'patient_id' => $row->patient_id, 'encounter_id' => $row->id,
                'clinic_branch_id' => $row->clinic_branch_id, 'uploaded_by' => $user->id,
                'original_name' => 'concurrent.pdf', 'disk' => 'local', 'path' => 'test-only',
                'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => hash('sha256', 'x'),
            ]);
        });
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $exception) { echo json_encode(['ok' => false, 'type' => get_class($exception)]); }
