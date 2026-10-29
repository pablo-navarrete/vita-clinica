<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('direct')->after('created_by');
        });

        DB::table('chat_conversations')
            ->whereIn('id', function ($query) {
                $query->select('conversation_id')
                    ->from('chat_participants')
                    ->groupBy('conversation_id')
                    ->havingRaw('COUNT(*) > 2');
            })
            ->update(['type' => 'group']);
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
