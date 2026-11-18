<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_branch_user', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('clinic_branch_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['clinic_branch_id', 'user_id'], 'clinic_branch_user_clinic_branch_id_user_id_unique');
            $table->index(['user_id'], 'clinic_branch_user_user_id_foreign');
            $table->foreign(['clinic_branch_id'], 'clinic_branch_user_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'clinic_branch_user_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_branch_user');
    }
};
