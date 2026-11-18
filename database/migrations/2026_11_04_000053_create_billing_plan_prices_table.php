<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plan_prices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_version_id');
            $table->string('interval', 10);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('additional_branch_amount');
            $table->unique(['billing_plan_version_id', 'interval'], 'billing_plan_prices_billing_plan_version_id_interval_unique');
            $table->foreign(['billing_plan_version_id'], 'billing_plan_prices_billing_plan_version_id_foreign')->references(['id'])->on('billing_plan_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_prices');
    }
};
