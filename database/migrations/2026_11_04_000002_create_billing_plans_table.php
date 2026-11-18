<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('code', 80);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('active');
            $table->unsignedInteger('position');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['code'], 'billing_plans_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
