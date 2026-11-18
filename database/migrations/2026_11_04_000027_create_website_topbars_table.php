<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_topbars', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->boolean('enabled')->default('1');
            $table->boolean('show_social_links')->default('0');
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('reply_email', 160)->nullable();
            $table->string('reply_whatsapp', 40)->nullable();
            $table->string('telemedicine_delivery', 10)->default('email');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['customer_id'], 'website_topbars_customer_id_unique');
            $table->foreign(['customer_id'], 'website_topbars_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_topbars');
    }
};
