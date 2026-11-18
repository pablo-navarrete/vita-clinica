<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_page_sections', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('website_page_id');
            $table->string('section_type', 20)->default('content');
            $table->string('title', 160)->nullable();
            $table->longText('body')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('image_alt', 160)->nullable();
            $table->string('image_position', 12)->default('inline');
            $table->unsignedInteger('position')->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['website_page_id', 'position'], 'website_page_sections_website_page_id_position_index');
            $table->foreign(['website_page_id'], 'website_page_sections_website_page_id_foreign')->references(['id'])->on('website_pages')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_sections');
    }
};
