<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('subtitle', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('button_text', 60)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('image_path');
            $table->string('image_alt', 160)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_banners');
    }
};
