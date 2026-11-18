<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clinic_activity_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('module', 80);
            $table->string('action', 30);
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->text('summary');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'clinic_activity_logs_user_id_foreign');
            $table->index(['customer_id', 'created_at'], 'clinic_activity_logs_customer_id_created_at_index');
            $table->index(['customer_id', 'module', 'created_at'], 'clinic_activity_logs_customer_id_module_created_at_index');
            $table->index(['customer_id', 'user_id', 'created_at'], 'clinic_activity_logs_customer_id_user_id_created_at_index');
            $table->index(['clinic_branch_id'], 'clinic_activity_logs_clinic_branch_id_foreign');
            $table->index(['customer_id', 'clinic_branch_id', 'created_at'], 'activity_logs_branch_date_idx');
            $table->foreign(['clinic_branch_id'], 'clinic_activity_logs_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'clinic_activity_logs_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'clinic_activity_logs_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_activity_logs');
    }
};
