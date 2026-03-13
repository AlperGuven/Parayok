<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraService
{
    private const AUTH_URL = 'https://auth.atlassian.com/oauth/token';
    private const API_URL = 'https://api.atlassian.com';

    private ?User $user;
    private string $baseUrl;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
        
        if ($user && $user->jira_cloud_id) {
            $this->baseUrl = self::API_URL . "/ex/jira/{$user->jira_cloud_id}/rest/api/3";
        } else {
            $this->baseUrl = '';
        }
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::AUTH_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.atlassian.client_id'),
            'client_secret' => config('services.atlassian.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.atlassian.redirect'),
        ]);

        if ($response->failed()) {
            Log::error('Jira Token Exchange Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'sent_redirect_uri' => config('services.atlassian.redirect'),
            ]);
            throw new \Exception('Failed to get token from Atlassian: ' . $response->body());
        }

        return $response->json();
    }

    public function getAtlassianUser(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::API_URL . '/me');

        if ($response->failed()) {
            throw new \Exception('Failed to get user from Atlassian');
        }

        return $response->json();
    }

    public function getAccessibleResources(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ])->get(self::AUTH_URL . '/accessible-resources');

        // Note: accessible-resources endpoint is actually at api.atlassian.com/oauth/token/accessible-resources
        // Let's fix the URL usage in getAccessibleResources to be correct
        $response = Http::withToken($accessToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_URL . '/oauth/token/accessible-resources');

        return $response->json() ?? [];
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->baseUrl = self::API_URL . "/ex/jira/{$user->jira_cloud_id}/rest/api/3";
        
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
            $response = Http::asForm()->post(self::AUTH_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.atlassian.client_id'),
                'client_secret' => config('services.atlassian.client_secret'),
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
            if ($response->status() === 401) {
                abort(401, 'Jira Authentication Failed');
            }
            throw new \Exception('Jira API request failed: ' . $response->body());
        }

        return $response->json() ?? [];
    }
}
