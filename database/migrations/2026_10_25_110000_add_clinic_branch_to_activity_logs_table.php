<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_activity_logs', function (Blueprint $table) {
            $table->foreignId('clinic_branch_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('clinic_branches')
                ->nullOnDelete();
            $table->index(['customer_id', 'clinic_branch_id', 'created_at'], 'activity_logs_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_branch_date_idx');
            $table->dropConstrainedForeignId('clinic_branch_id');
        });
    }
};
