<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('encounter_observations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('encounter_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->text('observation');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['encounter_id'], 'encounter_observations_encounter_id_foreign');
            $table->index(['user_id'], 'encounter_observations_user_id_foreign');
            $table->index(['clinic_branch_id'], 'encounter_observations_clinic_branch_id_foreign');
            $table->index(['customer_id', 'encounter_id', 'created_at'], 'encounter_observations_timeline_idx');
            $table->foreign(['clinic_branch_id'], 'encounter_observations_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'encounter_observations_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['encounter_id'], 'encounter_observations_encounter_id_foreign')->references(['id'])->on('encounters')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'encounter_observations_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_observations');
    }
};
