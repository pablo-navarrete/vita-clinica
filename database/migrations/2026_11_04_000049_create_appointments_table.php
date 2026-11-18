<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('professional_id');
            $table->integer('specialty_id')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->tinyInteger('status')->default('1');
            $table->string('modality', 20)->default('in_person');
            $table->timestamp('whatsapp_consent_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id'], 'appointments_customer_id_foreign');
            $table->index(['professional_id', 'start_at'], 'appointments_professional_id_start_at_index');
            $table->index(['patient_id'], 'appointments_patient_id_index');
            $table->index(['status'], 'appointments_status_index');
            $table->index(['clinic_branch_id'], 'appointments_clinic_branch_id_foreign');
            $table->foreign(['clinic_branch_id'], 'appointments_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'appointments_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['patient_id'], 'appointments_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['professional_id'], 'appointments_professional_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
