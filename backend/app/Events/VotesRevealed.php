<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotesRevealed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public User $revealedBy,
        public array $votes,
        public ?float $average
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->issue->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'votes.revealed';
    }

    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'jira_issue_key' => $this->issue->jira_issue_key,
            'revealed_by' => $this->revealedBy->display_name,
            'votes' => $this->votes,
            'average' => $this->average,
            'final_score' => $this->issue->final_score,
        ];
    }
}
