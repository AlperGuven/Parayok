<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $issueId;

    public function __construct(Room $room, int $issueId)
    {
        $this->room = $room;
        $this->issueId = $issueId;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'issue.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issueId,
        ];
    }
}
