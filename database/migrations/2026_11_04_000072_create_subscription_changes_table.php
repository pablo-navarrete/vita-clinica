<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('billing_plan_price_id');
            $table->unsignedInteger('branch_quantity');
            $table->date('effective_on');
            $table->string('status', 16);
            $table->text('reason');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('pending_subscription')->nullable()->storedAs('(case when (`status` = _utf8mb4\'pending\') then `customer_subscription_id` else NULL end)');
            $table->unique(['pending_subscription'], 'one_pending_subscription_change');
            $table->index(['customer_subscription_id'], 'subscription_changes_customer_subscription_id_foreign');
            $table->index(['billing_plan_price_id'], 'subscription_changes_billing_plan_price_id_foreign');
            $table->index(['created_by'], 'subscription_changes_created_by_foreign');
            $table->foreign(['billing_plan_price_id'], 'subscription_changes_billing_plan_price_id_foreign')->references(['id'])->on('billing_plan_prices')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['created_by'], 'subscription_changes_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_subscription_id'], 'subscription_changes_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
