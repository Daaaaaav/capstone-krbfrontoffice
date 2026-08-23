<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight event-logger for security-relevant actions.
 *
 * This service ONLY writes structured log entries (Log::info / Log::warning).
 * All attack detection, classification, and severity scoring is delegated to
 * the external Wazuh stack:
 *
 *   Laravel  ──Log::*──▶  log file  ──Wazuh Agent──▶  Wazuh Manager
 *            (this service)                          (rules & scoring)
 *                                                        │
 *                                               Wazuh Indexer
 *                                                        │
 *                                                   Dashboard
 *
 * Do NOT add regex patterns, classification logic, or severity scoring here.
 */
class SecurityMonitoringService
{
    /**
     * Record a form-submission event.
     *
     * The Wazuh Agent watching the Laravel log file will forward this entry
     * to the Wazuh Manager for analysis.
     */
    public static function logFormSubmit(string $form, array $data): void
    {
        Log::info('FORM_SUBMIT', [
            'ip'   => (string) request()->ip(),
            'form' => $form,
            'data' => $data,
        ]);
    }

    /**
     * Record a login-attempt event.
     *
     * Only the e-mail is logged — passwords are never persisted in logs.
     * The Wazuh Manager will handle brute-force detection through its
     * own rulesets.
     */
    public static function logLoginAttempt(?string $email): void
    {
        Log::info('LOGIN_ATTEMPT', [
            'ip'    => (string) request()->ip(),
            'email' => (string) ($email ?? ''),
        ]);
    }

    /**
     * Record a failed-login event.
     *
     * Wazuh Manager correlates repeated failures from the same IP to
     * trigger brute-force alerts automatically.
     */
    public static function logLoginFailed(?string $email): void
    {
        Log::warning('LOGIN_FAILED', [
            'ip'    => (string) request()->ip(),
            'email' => (string) ($email ?? ''),
        ]);
    }

    /**
     * Record a successful login event.
     */
    public static function logLoginSuccess(?string $email): void
    {
        Log::info('LOGIN_SUCCESS', [
            'ip'    => (string) request()->ip(),
            'email' => (string) ($email ?? ''),
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