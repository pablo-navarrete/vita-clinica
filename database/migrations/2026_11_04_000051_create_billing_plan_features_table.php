<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plan_features', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_version_id');
            $table->unsignedBigInteger('billing_feature_id');
            $table->unique(['billing_plan_version_id', 'billing_feature_id'], 'billing_version_feature_unique');
            $table->index(['billing_feature_id'], 'billing_plan_features_billing_feature_id_foreign');
            $table->foreign(['billing_feature_id'], 'billing_plan_features_billing_feature_id_foreign')->references(['id'])->on('billing_features')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['billing_plan_version_id'], 'billing_plan_features_billing_plan_version_id_foreign')->references(['id'])->on('billing_plan_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_features');
    }
};
