<?php

namespace App\Http\Controllers;

use Google\Client;
use Google\Service\Calendar;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google's OAuth consent screen.
     * Only accessible when logged in as Manager.
     */
    public function auth()
    {
        $client = $this->makeClient();
        $authUrl = $client->createAuthUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Handle the OAuth callback from Google, save the token.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/manager-settings')
                ->with('error', 'Google authorization was denied: ' . $request->get('error'));
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect('/manager-settings')
                ->with('error', 'No authorization code received from Google.');
        }

        try {
            $client = $this->makeClient();
            $token  = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                return redirect('/manager-settings')
                    ->with('error', 'Google error: ' . ($token['error_description'] ?? $token['error']));
            }

            $tokenPath = config('services.google.token_path', 'storage/app/google/token.json');
            if (!str_starts_with($tokenPath, '/')) {
                $tokenPath = base_path($tokenPath);
            }

            $dir = dirname($tokenPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($tokenPath, json_encode($token));

            return redirect('/manager-settings')
                ->with('success', 'Google Calendar connected successfully. Google Meet links will now be generated automatically.');

        } catch (\Throwable $e) {
            return redirect('/manager-settings')
                ->with('error', 'Failed to save Google token: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect — delete the saved token.
     */
    public function disconnect()
    {
        $tokenPath = config('services.google.token_path', 'storage/app/google/token.json');
        if (!str_starts_with($tokenPath, '/')) {
            $tokenPath = base_path($tokenPath);
        }

        if (file_exists($tokenPath)) {
            unlink($tokenPath);
        }

        return redirect('/manager-settings')
            ->with('success', 'Google Calendar disconnected.');
    }

    private function makeClient(): Client
    {
        $clientSecretPath = config('services.google.client_secret_path', 'storage/app/google/client_secret.json');
        if (!str_starts_with($clientSecretPath, '/')) {
            $clientSecretPath = base_path($clientSecretPath);
        }

        $client = new Client();
        $client->setAuthConfig($clientSecretPath);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // always return refresh_token
        $client->setScopes([
            Calendar::CALENDAR,
            Calendar::CALENDAR_EVENTS,
        ]);

        return $client;
    }
}
