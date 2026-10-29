<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_navigation_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('label', 60)->default('Contactar');
            $table->string('destination', 20)->default('contact');
            $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->string('custom_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_navigation_buttons');
    }
};
