<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_billing_customers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('environment', 12);
            $table->char('account_hash', 64);
            $table->string('external_id', 100);
            $table->string('provider_id', 100)->nullable();
            $table->string('state', 20);
            $table->string('card_brand', 40)->nullable();
            $table->char('card_last_four', 4)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['customer_id', 'environment', 'account_hash'], 'flow_billing_customer_account');
            $table->unique(['external_id'], 'flow_billing_customers_external_id_unique');
            $table->foreign(['customer_id'], 'flow_billing_customers_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_billing_customers');
    }
};
