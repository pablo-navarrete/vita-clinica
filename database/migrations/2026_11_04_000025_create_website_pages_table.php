<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('title', 120);
            $table->string('banner_path', 255)->nullable();
            $table->string('banner_alt', 160)->nullable();
            $table->string('slug', 140);
            $table->boolean('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['customer_id', 'slug'], 'website_pages_customer_id_slug_unique');
            $table->index(['customer_id', 'status'], 'website_pages_customer_id_status_index');
            $table->foreign(['customer_id'], 'website_pages_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
