<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_directory_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 40);
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['customer_id'], 'customer_directory_events_customer_id_foreign');
            $table->index(['actor_id'], 'customer_directory_events_actor_id_foreign');
            $table->foreign(['actor_id'], 'customer_directory_events_actor_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['customer_id'], 'customer_directory_events_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_directory_events');
    }
};
