<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomNameUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;

    public function __construct(Room $room)
    {
        $this->room = $room;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'room.name.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'name' => $this->room->name,
        ];
    }
}
