<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_blog_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('website_blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 220);
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('image_path')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'slug']);
            $table->index(['customer_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_blog_posts');
        Schema::dropIfExists('website_blog_settings');
    }
};
