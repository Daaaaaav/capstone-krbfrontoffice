<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight event-logger for security-relevant actions.
 *
 * This service ONLY writes structured log entries to the dedicated
 * 'security' channel (storage/logs/security-YYYY-MM-DD.log).
 *
 * Keeping security events in a separate file lets the Wazuh agent
 * monitor only that file — avoiding false positives from the high-volume
 * general laravel.log — and matches the decoder defined in:
 *   wazuh/decoders/krb_laravel_security_decoders.xml
 *
 * Pipeline:
 *   Laravel  ──Log::channel('security')──▶  security.log
 *            (this service)
 *                │
 *            Wazuh Agent (localfile entry in ossec.conf)
 *                │
 *            Wazuh Manager  (rules 100810-100817)
 *                │
 *            Wazuh Indexer  (wazuh-alerts-*)
 *                │
 *            AISecurityReports dashboard
 *
 * DO NOT add regex patterns, classification logic, or severity scoring here.
 * DO NOT log passwords, tokens, session IDs, cookies, or any secret.
 */
class SecurityMonitoringService
{
    /** The Monolog channel that routes to storage/logs/security.log */
    private const CHANNEL = 'security';

    /**
     * Record a form-submission event.
     */
    public static function logFormSubmit(string $form, array $data): void
    {
        Log::channel(self::CHANNEL)->info('FORM_SUBMIT', [
            'ip'   => (string) request()->ip(),
            'form' => $form,
            'data' => $data,
        ]);
    }

    /**
     * Record a login-attempt event.
     *
     * Only the e-mail is logged — passwords are never persisted in logs.
     */
    public static function logLoginAttempt(?string $email): void
    {
        Log::channel(self::CHANNEL)->info('LOGIN_ATTEMPT', [
            'ip'    => (string) request()->ip(),
            'email' => (string) ($email ?? ''),
        ]);
    }

    /**
     * Record a failed-login event.
     *
     * Wazuh Manager correlates repeated failures from the same IP to
     * trigger brute-force alerts automatically via rule 100814.
     */
    public static function logLoginFailed(?string $email, string $reason = 'invalid_credentials'): void
    {
        Log::channel(self::CHANNEL)->warning('LOGIN_FAILED', [
            'ip'     => (string) request()->ip(),
            'email'  => (string) ($email ?? ''),
            'reason' => $reason,
        ]);
    }

    /**
     * Record a successful login event.
     */
    public static function logLoginSuccess(?string $email, ?int $userId = null): void
    {
        Log::channel(self::CHANNEL)->info('LOGIN_SUCCESS', [
            'ip'      => (string) request()->ip(),
            'email'   => (string) ($email ?? ''),
            'user_id' => $userId,
        ]);
    }

    /**
     * Record a brute-force detection event.
     * Called when repeated failed logins from the same IP exceed threshold.
     */
    public static function logBruteForce(?string $email, int $attempts): void
    {
        Log::channel(self::CHANNEL)->warning('BRUTE_FORCE_DETECTED', [
            'ip'       => (string) request()->ip(),
            'email'    => (string) ($email ?? ''),
            'attempts' => $attempts,
        ]);
    }

    /**
     * @deprecated Use logLoginAttempt() instead. Kept temporarily so
     *             existing callers do not break.
     */
    public static function inspectLoginPayload(?string $email, ?string $password = null): void
    {
        self::logLoginAttempt($email);
    }
}
