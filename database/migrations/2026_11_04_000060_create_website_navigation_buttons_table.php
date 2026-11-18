<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_navigation_buttons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->boolean('enabled')->default('0');
            $table->boolean('public_booking_enabled')->default('0');
            $table->string('label', 60)->default('Contactar');
            $table->string('destination', 20)->default('contact');
            $table->unsignedBigInteger('menu_id')->nullable();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->string('custom_url', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('brand_display', 10)->default('both');
            $table->unique(['customer_id'], 'website_navigation_buttons_customer_id_unique');
            $table->index(['menu_id'], 'website_navigation_buttons_menu_id_foreign');
            $table->foreign(['customer_id'], 'website_navigation_buttons_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['menu_id'], 'website_navigation_buttons_menu_id_foreign')->references(['id'])->on('menus')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_navigation_buttons');
    }
};
