<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public int $customerId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->customerId}.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji']);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => $this->message->body,
            'user_id' => $this->message->user_id,
            'user' => $this->message->user?->name ?? 'Usuario',
            'time' => $this->message->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'reply_to' => $this->message->replyTo ? [
                'id' => (int) $this->message->replyTo->id,
                'user' => $this->message->replyTo->user?->name ?? 'Usuario',
                'body' => $this->message->replyTo->body,
            ] : null,
            'forwarded_from' => $this->message->forwardedFrom ? [
                'user' => $this->message->forwardedFrom->user?->name ?? 'Usuario',
            ] : null,
            'reactions' => $this->message->reactions->groupBy('emoji')->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => false,
            ])->values()->all(),
        ];
    }
}
