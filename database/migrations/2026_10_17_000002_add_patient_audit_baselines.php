<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('patients')
            ->select(['id', 'customer_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($patients) {
                $now = now();

                $rows = $patients->map(fn ($patient) => [
                    'customer_id' => $patient->customer_id,
                    'patient_id' => $patient->id,
                    'user_id' => null,
                    'action' => 'legacy',
                    'section' => 'Datos del paciente',
                    'changes' => null,
                    'created_at' => $patient->created_at ?? $now,
                    'updated_at' => $patient->created_at ?? $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('patient_audit_logs')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('patient_audit_logs')->where('action', 'legacy')->delete();
    }
};
