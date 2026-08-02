<?php

namespace App\Services;

use App\Models\AISettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SecurityMonitoringService
{
    // fallback values used only if the settings table is not yet seeded
    private const FORM_SPAM_THRESHOLD_DEFAULT      = 10;
    private const FORM_SPAM_WINDOW_SECONDS_DEFAULT = 60;

    public static function logFormSubmit(string $form, array $data): void
    {
        $ip = (string) request()->ip();

        Log::info('FORM_SUBMIT', [
            'ip' => $ip,
            'form' => $form,
            'data' => $data,
        ]);

        self::inspectPayload($data, 'form_submit', $form);
        self::trackFormSpam($form, $ip);
    }

    public static function inspectLoginPayload(?string $email, ?string $password = null): void
    {
        $payload = [
            'email' => (string) ($email ?? ''),
            'password' => (string) ($password ?? ''),
        ];

        self::inspectPayload($payload, 'login');
    }

    public static function inspectPayload(array $payload, string $event, ?string $form = null): void
    {
        $flatPayload = self::flattenPayload($payload);
        $ip = (string) request()->ip();
        $path = request()->path();

        foreach ($flatPayload as $field => $value) {
            if ($value === '') {
                continue;
            }

            if (self::looksLikeSqlInjection($value)) {
                Log::warning("SQLI_DETECTED ' OR 1=1--", [
                    'ip' => $ip,
                    'path' => $path,
                    'event' => $event,
                    'form' => $form,
                    'field' => $field,
                    'payload' => $value,
                ]);
            }

            if (self::looksLikeXss($value)) {
                Log::warning('XSS_DETECTED <script>alert(1)</script>', [
                    'ip' => $ip,
                    'path' => $path,
                    'event' => $event,
                    'form' => $form,
                    'field' => $field,
                    'payload' => $value,
                ]);
            }
        }
    }

    private static function trackFormSpam(string $form, string $ip): void
    {
        $threshold     = (int)   AISettings::get('spam_threshold',      self::FORM_SPAM_THRESHOLD_DEFAULT);
        $windowSeconds = (int)   AISettings::get('spam_window_seconds', self::FORM_SPAM_WINDOW_SECONDS_DEFAULT);

        $key      = sprintf('form-spam:%s:%s', $form, $ip);
        RateLimiter::hit($key, $windowSeconds);

        $attempts = RateLimiter::attempts($key);
        if ($attempts >= $threshold) {
            Log::warning('FORM_SPAM_DETECTED', [
                'ip'             => $ip,
                'form'           => $form,
                'attempts'       => $attempts,
                'window_seconds' => $windowSeconds,
            ]);
        }
    }

    private static function flattenPayload(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                $flat = array_merge($flat, self::flattenPayload($value, $name));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$name] = (string) ($value ?? '');
            }
        }

        return $flat;
    }

    private static function looksLikeSqlInjection(string $value): bool
    {
        $patterns = [
            '/\b(or|and)\b\s+\d+\s*=\s*\d+/i',
            '/\b(or|and)\b\s+[\'\"]?\w+[\'\"]?\s*=\s*[\'\"]?\w+[\'\"]?/i',
            '/\bunion\b\s+\bselect\b/i',
            '/\bdrop\b\s+\btable\b/i',
            '/\binsert\b\s+\binto\b/i',
            '/\bdelete\b\s+\bfrom\b/i',
            '/\bselect\b\s+.*\bfrom\b/i',
            '/--|#|\/\*/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function looksLikeXss(string $value): bool
    {
        $patterns = [
            '/<\s*script\b[^>]*>/i',
            '/javascript\s*:/i',
            '/on\w+\s*=\s*[\"\'][^\"\']*[\"\']/i',
            '/<\s*img\b[^>]*onerror\s*=/i',
            '/<\s*svg\b[^>]*onload\s*=/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}