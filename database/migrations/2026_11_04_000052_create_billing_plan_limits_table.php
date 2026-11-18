<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plan_limits', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_plan_version_id');
            $table->string('code', 80);
            $table->string('scope', 16);
            $table->unsignedBigInteger('value')->nullable();
            $table->unique(['billing_plan_version_id', 'code'], 'billing_plan_limits_billing_plan_version_id_code_unique');
            $table->foreign(['billing_plan_version_id'], 'billing_plan_limits_billing_plan_version_id_foreign')->references(['id'])->on('billing_plan_versions')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_limits');
    }
};
