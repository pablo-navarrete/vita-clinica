<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_catalog_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_id');
            $table->unsignedBigInteger('billing_plan_version_id')->nullable();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 40);
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['billing_plan_id'], 'billing_catalog_events_billing_plan_id_foreign');
            $table->index(['billing_plan_version_id'], 'billing_catalog_events_billing_plan_version_id_foreign');
            $table->index(['actor_id'], 'billing_catalog_events_actor_id_foreign');
            $table->foreign(['actor_id'], 'billing_catalog_events_actor_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['billing_plan_id'], 'billing_catalog_events_billing_plan_id_foreign')->references(['id'])->on('billing_plans')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['billing_plan_version_id'], 'billing_catalog_events_billing_plan_version_id_foreign')->references(['id'])->on('billing_plan_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_catalog_events');
    }
};
