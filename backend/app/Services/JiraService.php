<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraService
{
    private ?User $user;
    private string $baseUrl;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
        
        if ($user && $user->jira_cloud_id) {
            $this->baseUrl = "https://api.atlassian.com/ex/jira/{$user->jira_cloud_id}/rest/api/3";
        } else {
            $this->baseUrl = '';
        }
    }

    public function getAccessibleResources(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ])->get('https://api.atlassian.com/oauth/token/accessible-resources');

        Log::info('Accessible resources response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response->json() ?? [];
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->baseUrl = "https://api.atlassian.com/ex/jira/{$user->jira_cloud_id}/rest/api/3";
        
        return $this;
    }

    public function searchIssues(string $jql, int $maxResults = 50): array
    {
        $this->refreshTokenIfNeeded();
        
        $response = $this->request('GET', '/search', [
            'jql' => $jql,
            'maxResults' => $maxResults,
            'fields' => 'summary,description,status,issuetype,priority',
        ]);

        return $response['issues'] ?? [];
    }

    public function getIssue(string $issueKey): ?array
    {
        $this->refreshTokenIfNeeded();
        
        $response = $this->request('GET', "/issue/{$issueKey}", [
            'fields' => 'summary,description,status,issuetype,priority',
        ]);

        return $response;
    }

    public function updateStoryPoints(string $issueKey, float $points): bool
    {
        $this->refreshTokenIfNeeded();
        
        $fieldId = $this->user?->jira_story_points_field_id ?? $this->discoverStoryPointsField();
        
        if (!$fieldId) {
            Log::error('Story Points field not found for user', ['user_id' => $this->user?->id]);
            return false;
        }

        try {
            $this->request('PUT', "/issue/{$issueKey}", [
                'fields' => [
                    $fieldId => $points,
                ],
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update story points', [
                'issue_key' => $issueKey,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function discoverStoryPointsField(): ?string
    {
        $this->refreshTokenIfNeeded();
        
        $response = $this->request('GET', '/field');
        
        foreach ($response as $field) {
            $name = strtolower($field['name'] ?? '');
            $clauseNames = array_map('strtolower', $field['clauseNames'] ?? []);
            
            if (
                str_contains($name, 'story point') ||
                str_contains($name, 'story points') ||
                in_array('story points', $clauseNames) ||
                in_array('story point estimate', $clauseNames)
            ) {
                $fieldId = $field['id'];
                
                if ($this->user) {
                    $this->user->update(['jira_story_points_field_id' => $fieldId]);
                }
                
                return $fieldId;
            }
        }
        
        return null;
    }

    public function parseIssueUrl(string $url): ?string
    {
        if (preg_match('/[A-Z]+-\d+/', $url, $matches)) {
            return $matches[0];
        }
        
        return null;
    }

    public function refreshTokenIfNeeded(): void
    {
        if (!$this->user || !$this->user->token_expires_at) {
            return;
        }

        if ($this->user->token_expires_at->isPast()) {
            $this->refreshToken();
        }
    }

    public function refreshToken(): void
    {
        if (!$this->user || !$this->user->jira_refresh_token) {
            return;
        }

        try {
            $response = Http::asForm()->post('https://auth.atlassian.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.jira.client_id'),
                'client_secret' => config('services.jira.client_secret'),
                'refresh_token' => $this->user->jira_refresh_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->user->update([
                    'jira_access_token' => $data['access_token'],
                    'jira_refresh_token' => $data['refresh_token'] ?? $this->user->jira_refresh_token,
                    'token_expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh Jira token', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function request(string $method, string $path, array $data = []): array
    {
        if (!$this->user) {
            throw new \Exception('No user set for JiraService');
        }

        $url = $this->baseUrl . $path;
        
        $response = Http::withToken($this->user->jira_access_token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->$method($url, $data);

        if (!$response->successful()) {
            throw new \Exception('Jira API request failed: ' . $response->body());
        }

        return $response->json() ?? [];
    }
}
