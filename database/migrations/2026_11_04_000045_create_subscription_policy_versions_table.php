<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_policy_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('title', 160);
            $table->longText('body');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at');
            $table->index(['created_by'], 'subscription_policy_versions_created_by_foreign');
            $table->foreign(['created_by'], 'subscription_policy_versions_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_policy_versions');
    }
};
