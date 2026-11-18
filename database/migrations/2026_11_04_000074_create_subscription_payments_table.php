<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('billing_plan_price_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('paid_on');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('method', 24);
            $table->string('reference', 120);
            $table->text('note');
            $table->json('breakdown');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('created_at');
            $table->unique(['customer_subscription_id', 'period_start'], 'subscription_period_unique');
            $table->unique(['customer_subscription_id', 'reference'], 'subscription_reference_unique');
            $table->index(['customer_id'], 'subscription_payments_customer_id_foreign');
            $table->index(['billing_plan_price_id'], 'subscription_payments_billing_plan_price_id_foreign');
            $table->index(['recorded_by'], 'subscription_payments_recorded_by_foreign');
            $table->foreign(['billing_plan_price_id'], 'subscription_payments_billing_plan_price_id_foreign')->references(['id'])->on('billing_plan_prices')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_id'], 'subscription_payments_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_subscription_id'], 'subscription_payments_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['recorded_by'], 'subscription_payments_recorded_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
