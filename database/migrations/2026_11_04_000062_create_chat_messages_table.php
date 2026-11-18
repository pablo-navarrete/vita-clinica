<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->unsignedBigInteger('forwarded_from_message_id')->nullable();
            $table->text('body');
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'chat_messages_user_id_foreign');
            $table->index(['conversation_id', 'id'], 'chat_messages_conversation_id_id_index');
            $table->index(['reply_to_id'], 'chat_messages_reply_to_id_foreign');
            $table->index(['forwarded_from_message_id'], 'chat_messages_forwarded_from_message_id_foreign');
            $table->index(['deleted_by'], 'chat_messages_deleted_by_foreign');
            $table->foreign(['conversation_id'], 'chat_messages_conversation_id_foreign')->references(['id'])->on('chat_conversations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['deleted_by'], 'chat_messages_deleted_by_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['forwarded_from_message_id'], 'chat_messages_forwarded_from_message_id_foreign')->references(['id'])->on('chat_messages')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['reply_to_id'], 'chat_messages_reply_to_id_foreign')->references(['id'])->on('chat_messages')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_id'], 'chat_messages_user_id_foreign')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
