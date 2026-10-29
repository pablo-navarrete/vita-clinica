<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TelemedicineSignal implements ShouldBroadcastNow
{
    public function __construct(public string $room, public string $sender, public string $clientId, public array $signal) {}

    public function broadcastOn(): array { return [new PrivateChannel('telemedicine.' . $this->room)]; }
    public function broadcastAs(): string { return 'telemedicine.signal'; }
    public function broadcastWith(): array
    {
        return ['sender' => $this->sender, 'clientId' => $this->clientId, 'signal' => $this->signal];
    }
}
