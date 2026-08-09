<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptchaService
{
    public static function verify(string $token, ?string $remoteIp = null): bool
    {
        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => false,
                ])
                ->asForm()
                ->post(
                    'https://www.google.com/recaptcha/api/siteverify',
                    [
                        'secret' => config('services.recaptcha.secret'),
                        'response' => $token,
                        'remoteip' => $remoteIp ?? request()->ip(),
                    ]
                );

            $data = $response->json();
            
            if (!$response->successful() || !isset($data['success'])) {
                Log::warning('Captcha verification request failed', [
                    'status' => $response->status(),
                    'response' => $data
                ]);
                return false;
            }

            return $data['success'] === true;
        } catch (\Exception $e) {
            Log::error('Captcha verification exception', [
                'message' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...'
            ]);
            return false;
        }
    }
}
