<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public User $addedBy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->issue->room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'issue.added';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->issue->id,
            'jira_issue_key' => $this->issue->jira_issue_key,
            'summary' => $this->issue->summary,
            'description' => $this->issue->description,
            'jira_url' => $this->issue->jira_url,
            'added_by' => $this->addedBy->display_name,
        ];
    }
}
