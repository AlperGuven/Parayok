<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JiraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;

class JiraAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('atlassian')->redirect();
    }

    public function callback(JiraService $jiraService)
    {
        $atlassianUser = Socialite::driver('atlassian')->user();

        return $this->processUser($atlassianUser, $jiraService);
    }

    public function handleCallback(Request $request, JiraService $jiraService)
    {
        $code = $request->input('code');
        $state = $request->input('state');

        $config = config('services.atlassian');

        $response = Http::asForm()->post('https://auth.atlassian.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $config['redirect'],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to get token'], 400);
        }

        $tokenData = $response->json();

        $userResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ])->get('https://api.atlassian.com/me');

        $atlassianUserData = $userResponse->json();

        $atlassianUser = new \stdClass();
        $atlassianUser->id = $atlassianUserData['account_id'];
        $atlassianUser->name = $atlassianUserData['name'] ?? null;
        $atlassianUser->nickname = $atlassianUserData['email'];
        $atlassianUser->email = $atlassianUserData['email'];
        $atlassianUser->avatar = $atlassianUserData['picture'];
        $atlassianUser->token = $tokenData['access_token'];
        $atlassianUser->refreshToken = $tokenData['refresh_token'] ?? null;
        $atlassianUser->expiresIn = $tokenData['expires_in'] ?? 3600;

        return $this->processUser($atlassianUser, $jiraService);
    }

    protected function processUser($atlassianUser, JiraService $jiraService)
    {
        $accessibleResources = $jiraService->getAccessibleResources($atlassianUser->token);
        
        if (empty($accessibleResources)) {
            return response()->json(['error' => 'No Jira sites found'], 400);
        }

        $site = $accessibleResources[0];
        $cloudId = $site['id'] ?? null;
        $siteUrl = $site['url'] ?? null;

        if (!$cloudId || !$siteUrl) {
            return response()->json(['error' => 'Invalid Jira site information'], 400);
        }

        $user = User::where('jira_account_id', $atlassianUser->id)->first();

        if ($user) {
            $user->update([
                'jira_access_token' => $atlassianUser->token,
                'jira_refresh_token' => $atlassianUser->refreshToken,
                'token_expires_at' => now()->addSeconds($atlassianUser->expiresIn),
            ]);
        } else {
            $user = User::create([
                'jira_account_id' => $atlassianUser->id,
                'display_name' => $atlassianUser->name ?? $atlassianUser->nickname,
                'email' => $atlassianUser->email,
                'avatar_url' => $atlassianUser->avatar,
                'jira_access_token' => $atlassianUser->token,
                'jira_refresh_token' => $atlassianUser->refreshToken,
                'jira_cloud_id' => $cloudId,
                'jira_site_url' => $siteUrl,
                'token_expires_at' => now()->addSeconds($atlassianUser->expiresIn),
            ]);
        }

        Auth::login($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true, 
            'redirect' => '/dashboard',
            'user' => $user->toArray(),
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return $request->user();
    }
}
