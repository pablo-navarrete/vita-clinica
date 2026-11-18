<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_directory_profiles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('slug', 220);
            $table->text('description');
            $table->unsignedBigInteger('logo_id')->nullable();
            $table->unsignedBigInteger('website_banner_id')->nullable();
            $table->string('public_address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->boolean('publication_enabled')->default('0');
            $table->unsignedBigInteger('authorized_by')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorization_version', 40)->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->unsignedInteger('revision')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('brand_display', 10)->default('both');
            $table->string('cover_source', 10)->default('banner');
            $table->string('cover_path', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unique(['customer_id'], 'customer_directory_profiles_customer_id_unique');
            $table->unique(['slug'], 'customer_directory_profiles_slug_unique');
            $table->index(['logo_id'], 'customer_directory_profiles_logo_id_foreign');
            $table->index(['website_banner_id'], 'customer_directory_profiles_website_banner_id_foreign');
            $table->index(['authorized_by'], 'customer_directory_profiles_authorized_by_foreign');
            $table->index(['suspended_by'], 'customer_directory_profiles_suspended_by_foreign');
            $table->index(['publication_enabled'], 'customer_directory_profiles_publication_enabled_index');
            $table->foreign(['authorized_by'], 'customer_directory_profiles_authorized_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_id'], 'customer_directory_profiles_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['logo_id'], 'customer_directory_profiles_logo_id_foreign')->references(['id'])->on('logos')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['suspended_by'], 'customer_directory_profiles_suspended_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['website_banner_id'], 'customer_directory_profiles_website_banner_id_foreign')->references(['id'])->on('website_banners')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_directory_profiles');
    }
};
