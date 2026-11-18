<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_checkout_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36);
            $table->unsignedBigInteger('customer_subscription_id');
            $table->string('environment', 12);
            $table->char('account_hash', 64);
            $table->string('commerce_order', 100);
            $table->string('flow_order', 80)->nullable();
            $table->date('period_start');
            $table->json('quote');
            $table->string('state', 20);
            $table->unsignedBigInteger('initiated_by');
            $table->text('token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedInteger('attempt')->default('1');
            $table->unsignedBigInteger('open_subscription')->nullable()->storedAs('(case when (`state` in (_utf8mb4\'prepared\',_utf8mb4\'sending\',_utf8mb4\'pending\')) then `customer_subscription_id` else NULL end)');
            $table->primary(['id']);
            $table->unique(['commerce_order'], 'flow_checkout_orders_commerce_order_unique');
            $table->unique(['customer_subscription_id', 'period_start', 'attempt'], 'flow_checkout_period_attempt');
            $table->unique(['open_subscription'], 'flow_one_open_checkout');
            $table->index(['initiated_by'], 'flow_checkout_orders_initiated_by_foreign');
            $table->foreign(['customer_subscription_id'], 'flow_checkout_orders_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['initiated_by'], 'flow_checkout_orders_initiated_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_checkout_orders');
    }
};
