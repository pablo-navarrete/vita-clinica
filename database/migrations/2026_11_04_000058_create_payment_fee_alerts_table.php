<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_fee_alerts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('deduplication_key', 64);
            $table->string('kind', 40);
            $table->string('environment', 12)->nullable();
            $table->char('account_hash', 64)->nullable();
            $table->unsignedBigInteger('observation_id')->nullable();
            $table->unsignedBigInteger('payment_fee_rate_id')->nullable();
            $table->text('message');
            $table->json('details');
            $table->timestamp('created_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unique(['deduplication_key'], 'payment_fee_alerts_deduplication_key_unique');
            $table->index(['observation_id'], 'payment_fee_alerts_observation_id_foreign');
            $table->index(['payment_fee_rate_id'], 'payment_fee_alerts_payment_fee_rate_id_foreign');
            $table->index(['acknowledged_by'], 'payment_fee_alerts_acknowledged_by_foreign');
            $table->foreign(['acknowledged_by'], 'payment_fee_alerts_acknowledged_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['observation_id'], 'payment_fee_alerts_observation_id_foreign')->references(['id'])->on('flow_payment_observations')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['payment_fee_rate_id'], 'payment_fee_alerts_payment_fee_rate_id_foreign')->references(['id'])->on('payment_fee_rates')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_fee_alerts');
    }
};
