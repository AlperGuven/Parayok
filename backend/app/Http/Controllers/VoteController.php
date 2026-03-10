<?php

namespace App\Http\Controllers;

use App\Events\IssueAdded;
use App\Events\ParticipantJoined;
use App\Events\VoteCast;
use App\Events\VotesRevealed;
use App\Events\VotingReset;
use App\Events\VotingStarted;
use App\Http\Requests\VoteRequest;
use App\Models\Issue;
use App\Models\Room;
use App\Models\Vote;
use App\Services\JiraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoteController extends Controller
{
    public function castVote(VoteRequest $request, string $uuid, int $issueId)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $issue = Issue::where('id', $issueId)
            ->where('room_id', $room->id)
            ->firstOrFail();

        if ($issue->status !== 'voting') {
            return response()->json(['message' => 'Voting is not active for this issue'], 400);
        }

        Vote::updateOrCreate(
            [
                'issue_id' => $issue->id,
                'user_id' => Auth::id(),
            ],
            [
                'value' => $request->value,
            ]
        );

        $hasVoted = Vote::where('issue_id', $issue->id)->count();

        event(new VoteCast($issue, Auth::user(), $hasVoted > 0));

        return response()->json(['message' => 'Vote cast successfully']);
    }

    public function startVoting(Request $request, string $uuid, int $issueId)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $issue = Issue::where('id', $issueId)
            ->where('room_id', $room->id)
            ->firstOrFail();

        Vote::where('issue_id', $issue->id)->delete();

        $issue->update([
            'status' => 'voting',
            'final_score' => null,
        ]);

        $room->update(['status' => 'active']);

        event(new VotingStarted($issue, Auth::user()));

        return response()->json(['message' => 'Voting started']);
    }

    public function revealVotes(Request $request, string $uuid, int $issueId)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $issue = Issue::where('id', $issueId)
            ->where('room_id', $room->id)
            ->firstOrFail();

        $votes = Vote::where('issue_id', $issue->id)
            ->with('user')
            ->get()
            ->map(function ($vote) {
                return [
                    'user_id' => $vote->user_id,
                    'display_name' => $vote->user->display_name,
                    'value' => $vote->value,
                ];
            });

        $numericVotes = $votes->filter(function ($vote) {
            return is_numeric($vote['value']);
        })->pluck('value');

        $average = $numericVotes->count() > 0 
            ? round($numericVotes->avg(), 1) 
            : null;

        $finalScore = $this->calculateFinalScore($votes->pluck('value'));

        $issue->update([
            'status' => 'revealed',
            'final_score' => $finalScore,
        ]);

        event(new VotesRevealed($issue, Auth::user(), $votes->toArray(), $average));

        if ($issue->final_score) {
            $jiraService = new JiraService(Auth::user());
            $jiraService->setUser(Auth::user());
            $jiraService->updateStoryPoints($issue->jira_issue_key, $issue->final_score);
        }

        return response()->json([
            'message' => 'Votes revealed',
            'votes' => $votes,
            'average' => $average,
            'final_score' => $finalScore,
        ]);
    }

    public function resetVoting(Request $request, string $uuid, int $issueId)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $issue = Issue::where('id', $issueId)
            ->where('room_id', $room->id)
            ->firstOrFail();

        Vote::where('issue_id', $issue->id)->delete();

        $issue->update([
            'status' => 'pending',
            'final_score' => null,
        ]);

        event(new VotingReset($issue, Auth::user()));

        return response()->json(['message' => 'Voting reset']);
    }

    private function calculateFinalScore($votes): ?float
    {
        $numericVotes = $votes->filter(function ($value) {
            return is_numeric($value);
        })->map(function ($value) {
            return (float) $value;
        });

        if ($numericVotes->count() === 0) {
            return null;
        }

        $mode = $numericVotes->countBy()->sortDesc()->keys()->first();

        return $mode !== null ? (float) $mode : null;
    }
}
