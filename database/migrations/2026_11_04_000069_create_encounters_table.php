<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('professional_id')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('modality', 20)->default('in_person');
            $table->unsignedBigInteger('telemedicine_session_id')->nullable();
            $table->tinyInteger('status')->default('1');
            $table->text('chief_complaint')->nullable();
            $table->longText('clinical_assessment')->nullable();
            $table->longText('treatment_plan')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['patient_id'], 'encounters_patient_id_foreign');
            $table->index(['appointment_id'], 'encounters_appointment_id_foreign');
            $table->index(['customer_id'], 'encounters_customer_id_foreign');
            $table->index(['professional_id'], 'encounters_professional_id_foreign');
            $table->index(['specialty_id'], 'encounters_specialty_id_foreign');
            $table->index(['opened_by'], 'encounters_opened_by_foreign');
            $table->index(['created_by'], 'encounters_created_by_foreign');
            $table->index(['telemedicine_session_id'], 'encounters_telemedicine_session_id_foreign');
            $table->index(['clinic_branch_id'], 'encounters_clinic_branch_id_foreign');
            $table->foreign(['appointment_id'], 'encounters_appointment_id_foreign')->references(['id'])->on('appointments')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['clinic_branch_id'], 'encounters_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['created_by'], 'encounters_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'encounters_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['opened_by'], 'encounters_opened_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['patient_id'], 'encounters_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['professional_id'], 'encounters_professional_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['specialty_id'], 'encounters_specialty_id_foreign')->references(['id'])->on('specialties')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['telemedicine_session_id'], 'encounters_telemedicine_session_id_foreign')->references(['id'])->on('telemedicine_sessions')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
