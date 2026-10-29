<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('left_at');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('body');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('chat_message_user_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_user_deletions');
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deleted_at');
        });
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
