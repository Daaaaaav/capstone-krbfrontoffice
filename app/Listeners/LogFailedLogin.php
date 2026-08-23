<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Services\SecurityMonitoringService;

/**
 * Listener for Illuminate\Auth\Events\Failed.
 *
 * Writes structured security log entries to the dedicated 'security' channel
 * (storage/logs/security-YYYY-MM-DD.log) via SecurityMonitoringService.
 *
 * The previous plain-text format ("level 5 srcip: ... -> LOGIN_FAILED") has
 * been replaced with structured JSON so the Wazuh decoder
 * (wazuh/decoders/krb_laravel_security_decoders.xml) can parse field values
 * reliably rather than relying on regex substring matching.
 *
 * Brute-force threshold: 5 failures within 5 minutes from the same IP
 * triggers a BRUTE_FORCE_DETECTED entry (matched by Wazuh rule 100814).
 */
class LogFailedLogin
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Failed $event): void
    {
        $ip    = $this->request->ip() ?? '127.0.0.1';
        $email = ($event->credentials['email'] ?? null);

        // Track failure count per IP in the cache (5-minute window)
        $cacheKey = 'failed_login_attempts_' . $ip;
        $attempts = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $attempts, now()->addMinutes(5));

        if ($attempts >= 5) {
            // Escalate to BRUTE_FORCE_DETECTED — matched by Wazuh rule 100814 (level 12)
            SecurityMonitoringService::logBruteForce($email, $attempts);
        } else {
            // Standard failed login — matched by Wazuh rule 100812 (level 6)
            SecurityMonitoringService::logLoginFailed($email, 'invalid_credentials');
        }
    }
}
