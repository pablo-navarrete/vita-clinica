<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->string('section', 100);
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_audit_logs');
    }
};
