<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_user_deletions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['message_id', 'user_id'], 'chat_message_user_deletions_message_id_user_id_unique');
            $table->index(['user_id'], 'chat_message_user_deletions_user_id_foreign');
            $table->foreign(['message_id'], 'chat_message_user_deletions_message_id_foreign')->references(['id'])->on('chat_messages')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'chat_message_user_deletions_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_user_deletions');
    }
};
