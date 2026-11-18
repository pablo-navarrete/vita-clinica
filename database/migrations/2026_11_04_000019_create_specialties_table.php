<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('name', 255);
            $table->boolean('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedInteger('catalog_id')->nullable();
            $table->boolean('public_visible')->default('1');
            $table->index(['customer_id'], 'specialties_customer_id_foreign');
            $table->index(['catalog_id'], 'specialties_catalog_id_index');
            $table->foreign(['customer_id'], 'specialties_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
