<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingReset implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public User $resetBy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('room.' . $this->issue->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voting.reset';
    }

    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'jira_issue_key' => $this->issue->jira_issue_key,
            'reset_by' => $this->resetBy->display_name,
        ];
    }
}
