<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserRealtimeUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $customerId, public array $userIds, public string $area, public ?int $conversationId = null) {}
    public function broadcastOn(): array { return array_map(fn ($id) => new PrivateChannel("clinic-user.{$this->customerId}.{$id}"), $this->userIds); }
    public function broadcastAs(): string { return 'user.updated'; }
    public function broadcastWith(): array { return ['area' => $this->area, 'conversation_id' => $this->conversationId]; }
}
