<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regular_medications', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('medical_record_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id'], 'regular_medications_customer_id_foreign');
            $table->index(['medical_record_id'], 'regular_medications_medical_record_id_foreign');
            $table->foreign(['customer_id'], 'regular_medications_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['medical_record_id'], 'regular_medications_medical_record_id_foreign')->references(['id'])->on('medical_records')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regular_medications');
    }
};
