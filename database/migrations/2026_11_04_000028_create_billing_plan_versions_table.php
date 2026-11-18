<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plan_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_id');
            $table->unsignedInteger('number');
            $table->string('status', 16);
            $table->unsignedInteger('included_branches')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->unsignedInteger('grace_days')->nullable();
            $table->text('terms')->nullable();
            $table->longText('snapshot')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['billing_plan_id', 'number'], 'billing_plan_versions_billing_plan_id_number_unique');
            $table->index(['created_by'], 'billing_plan_versions_created_by_foreign');
            $table->index(['published_by'], 'billing_plan_versions_published_by_foreign');
            $table->foreign(['billing_plan_id'], 'billing_plan_versions_billing_plan_id_foreign')->references(['id'])->on('billing_plans')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['created_by'], 'billing_plan_versions_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['published_by'], 'billing_plan_versions_published_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_versions');
    }
};
