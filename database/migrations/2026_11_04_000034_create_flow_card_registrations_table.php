<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_card_registrations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36);
            $table->unsignedBigInteger('flow_billing_customer_id');
            $table->unsignedBigInteger('initiated_by');
            $table->text('token')->nullable();
            $table->char('token_hash', 64)->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('state', 20);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['id']);
            $table->unique(['token_hash'], 'flow_card_registrations_token_hash_unique');
            $table->index(['flow_billing_customer_id'], 'flow_card_registrations_flow_billing_customer_id_foreign');
            $table->index(['initiated_by'], 'flow_card_registrations_initiated_by_foreign');
            $table->foreign(['flow_billing_customer_id'], 'flow_card_registrations_flow_billing_customer_id_foreign')->references(['id'])->on('flow_billing_customers')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['initiated_by'], 'flow_card_registrations_initiated_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_card_registrations');
    }
};
