<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public User $startedBy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('room.' . $this->issue->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voting.started';
    }

    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'jira_issue_key' => $this->issue->jira_issue_key,
            'started_by' => $this->startedBy->display_name,
        ];
    }
}
