<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('specialty_user', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('specialty_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('customer_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unique(['specialty_id', 'user_id', 'clinic_branch_id'], 'specialty_user_branch_unique');
            $table->index(['user_id'], 'specialty_user_user_id_foreign');
            $table->index(['clinic_branch_id'], 'specialty_user_clinic_branch_id_foreign');
            $table->index(['specialty_id'], 'specialty_user_specialty_id_index');
            $table->foreign(['clinic_branch_id'], 'specialty_user_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['specialty_id'], 'specialty_user_specialty_id_foreign')->references(['id'])->on('specialties')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'specialty_user_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialty_user');
    }
};
