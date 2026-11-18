<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('website_page_id')->nullable();
            $table->string('name', 80);
            $table->string('url', 255);
            $table->unsignedInteger('position')->default('0');
            $table->boolean('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id', 'status', 'position'], 'menus_customer_id_status_position_index');
            $table->index(['website_page_id'], 'menus_website_page_id_foreign');
            $table->foreign(['customer_id'], 'menus_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['website_page_id'], 'menus_website_page_id_foreign')->references(['id'])->on('website_pages')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
