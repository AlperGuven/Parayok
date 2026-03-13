<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Atlassian\Provider as AtlassianProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        require base_path('routes/channels.php');
        
        Socialite::extend('atlassian', function ($app) {
            $config = $app['config']['services.atlassian'];
            $provider = new AtlassianProvider(
                $app['request'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect'] ?? '',
                ['guzzle' => ['http_errors' => false]]
            );
            $provider->setScopes([
                'read:me',
                'read:jira-user',
                'read:jira-work',
                'write:jira-work',
            ]);
            return $provider;
        });
    }
}
