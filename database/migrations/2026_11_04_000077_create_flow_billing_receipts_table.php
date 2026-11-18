<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_billing_receipts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('subscription_payment_id');
            $table->string('environment', 12);
            $table->char('account_hash', 64);
            $table->string('flow_order', 80);
            $table->string('commerce_order', 160);
            $table->unsignedBigInteger('amount');
            $table->timestamp('verified_at');
            $table->unique(['environment', 'account_hash', 'flow_order'], 'flow_billing_receipt_unique');
            $table->index(['customer_subscription_id'], 'flow_billing_receipts_customer_subscription_id_foreign');
            $table->index(['subscription_payment_id'], 'flow_billing_receipts_subscription_payment_id_foreign');
            $table->foreign(['customer_subscription_id'], 'flow_billing_receipts_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['subscription_payment_id'], 'flow_billing_receipts_subscription_payment_id_foreign')->references(['id'])->on('subscription_payments')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_billing_receipts');
    }
};
