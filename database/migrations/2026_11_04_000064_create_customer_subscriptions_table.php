<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('billing_plan_price_id');
            $table->unsignedInteger('branch_quantity');
            $table->date('starts_on');
            $table->date('trial_ends_on')->nullable();
            $table->date('paid_through_on')->nullable();
            $table->date('next_renewal_on');
            $table->unsignedTinyInteger('anchor_day');
            $table->unsignedTinyInteger('anchor_month');
            $table->boolean('cancel_at_period_end')->default('0');
            $table->date('access_ends_on')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedInteger('revision')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('payment_mode', 16)->nullable();
            $table->string('contract_status', 24)->default('legacy_transition');
            $table->unique(['customer_id'], 'customer_subscriptions_customer_id_unique');
            $table->index(['billing_plan_price_id'], 'customer_subscriptions_billing_plan_price_id_foreign');
            $table->index(['created_by'], 'customer_subscriptions_created_by_foreign');
            $table->foreign(['billing_plan_price_id'], 'customer_subscriptions_billing_plan_price_id_foreign')->references(['id'])->on('billing_plan_prices')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['created_by'], 'customer_subscriptions_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_id'], 'customer_subscriptions_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
