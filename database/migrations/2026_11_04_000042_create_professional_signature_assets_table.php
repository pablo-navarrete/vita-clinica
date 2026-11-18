<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professional_signature_assets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 16);
            $table->string('path', 255);
            $table->string('mime', 32);
            $table->unsignedInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->timestamp('created_at');
            $table->unique(['uuid'], 'professional_signature_assets_uuid_unique');
            $table->unique(['path'], 'professional_signature_assets_path_unique');
            $table->index(['user_id'], 'professional_signature_assets_user_id_foreign');
            $table->index(['customer_id', 'user_id'], 'professional_signature_assets_customer_id_user_id_index');
            $table->foreign(['customer_id'], 'professional_signature_assets_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['user_id'], 'professional_signature_assets_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_signature_assets');
    }
};
