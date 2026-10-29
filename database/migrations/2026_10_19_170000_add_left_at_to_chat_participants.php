<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable()->after('last_read_at');
            $table->index(['conversation_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_participants', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'left_at']);
            $table->dropColumn('left_at');
        });
    }
};
