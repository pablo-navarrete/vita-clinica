<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_banners', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('title', 120);
            $table->string('subtitle', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('button_text', 60)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('image_path', 255);
            $table->string('image_alt', 160)->nullable();
            $table->unsignedInteger('position')->default('1');
            $table->boolean('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('dark_overlay')->default('1');
            $table->boolean('image_only')->default('0');
            $table->string('text_alignment', 6)->default('center');
            $table->string('button_alignment', 6)->default('center');
            $table->index(['customer_id', 'status', 'position'], 'website_banners_customer_id_status_position_index');
            $table->foreign(['customer_id'], 'website_banners_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_banners');
    }
};
