<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_audit_logs', function (Blueprint $table) {
            $table->foreignId('clinic_branch_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('clinic_branches')
                ->nullOnDelete();
            $table->index(['patient_id', 'clinic_branch_id', 'created_at'], 'patient_audit_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('patient_audit_logs', function (Blueprint $table) {
            $table->dropIndex('patient_audit_branch_date_idx');
            $table->dropConstrainedForeignId('clinic_branch_id');
        });
    }
};
