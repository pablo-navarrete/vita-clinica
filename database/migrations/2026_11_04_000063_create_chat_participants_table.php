<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['conversation_id', 'user_id'], 'chat_participants_conversation_id_user_id_unique');
            $table->index(['user_id', 'last_read_at'], 'chat_participants_user_id_last_read_at_index');
            $table->index(['conversation_id', 'left_at'], 'chat_participants_conversation_id_left_at_index');
            $table->foreign(['conversation_id'], 'chat_participants_conversation_id_foreign')->references(['id'])->on('chat_conversations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'chat_participants_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};
