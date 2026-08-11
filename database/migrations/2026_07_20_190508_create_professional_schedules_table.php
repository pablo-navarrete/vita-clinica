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
        Schema::create('professional_schedules', function (Blueprint $table) {
            $table->id();
             // Tenant
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            // Profesional (User)
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 0=Domingo, 1=Lunes ... 6=Sábado
            $table->unsignedTinyInteger('day_of_week');

            // Horario
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
    }
};
