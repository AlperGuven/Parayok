<?php

namespace App\Events;

use App\Models\RoomParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RoomParticipant $participant
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('room.' . $this->participant->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->participant->user_id,
            'display_name' => $this->participant->user->display_name,
            'avatar_url' => $this->participant->user->avatar_url,
            'role' => $this->participant->role,
        ];
    }
}
