<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IceBreakerUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $iceBreaker;

    /**
     * Create a new event instance.
     */
    public function __construct(Room $room, string $iceBreaker)
    {
        $this->room = $room;
        $this->iceBreaker = $iceBreaker;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ice.breaker.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ice_breaker' => $this->iceBreaker,
        ];
    }
}
