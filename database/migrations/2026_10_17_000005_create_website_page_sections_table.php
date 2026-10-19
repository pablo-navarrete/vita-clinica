<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->string('title', 160)->nullable();
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['website_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_sections');
    }
};
