<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight event-logger for security-relevant actions.
 *
 * This service writes structured log entries to the dedicated 'security'
 * channel (storage/logs/security-YYYY-MM-DD.log).
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
 *            Wazuh Manager  (rules 100100–100199)
 *                │
 *            Wazuh Indexer  (wazuh-alerts-*)
 *                │
 *            AISecurityReports dashboard
 *
 * DO NOT log passwords, tokens, session IDs, cookies, or any secret.
 *
 * ── Architecture note ─────────────────────────────────────────────────────
 * The WazuhSecurityMonitor HTTP middleware handles raw HTTP requests and
 * correctly fires SQLI_DETECTED / XSS_DETECTED for traditional form POSTs.
 * However, Livewire components communicate exclusively via POST /livewire/update,
 * which the middleware EXPLICITLY SKIPS (to avoid false positives on Livewire
 * internal state).  Therefore Livewire form submissions that contain suspicious
 * values bypass the middleware entirely and reach logFormSubmit() as plain
 * FORM_SUBMIT events.
 *
 * The fix: logFormSubmit() now inspects the submitted data array using the same
 * detection patterns as the middleware BEFORE deciding whether to emit
 * SQLI_DETECTED (and related events) or the generic FORM_SUBMIT.  This is
 * intentionally centralised here so every component caller gains detection
 * automatically without per-component changes.
 * ──────────────────────────────────────────────────────────────────────────
 */
class SecurityMonitoringService
{
    /** The Monolog channel that routes to storage/logs/security.log */
    private const CHANNEL = 'security';

    /**
     * Keys that are EXCLUDED from security scanning because they contain
     * framework/component internals, UI state, or non-user-input data.
     *
     * This prevents false positives from Livewire pagination cursors,
     * array-of-room objects, modal-open flags, etc., while still scanning
     * all genuine user-input string fields.
     *
     * Extend this list if new internal-state properties are added that
     * should never be treated as user input.
     */
    private const EXCLUDED_KEYS = [
        // Livewire pagination & internal
        'paginators', '_token', '__livewire', 'fingerprint', 'serverMemo',
        'updates', 'id', 'name', 'effects', 'dirty',
        // Component UI/state properties that are not user text input
        'activeTab', 'showConflictModal', 'showCapacityModal',
        'showCancelModal', 'showSidebarDetail', 'showSidebarReject',
        'capacityOverrideConfirmed', 'requestCancellation',
        'autoRefresh', 'selectedSeverity', 'expandedIndex',
        'statusFilter', 'perPage', 'page',
        'conflicting_booking_id', 'cancelTargetId', 'sidebarDetailId',
        // Data collections loaded server-side (not user input)
        'rooms', 'vehicles', 'bookings', 'alerts', 'summary',
        'wazuhAvailable', 'lastUpdated',
    ];

    /**
     * Maximum character length of a single string value that will be scanned.
     * Values longer than this are unlikely to be simple SQL injection attempts
     * and scanning them provides diminishing returns at increased CPU cost.
     */
    private const MAX_SCAN_LENGTH = 2000;

    /**
     * Maximum recursion depth when walking nested arrays.
     * Prevents stack overflow on deeply nested structures.
     */
    private const MAX_DEPTH = 6;

    // ─────────────────────────────────────────────────────────────────────────
    // Detection patterns (same as WazuhSecurityMonitor middleware so that both
    // HTTP-layer and Livewire-layer use identical logic).
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * SQL injection pattern.
     * Covers: UNION SELECT, SELECT FROM, INSERT INTO, DROP TABLE, DELETE FROM,
     *         UPDATE SET, OR N=N (including string-quoted variants like '1'='1'),
     *         and inline SQL comment --.
     */
    private const SQLI_PATTERN =
        "/['\"`]\s*(?:union[\s\/*]+select|select[\s\/*].+?from|insert[\s\/*]+into"
        . "|drop[\s\/*]+table|delete[\s\/*]+from|update[\s\/*]+\S+[\s\/*]+set)"
        . "|\bor\b\s+['\"]?\d+['\"]?\s*=\s*['\"]?\d+"
        . "|--\s*$/im";

    /** XSS pattern — script tags, javascript: URIs, event handlers, eval. */
    private const XSS_PATTERN =
        '/(<script[\s>]|javascript\s*:|onerror\s*=|onload\s*=|eval\s*\(|document\.cookie)/i';

