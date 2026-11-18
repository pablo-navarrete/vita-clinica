<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flow_payment_observations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('environment', 12);
            $table->char('account_hash', 64);
            $table->string('flow_order', 80);
            $table->string('commerce_order', 160);
            $table->date('paid_on');
            $table->string('media', 80);
            $table->char('currency', 3);
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('reported_fee');
            $table->bigInteger('net');
            $table->date('transfer_on')->nullable();
            $table->char('fingerprint', 64);
            $table->timestamp('created_at');
            $table->unique(['fingerprint'], 'flow_payment_observations_fingerprint_unique');
            $table->index(['environment', 'account_hash', 'paid_on'], 'flow_observation_account_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_payment_observations');
    }
};
