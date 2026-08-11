<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Estado
            $table->tinyInteger('status')->default(1);

            // Motivo de la consulta
            $table->string('reason')->nullable();

            // Observaciones
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['professional_id', 'start_at']);
            $table->index(['patient_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
