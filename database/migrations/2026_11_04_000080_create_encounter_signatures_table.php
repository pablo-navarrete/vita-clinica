<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('encounter_signatures', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('operation_id', 36);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('encounter_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('signature_asset_id')->nullable();
            $table->unsignedBigInteger('stamp_asset_id')->nullable();
            $table->string('method', 32);
            $table->string('mechanism_version', 24);
            $table->string('authentication_method', 32);
            $table->string('acceptance_version', 24);
            $table->boolean('accepted');
            $table->longText('identity');
            $table->char('content_hash', 64);
            $table->timestamp('authenticated_at', 6);
            $table->timestamp('signed_at', 6);
            $table->unique(['operation_id'], 'encounter_signatures_operation_id_unique');
            $table->unique(['document_version_id'], 'encounter_signatures_document_version_id_unique');
            $table->index(['encounter_id'], 'encounter_signatures_encounter_id_foreign');
            $table->index(['user_id'], 'encounter_signatures_user_id_foreign');
            $table->index(['signature_asset_id'], 'encounter_signatures_signature_asset_id_foreign');
            $table->index(['stamp_asset_id'], 'encounter_signatures_stamp_asset_id_foreign');
            $table->index(['customer_id', 'encounter_id'], 'encounter_signatures_customer_id_encounter_id_index');
            $table->foreign(['customer_id'], 'encounter_signatures_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['document_version_id'], 'encounter_signatures_document_version_id_foreign')->references(['id'])->on('encounter_document_versions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['encounter_id'], 'encounter_signatures_encounter_id_foreign')->references(['id'])->on('encounters')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['operation_id'], 'encounter_signatures_operation_id_foreign')->references(['id'])->on('encounter_signing_operations')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['signature_asset_id'], 'encounter_signatures_signature_asset_id_foreign')->references(['id'])->on('professional_signature_assets')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['stamp_asset_id'], 'encounter_signatures_stamp_asset_id_foreign')->references(['id'])->on('professional_signature_assets')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['user_id'], 'encounter_signatures_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_signatures');
    }
};
