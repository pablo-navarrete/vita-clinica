<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_fee_rates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('scenario', 160);
            $table->date('effective_from');
            $table->unsignedInteger('rate_units');
            $table->unsignedInteger('vat_units');
            $table->unsignedBigInteger('fixed_amount');
            $table->text('note');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at');
            $table->unique(['effective_from'], 'payment_fee_rates_effective_from_unique');
            $table->index(['created_by'], 'payment_fee_rates_created_by_foreign');
            $table->foreign(['created_by'], 'payment_fee_rates_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_fee_rates');
    }
};
