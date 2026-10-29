<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $customerId, public int $conversationId, public int $messageId) {}

    public function broadcastOn(): array { return [new PrivateChannel("chat.{$this->customerId}.{$this->conversationId}")]; }
    public function broadcastAs(): string { return 'chat.message.deleted'; }
    public function broadcastWith(): array { return ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId]; }
}