    /** Command injection pattern — shell pipe/semicolons followed by known commands. */
    private const CMD_PATTERN =
        '/(\||;|&|`)\s*\b(ls|cat|whoami|pwd|wget|curl|echo|ping|bash|sh)\b/i';

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a form-submission event.
     *
     * Before emitting FORM_SUBMIT, this method scans the provided $data array
     * for SQLi, XSS, and command-injection patterns.  If a match is found,
     * the appropriate specific event (SQLI_DETECTED, XSS_DETECTED,
     * COMMAND_INJECTION_DETECTED) is emitted instead, and FORM_SUBMIT is
     * suppressed.  This mirrors the behaviour of the WazuhSecurityMonitor
     * middleware for non-Livewire requests.
     *
     * @param  string  $form  Human-readable component/form name (e.g. class_basename($this))
     * @param  array   $data  Submitted data array — may include full Livewire component state
     */
    public static function logFormSubmit(string $form, array $data): void
    {
        $ip     = (string) request()->ip();
        $route  = self::resolveRoute();
        $method = request()->method();

        // ── Security scan ────────────────────────────────────────────────────
        $threat = self::scanForThreats($data);

        if ($threat !== null) {
            // A threat was found: emit the specific security event.
            // FORM_SUBMIT is intentionally NOT emitted when a threat is detected
            // so that the Wazuh Indexer sees the specific rule (100105 etc.)
            // without noise from the generic base rule (100100/100101).
            Log::channel(self::CHANNEL)->warning($threat['event'], [
                'ip'     => $ip,
                'route'  => $route,
                'method' => $method,
                'form'   => $form,
                'field'  => $threat['field'],
                'detail' => $threat['detail'],
            ]);
            return;
        }

        // ── No threat detected: emit the generic informational event ─────────
        Log::channel(self::CHANNEL)->info('FORM_SUBMIT', [
            'ip'   => $ip,
            'form' => $form,
            'data' => self::sanitizeForLog($data),
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

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Walk a data array and check each string value for security threats.
     *
     * Returns an associative array with keys:
     *   'event'  – the log message name (SQLI_DETECTED, XSS_DETECTED, etc.)
     *   'field'  – dot-notation path to the triggering field (e.g. "meeting_title")
     *   'detail' – safe, non-secret description for forensic use
     *
     * Returns null if no threat is detected.
     *
     * Safety constraints:
     *   - Keys in EXCLUDED_KEYS are not scanned.
     *   - Only string values are scanned (booleans, integers, nulls are skipped).
     *   - Strings longer than MAX_SCAN_LENGTH are skipped.
     *   - Recursion is capped at MAX_DEPTH.
     *   - The matching sub-string is NOT included in the returned detail to
     *     avoid storing potentially malicious payloads in logs.
     *
     * @param  array   $data
     * @param  string  $prefix   Dot-notation key path for nested context
     * @param  int     $depth    Current recursion depth
     * @return array{event:string,field:string,detail:string}|null
     */
    private static function scanForThreats(array $data, string $prefix = '', int $depth = 0): ?array
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        foreach ($data as $key => $value) {
            // Build the dot-notation path for field identification
            $fieldPath = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

            // Skip framework/UI internal keys at top level and nested
            if (in_array((string) $key, self::EXCLUDED_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::scanForThreats($value, $fieldPath, $depth + 1);
                if ($nested !== null) {
                    return $nested;
                }
                continue;
            }

            if (!is_string($value)) {
                // Booleans, integers, nulls — not user text, skip
                continue;
            }

            if (strlen($value) > self::MAX_SCAN_LENGTH) {
                // Oversized values are not scanned — unlikely SQLi vectors
                continue;
            }

            // ── SQLi ─────────────────────────────────────────────────────────
            if (preg_match(self::SQLI_PATTERN, $value)) {
                return [
                    'event'  => 'SQLI_DETECTED',
                    'field'  => $fieldPath,
                    'detail' => 'SQL injection pattern matched in field',
                ];
            }

            // ── XSS ──────────────────────────────────────────────────────────
            if (preg_match(self::XSS_PATTERN, $value)) {
                return [
                    'event'  => 'XSS_DETECTED',
                    'field'  => $fieldPath,
                    'detail' => 'XSS pattern matched in field',
                ];
            }

            // ── Command injection ─────────────────────────────────────────────
            if (preg_match(self::CMD_PATTERN, $value)) {
                return [
                    'event'  => 'COMMAND_INJECTION_DETECTED',
                    'field'  => $fieldPath,
                    'detail' => 'Command injection pattern matched in field',
                ];
            }
        }

        return null;
    }

    /**
     * Sanitize a data array before writing it to the FORM_SUBMIT log.
     *
     * Removes keys that should never appear in logs (secrets, internal state,
     * large collections) and truncates long values.
     *
     * This is applied only for benign FORM_SUBMIT events.
     * Threat events use a separate, smaller context structure.
     */
    private static function sanitizeForLog(array $data, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['[truncated:depth]'];
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::EXCLUDED_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::sanitizeForLog($value, $depth + 1);
                continue;
            }

            if (is_string($value) && strlen($value) > 500) {
                $result[$key] = substr($value, 0, 500) . '[truncated]';
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Resolve the current application route for security event context.
     *
     * Livewire requests arrive via POST /livewire/update.  The real page
     * route is preserved in the Referer header for these requests.
     * For non-Livewire requests the actual URL path is used directly.
     *
     * This avoids the hardcoded "/it-officer/something" placeholder that
     * appeared in earlier test events and ensures the log shows the actual
     * Manager page URL (e.g. /manager-priority-room) not the Livewire transport.
     */
    private static function resolveRoute(): string
    {
        $request = request();

        // For Livewire AJAX requests, use the Referer as the application route
        if ($request->is('livewire/update') || $request->is('livewire/*')) {
            $referer = $request->header('Referer');
            if ($referer) {
                // Extract path only — strip scheme/host/query to avoid logging
                // full URLs that may contain tokens or user data
                $parsed = parse_url($referer, PHP_URL_PATH);
                return is_string($parsed) ? $parsed : $request->path();
            }
        }

        return $request->path();
    }
}
