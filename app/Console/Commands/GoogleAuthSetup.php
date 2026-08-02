<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Calendar;
use Illuminate\Console\Command;

class GoogleAuthSetup extends Command
{
    protected $signature = 'google:auth
                            {--force : Overwrite existing token if present}';

    protected $description = 'Authorize this app to access Google Calendar (OAuth 2.0) and save the token. Run once on the server.';

    public function handle(): int
    {
        $clientSecretPath = config('services.google.client_secret_path', 'storage/app/google/client_secret.json');
        if (!str_starts_with($clientSecretPath, '/')) {
            $clientSecretPath = base_path($clientSecretPath);
        }

        $tokenPath = config('services.google.token_path', 'storage/app/google/token.json');
        if (!str_starts_with($tokenPath, '/')) {
            $tokenPath = base_path($tokenPath);
        }

        // --- Prerequisite: client_secret.json ---
        if (!file_exists($clientSecretPath)) {
            $this->error("client_secret.json not found at: {$clientSecretPath}");
            $this->line('');
            $this->line('To get it:');
            $this->line('  1. Go to https://console.cloud.google.com');
            $this->line('  2. APIs & Services → Credentials');
            $this->line('  3. Create OAuth 2.0 Client ID → Desktop app');
            $this->line('  4. Download the JSON and save it to:');
            $this->line("     {$clientSecretPath}");
            return self::FAILURE;
        }

        // --- Already have a valid token? ---
        if (!$this->option('force') && file_exists($tokenPath)) {
            $existing = json_decode(file_get_contents($tokenPath), true);
            if (!empty($existing['access_token']) || !empty($existing['refresh_token'])) {
                $this->info("A token already exists at: {$tokenPath}");
                $this->line('Use --force to overwrite it.');
                return self::SUCCESS;
            }
        }

        $client = new Client();
        $client->setAuthConfig($clientSecretPath);
        $client->setAccessType('offline');
        $client->setPrompt('consent');  
        $client->setScopes([
            Calendar::CALENDAR,
            Calendar::CALENDAR_EVENTS,
        ]);

        $authUrl = $client->createAuthUrl();

        $this->line('');
        $this->info('======================================================');
        $this->info('  Google OAuth Authorization');
        $this->info('======================================================');
        $this->line('Open the following URL in your browser and log in with');
        $this->line('the Gmail account that owns the Google Calendar:');
        $this->line('');
        $this->line("  <href={$authUrl}>{$authUrl}</>");
        $this->line('');
        $this->line('After authorizing, Google will show you a code.');
        $this->line('Paste it below.');
        $this->line('');

        $code = $this->ask('Enter the authorization code');

        if (empty($code)) {
            $this->error('No code entered. Aborting.');
            return self::FAILURE;
        }


        try {
            $token = $client->fetchAccessTokenWithAuthCode(trim($code));
        } catch (\Throwable $e) {
            $this->error('Failed to exchange code: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (isset($token['error'])) {
            $this->error('Google returned an error: ' . ($token['error_description'] ?? $token['error']));
            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->warn('Warning: no refresh_token in the response.');
            $this->warn('If the token expires the app cannot auto-refresh it.');
            $this->warn('To fix: go to https://myaccount.google.com/permissions, revoke this app, then re-run with --force.');
        }

        $dir = dirname($tokenPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($tokenPath, json_encode($token));

        $this->line('');
        $this->info("Token saved to: {$tokenPath}");
        $this->info('Google Meet integration is now active.');
        
        $this->line('');
        $this->line('Running a quick connection test...');
        try {
            $client->setAccessToken($token);
            $service = new Calendar($client);
            $service->calendarList->listCalendarList(['maxResults' => 1]);
            $this->info('Connection test passed. Calendar API is accessible.');
        } catch (\Throwable $e) {
            $this->warn('Connection test failed: ' . $e->getMessage());
            $this->warn('The token was saved but may not be working correctly.');
        }

        return self::SUCCESS;
    }
}
