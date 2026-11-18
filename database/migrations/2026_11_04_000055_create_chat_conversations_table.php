<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('clinic_branch_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('type', 20)->default('direct');
            $table->string('title', 120)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['patient_id'], 'chat_conversations_patient_id_foreign');
            $table->index(['created_by'], 'chat_conversations_created_by_foreign');
            $table->index(['customer_id', 'updated_at'], 'chat_conversations_customer_id_updated_at_index');
            $table->index(['clinic_branch_id'], 'chat_conversations_clinic_branch_id_foreign');
            $table->index(['customer_id', 'clinic_branch_id', 'updated_at'], 'chat_conversations_branch_updated_idx');
            $table->foreign(['clinic_branch_id'], 'chat_conversations_clinic_branch_id_foreign')->references(['id'])->on('clinic_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['created_by'], 'chat_conversations_created_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['customer_id'], 'chat_conversations_customer_id_foreign')->references(['id'])->on('customers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['patient_id'], 'chat_conversations_patient_id_foreign')->references(['id'])->on('patients')->onUpdate('no action')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
