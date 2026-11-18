<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telemedicine_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('room_name', 255);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('patient_consent_at')->nullable();
            $table->string('patient_consent_version', 40)->nullable();
            $table->text('patient_consent_text')->nullable();
            $table->timestamp('patient_invitation_sent_at')->nullable();
            $table->timestamp('patient_whatsapp_invitation_sent_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('professional_id')->nullable();
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unique(['room_name'], 'telemedicine_sessions_room_name_unique');
            $table->index(['appointment_id'], 'telemedicine_sessions_appointment_id_foreign');
            $table->index(['created_by'], 'telemedicine_sessions_created_by_foreign');
            $table->index(['customer_id', 'status'], 'telemedicine_sessions_customer_id_status_index');
            $table->index(['patient_id'], 'telemedicine_sessions_patient_id_foreign');
            $table->index(['professional_id'], 'telemedicine_sessions_professional_id_foreign');
            $table->index(['clinic_branch_id'], 'telemedicine_sessions_clinic_branch_id_foreign');
            $table->index(['specialty_id'], 'telemedicine_sessions_specialty_id_foreign');
            $table->foreign(['appointment_id'], 'telemedicine_sessions_appointment_id_foreign')->references(['id'])->on('appointments')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['clinic_branch_id'], 'telemedicine_sessions_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['created_by'], 'telemedicine_sessions_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'telemedicine_sessions_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['patient_id'], 'telemedicine_sessions_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['professional_id'], 'telemedicine_sessions_professional_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['specialty_id'], 'telemedicine_sessions_specialty_id_foreign')->references(['id'])->on('specialties')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_sessions');
    }
};
