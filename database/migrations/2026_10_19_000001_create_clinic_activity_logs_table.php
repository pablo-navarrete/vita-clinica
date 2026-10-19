<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('module', 80);
            $table->string('action', 30);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('summary');
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['customer_id', 'module', 'created_at']);
            $table->index(['customer_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_activity_logs');
    }
};
