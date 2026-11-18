<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_fee_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedTinyInteger('id');
            $table->unsignedInteger('simulator_rate_units')->nullable();
            $table->unsignedInteger('simulator_vat_units')->nullable();
            $table->timestamp('simulator_updated_at')->nullable();
            $table->unsignedBigInteger('simulator_updated_by')->nullable();
            $table->primary(['id']);
            $table->index(['simulator_updated_by'], 'payment_fee_settings_simulator_updated_by_foreign');
            $table->foreign(['simulator_updated_by'], 'payment_fee_settings_simulator_updated_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
        DB::table('payment_fee_settings')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_fee_settings');
    }
};
