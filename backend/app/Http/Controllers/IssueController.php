<?php

namespace App\Http\Controllers;

use App\Events\IssueAdded;
use App\Http\Requests\StoreIssueRequest;
use App\Models\Issue;
use App\Models\Room;
use App\Services\JiraService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueController extends Controller
{
    public function storeFromUrl(StoreIssueRequest $request, string $uuid)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        $jiraService = new JiraService($user);
        $jiraService->setUser($user);

        $issueKey = $jiraService->parseIssueUrl($request->url);
        
        if (!$issueKey) {
            return response()->json(['message' => 'Invalid Jira URL'], 400);
        }

        $issueData = $jiraService->getIssue($issueKey);

        if (!$issueData) {
            return response()->json(['message' => 'Issue not found in Jira'], 404);
        }

        $existingIssue = Issue::where('room_id', $room->id)
            ->where('jira_issue_key', $issueKey)
            ->first();

        if ($existingIssue) {
            return response()->json(['message' => 'Issue already exists in this room'], 400);
        }

        $maxSortOrder = Issue::where('room_id', $room->id)->max('sort_order') ?? 0;

        $issue = Issue::create([
            'room_id' => $room->id,
            'jira_issue_id' => $issueData['id'],
            'jira_issue_key' => $issueData['key'],
            'summary' => $issueData['fields']['summary'] ?? 'No title',
            'description' => $this->parseDescription($issueData['fields']['description'] ?? null),
            'jira_url' => $request->url,
            'status' => 'pending',
            'sort_order' => $maxSortOrder + 1,
            'added_by' => $user->id,
        ]);

        return response()->json([
            'id' => $issue->id,
            'jira_issue_key' => $issue->jira_issue_key,
            'summary' => $issue->summary,
        ], 201);
    }

    public function storeFromJql(Request $request, string $uuid)
    {
        $request->validate([
            'jql' => 'required|string',
            'max_results' => 'nullable|integer|min:1|max:50',
        ]);

        $room = Room::where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        $jiraService = new JiraService($user);
        $jiraService->setUser($user);

        $issues = $jiraService->searchIssues($request->jql, $request->max_results ?? 20);

        $added = [];
        $maxSortOrder = Issue::where('room_id', $room->id)->max('sort_order') ?? 0;

        foreach ($issues as $issueData) {
            $issueKey = $issueData['key'];

            $existingIssue = Issue::where('room_id', $room->id)
                ->where('jira_issue_key', $issueKey)
                ->first();

            if ($existingIssue) {
                continue;
            }

            $maxSortOrder++;
            
            $issue = Issue::create([
                'room_id' => $room->id,
                'jira_issue_id' => $issueData['id'],
                'jira_issue_key' => $issueKey,
                'summary' => $issueData['fields']['summary'] ?? 'No title',
                'description' => $this->parseDescription($issueData['fields']['description'] ?? null),
                'jira_url' => "{$user->jira_site_url}/browse/{$issueKey}",
                'status' => 'pending',
                'sort_order' => $maxSortOrder,
                'added_by' => $user->id,
            ]);

            $added[] = $issue;
        }

        return response()->json([
            'message' => count($added) . ' issues added',
            'issues' => array_map(fn($i) => [
                'id' => $i->id,
                'jira_issue_key' => $i->jira_issue_key,
                'summary' => $i->summary,
            ], $added),
        ]);
    }

    public function destroy(Request $request, string $uuid, int $id)
    {
        $room = Room::where('uuid', $uuid)->firstOrFail();
        
        $issue = Issue::where('id', $id)
            ->where('room_id', $room->id)
            ->firstOrFail();

        $issue->delete();

        return response()->json(['message' => 'Issue removed']);
    }

    public function reorder(Request $request, string $uuid, int $id)
    {
        $request->validate([
            'sort_order' => 'required|integer|min:0',
        ]);

        $room = Room::where('uuid', $uuid)->firstOrFail();
        
        $issue = Issue::where('id', $id)
            ->where('room_id', $room->id)
            ->firstOrFail();

        $issue->update(['sort_order' => $request->sort_order]);

        return response()->json(['message' => 'Order updated']);
    }

    private function parseDescription(?array $jiraDescription): ?string
    {
        if (!$jiraDescription) {
            return null;
        }

        if (isset($jiraDescription['content'])) {
            return $this->extractTextFromAdf($jiraDescription['content']);
        }

        return $jiraDescription['content'] ?? $jiraDescription['plain']['value'] ?? null;
    }

    private function extractTextFromAdf(array $content): string
    {
        $text = '';
        
        foreach ($content as $block) {
            if (isset($block['type']) && $block['type'] === 'paragraph') {
                if (isset($block['content'])) {
                    foreach ($block['content'] as $inline) {
                        if (isset($inline['text'])) {
                            $text .= $inline['text'];
                        }
                    }
                    $text .= "\n";
                }
            }
        }
        
        return trim($text);
    }
}
