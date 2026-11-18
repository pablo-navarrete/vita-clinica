<?php

namespace App\Support;

use App\Events\UserRealtimeUpdated;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserRealtime
{
    public static function notify(int $customerId, int $userId, string $area, ?int $conversationId = null): void
    {
        static::publish($customerId, [$userId], $area, $conversationId);
    }

    private static function publish(int $customerId, array $userIds, string $area, ?int $conversationId): void
    {
        if (!$userIds) return;
        $send = function () use ($customerId, $userIds, $area, $conversationId) {
            try { event(new UserRealtimeUpdated($customerId, $userIds, $area, $conversationId)); }
            catch (\Throwable $error) { Log::warning('Realtime update unavailable.', ['area' => $area, 'exception' => $error::class]); }
        };
        if (DB::transactionLevel() > 0) DB::afterCommit($send);
        else $send();
    }

    public static function chat(int $customerId, int $conversationId): void
    {
        $conversation = ChatConversation::where('customer_id', $customerId)->find($conversationId);
        if (!$conversation) return;
        $ids = $conversation->participants()->whereNull('chat_participants.left_at')->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        static::publish($customerId, $ids, 'chat', $conversationId);
    }
}
