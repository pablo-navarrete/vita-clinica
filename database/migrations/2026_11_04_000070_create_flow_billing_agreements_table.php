<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_billing_agreements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36);
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('flow_billing_customer_id');
            $table->string('plan_id', 100);
            $table->string('provider_id', 100)->nullable();
            $table->string('state', 20);
            $table->string('collection_mode', 20)->default('automatic');
            $table->json('quote');
            $table->date('starts_on');
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('accepted_at');
            $table->string('consent_version', 40);
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('open_subscription')->nullable()->storedAs('(case when (`state` in (_utf8mb4\'creating\',_utf8mb4\'active\',_utf8mb4\'ending\',_utf8mb4\'review\')) then `customer_subscription_id` else NULL end)');
            $table->primary(['id']);
            $table->unique(['plan_id'], 'flow_billing_agreements_plan_id_unique');
            $table->unique(['provider_id'], 'flow_billing_agreements_provider_id_unique');
            $table->unique(['open_subscription'], 'flow_one_open_agreement');
            $table->index(['customer_subscription_id'], 'flow_billing_agreements_customer_subscription_id_foreign');
            $table->index(['flow_billing_customer_id'], 'flow_billing_agreements_flow_billing_customer_id_foreign');
            $table->index(['initiated_by'], 'flow_billing_agreements_initiated_by_foreign');
            $table->foreign(['customer_subscription_id'], 'flow_billing_agreements_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['flow_billing_customer_id'], 'flow_billing_agreements_flow_billing_customer_id_foreign')->references(['id'])->on('flow_billing_customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['initiated_by'], 'flow_billing_agreements_initiated_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_billing_agreements');
    }
};
