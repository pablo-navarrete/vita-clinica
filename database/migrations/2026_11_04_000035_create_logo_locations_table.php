<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logo_locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('logo_id')->nullable();
            $table->string('location', 255);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id'], 'logo_locations_customer_id_foreign');
            $table->index(['logo_id'], 'logo_locations_logo_id_foreign');
            $table->foreign(['customer_id'], 'logo_locations_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['logo_id'], 'logo_locations_logo_id_foreign')->references(['id'])->on('logos')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logo_locations');
    }
};
