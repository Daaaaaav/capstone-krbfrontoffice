<?php

namespace App\Services;

use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZoomService
{
    protected Client $http;
    protected string $accountId;
    protected string $clientId;
    protected string $clientSecret;
    protected string $userId;

    public function __construct()
    {
        $this->http = new Client(['base_uri' => 'https://api.zoom.us']);
        $this->accountId    = config('services.zoom.account_id', '');
        $this->clientId     = config('services.zoom.client_id', '');
        $this->clientSecret = config('services.zoom.client_secret', '');
        $this->userId       = config('services.zoom.user_id', 'me');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accountId)
            && !empty($this->clientId)
            && !empty($this->clientSecret);
    }

    protected function getAccessToken(): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Zoom API credentials are not configured. Please set ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, and ZOOM_CLIENT_SECRET in your .env file.');
        }

        return Cache::remember('zoom_access_token', 55 * 60, function () {
            try {
                $resp = $this->http->post('/oauth/token', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                    ],
                    'form_params' => [
                        'grant_type' => 'account_credentials',
                        'account_id' => $this->accountId,
                    ],
                ]);

                $json = json_decode((string) $resp->getBody(), true);
                return $json['access_token'];
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $body = (string) $e->getResponse()->getBody();
                $error = json_decode($body, true);
                $reason = $error['reason'] ?? $error['error'] ?? 'Unknown error';

                Cache::forget('zoom_access_token');
                Log::error("Zoom OAuth failed: {$reason}", ['response' => $body]);

                throw new \RuntimeException("Zoom authentication failed: {$reason}. Please check that your Zoom app is active in the Zoom Marketplace and credentials are correct.");
            }
        });
    }

    public function createMeeting(string $topic, Carbon $start, Carbon $end, ?string $agenda = null): array
    {
        $token = $this->getAccessToken();

        $duration = max(15, $end->diffInMinutes($start));
        $payload = [
            'topic' => $topic,
            'type' => 2,
            'start_time' => $start->copy()->tz('UTC')->format('Y-m-d\TH:i:s\Z'),
            'duration' => $duration,
            'timezone' => $start->getTimezone()->getName(),
            'agenda' => $agenda ?? '',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'waiting_room' => true,
                'approval_type' => 0,
            ],
        ];

        $resp = $this->http->post("/v2/users/{$this->userId}/meetings", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
        ]);

        $json = json_decode((string) $resp->getBody(), true);

        return [
            'url' => $json['join_url'] ?? null,
            'code' => $json['id'] ?? null,
            'password' => $json['password'] ?? null,
        ];
    }

    public function deleteMeeting(string $meetingId): bool
    {
        try {
            $token = $this->getAccessToken();

            $this->http->delete("/v2/meetings/{$meetingId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
