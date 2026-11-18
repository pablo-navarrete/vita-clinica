<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('encounter_signing_operations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('encounter_id');
            $table->unsignedBigInteger('user_id');
            $table->char('content_hash', 64);
            $table->longText('payload');
            $table->string('status', 16)->default('prepared');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('purpose', 24)->default('original');
            $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->primary(['id']);
            $table->index(['customer_id'], 'encounter_signing_operations_customer_id_foreign');
            $table->index(['encounter_id'], 'encounter_signing_operations_encounter_id_foreign');
            $table->index(['user_id'], 'encounter_signing_operations_user_id_foreign');
            $table->index(['expires_at'], 'encounter_signing_operations_expires_at_index');
            $table->index(['parent_version_id'], 'encounter_signing_operations_parent_version_id_foreign');
            $table->foreign(['customer_id'], 'encounter_signing_operations_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['encounter_id'], 'encounter_signing_operations_encounter_id_foreign')->references(['id'])->on('encounters')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_version_id'], 'encounter_signing_operations_parent_version_id_foreign')->references(['id'])->on('encounter_document_versions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['user_id'], 'encounter_signing_operations_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_signing_operations');
    }
};
