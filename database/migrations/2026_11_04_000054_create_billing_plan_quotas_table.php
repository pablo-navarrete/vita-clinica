<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plan_quotas', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_version_id');
            $table->string('metric', 80);
            $table->string('unit', 24);
            $table->unsignedBigInteger('quantity')->nullable();
            $table->string('reset_period', 16);
            $table->string('overage_policy', 24);
            $table->unique(['billing_plan_version_id', 'metric'], 'billing_plan_quotas_billing_plan_version_id_metric_unique');
            $table->foreign(['billing_plan_version_id'], 'billing_plan_quotas_billing_plan_version_id_foreign')->references(['id'])->on('billing_plan_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_quotas');
    }
};
