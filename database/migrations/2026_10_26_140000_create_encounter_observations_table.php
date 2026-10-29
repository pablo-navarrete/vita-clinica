<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinic_branch_id')->nullable()->constrained('clinic_branches')->nullOnDelete();
            $table->text('observation');
            $table->timestamps();

            $table->index(['customer_id', 'encounter_id', 'created_at'], 'encounter_observations_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_observations');
    }
};
