<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professional_signature_profiles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('method', 32)->default('fes_vita');
            $table->unsignedBigInteger('signature_asset_id')->nullable();
            $table->unsignedBigInteger('stamp_asset_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['customer_id', 'user_id'], 'professional_signature_profiles_customer_id_user_id_unique');
            $table->index(['user_id'], 'professional_signature_profiles_user_id_foreign');
            $table->index(['signature_asset_id'], 'professional_signature_profiles_signature_asset_id_foreign');
            $table->index(['stamp_asset_id'], 'professional_signature_profiles_stamp_asset_id_foreign');
            $table->foreign(['customer_id'], 'professional_signature_profiles_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['signature_asset_id'], 'professional_signature_profiles_signature_asset_id_foreign')->references(['id'])->on('professional_signature_assets')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['stamp_asset_id'], 'professional_signature_profiles_stamp_asset_id_foreign')->references(['id'])->on('professional_signature_assets')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['user_id'], 'professional_signature_profiles_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_signature_profiles');
    }
};
