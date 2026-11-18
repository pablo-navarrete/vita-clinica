<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('encounter_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('original_name', 255);
            $table->string('disk', 255)->default('local');
            $table->string('path', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['patient_id', 'sha256'], 'patient_documents_patient_id_sha256_unique');
            $table->index(['encounter_id'], 'patient_documents_encounter_id_foreign');
            $table->index(['clinic_branch_id'], 'patient_documents_clinic_branch_id_foreign');
            $table->index(['uploaded_by'], 'patient_documents_uploaded_by_foreign');
            $table->index(['customer_id', 'patient_id', 'created_at'], 'patient_documents_customer_id_patient_id_created_at_index');
            $table->foreign(['clinic_branch_id'], 'patient_documents_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'patient_documents_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['encounter_id'], 'patient_documents_encounter_id_foreign')->references(['id'])->on('encounters')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['patient_id'], 'patient_documents_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['uploaded_by'], 'patient_documents_uploaded_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
