<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_blog_posts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title', 180);
            $table->string('slug', 220);
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('image_path', 255)->nullable();
            $table->boolean('status')->default('0');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['customer_id', 'slug'], 'website_blog_posts_customer_id_slug_unique');
            $table->index(['user_id'], 'website_blog_posts_user_id_foreign');
            $table->index(['customer_id', 'status', 'published_at'], 'website_blog_posts_customer_id_status_published_at_index');
            $table->foreign(['customer_id'], 'website_blog_posts_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'website_blog_posts_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_blog_posts');
    }
};
