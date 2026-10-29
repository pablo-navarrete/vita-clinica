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
            $table->foreignId('clinic_branch_id')->nullable()->after('customer_id')
                ->constrained('clinic_branches')->nullOnDelete();
            $table->index(['customer_id', 'clinic_branch_id', 'updated_at'], 'chat_conversations_branch_updated_idx');
        });

        DB::table('chat_conversations')->whereNull('clinic_branch_id')->orderBy('id')->chunkById(200, function ($conversations) {
            foreach ($conversations as $conversation) {
                $branchId = DB::table('clinic_branch_user')
                    ->join('clinic_branches', 'clinic_branches.id', '=', 'clinic_branch_user.clinic_branch_id')
                    ->where('clinic_branch_user.user_id', $conversation->created_by)
                    ->where('clinic_branches.customer_id', $conversation->customer_id)
                    ->where('clinic_branches.status', true)
                    ->orderBy('clinic_branches.id')
                    ->value('clinic_branches.id');

                if ($branchId) {
                    DB::table('chat_conversations')->where('id', $conversation->id)->update(['clinic_branch_id' => $branchId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('chat_conversations_branch_updated_idx');
            $table->dropConstrainedForeignId('clinic_branch_id');
        });
    }
};
