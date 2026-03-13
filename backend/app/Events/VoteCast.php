<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteCast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public User $user,
        public bool $hasVoted
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->issue->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vote.cast';
    }

    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'jira_issue_key' => $this->issue->jira_issue_key,
            'user_id' => $this->user->id,
            'display_name' => $this->user->display_name,
            'has_voted' => $this->hasVoted,
        ];
    }
}
