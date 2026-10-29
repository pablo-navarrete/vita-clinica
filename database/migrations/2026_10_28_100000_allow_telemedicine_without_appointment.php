<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemedicine_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->change();
            $table->foreignId('patient_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('clinic_branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('telemedicine_sessions')->whereNull('appointment_id')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan salas sin cita.');
        }
        Schema::table('telemedicine_sessions', function (Blueprint $table) {
            foreach (['patient_id', 'professional_id', 'clinic_branch_id', 'specialty_id'] as $column) $table->dropConstrainedForeignId($column);
            $table->dropColumn('expires_at');
            $table->unsignedBigInteger('appointment_id')->nullable(false)->change();
        });
    }
};
