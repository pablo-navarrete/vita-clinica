<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_subscription_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 40);
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['customer_subscription_id'], 'subscription_events_customer_subscription_id_foreign');
            $table->index(['actor_id'], 'subscription_events_actor_id_foreign');
            $table->foreign(['actor_id'], 'subscription_events_actor_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_subscription_id'], 'subscription_events_customer_subscription_id_foreign')->references(['id'])->on('customer_subscriptions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
