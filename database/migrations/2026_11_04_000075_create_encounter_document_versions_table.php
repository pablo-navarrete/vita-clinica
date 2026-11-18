<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('encounter_document_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('encounter_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('clinic_branch_id');
            $table->unsignedInteger('version');
            $table->string('kind', 24)->default('original');
            $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->unsignedSmallInteger('schema_version')->default('1');
            $table->longText('payload');
            $table->char('sha256', 64);
            $table->longText('attachment_manifest');
            $table->timestamp('created_at');
            $table->unsignedBigInteger('rectifies_version_id')->nullable();
            $table->unique(['encounter_id', 'version'], 'encounter_document_versions_encounter_id_version_unique');
            $table->index(['customer_id'], 'encounter_document_versions_customer_id_foreign');
            $table->index(['patient_id'], 'encounter_document_versions_patient_id_foreign');
            $table->index(['clinic_branch_id'], 'encounter_document_versions_clinic_branch_id_foreign');
            $table->index(['parent_version_id'], 'encounter_document_versions_parent_version_id_foreign');
            $table->index(['rectifies_version_id'], 'encounter_document_versions_rectifies_version_id_foreign');
            $table->foreign(['clinic_branch_id'], 'encounter_document_versions_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_id'], 'encounter_document_versions_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['encounter_id'], 'encounter_document_versions_encounter_id_foreign')->references(['id'])->on('encounters')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_version_id'], 'encounter_document_versions_parent_version_id_foreign')->references(['id'])->on('encounter_document_versions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['patient_id'], 'encounter_document_versions_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['rectifies_version_id'], 'encounter_document_versions_rectifies_version_id_foreign')->references(['id'])->on('encounter_document_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_document_versions');
    }
};
