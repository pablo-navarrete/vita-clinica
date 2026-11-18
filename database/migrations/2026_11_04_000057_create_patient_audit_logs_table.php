<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 32);
            $table->string('section', 100);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'patient_audit_logs_user_id_foreign');
            $table->index(['customer_id', 'patient_id', 'created_at'], 'patient_audit_logs_customer_id_patient_id_created_at_index');
            $table->index(['clinic_branch_id'], 'patient_audit_logs_clinic_branch_id_foreign');
            $table->index(['patient_id', 'clinic_branch_id', 'created_at'], 'patient_audit_branch_date_idx');
            $table->foreign(['clinic_branch_id'], 'patient_audit_logs_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'patient_audit_logs_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['patient_id'], 'patient_audit_logs_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'patient_audit_logs_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_audit_logs');
    }
};
