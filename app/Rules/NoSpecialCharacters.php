<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\AISettings;

/**
 * Validation rule that blocks inappropriate special characters in text fields.
 *
 * Allowed  : letters, numbers, spaces, and basic punctuation (. , - ' ")
 * Blocked  : < > { } [ ] | \ / ; : ` ~ ! @ # $ % ^ & * ( ) = + ?
 *
 * The rule respects the global 'no_special_characters' setting stored in
 * ai_settings.  When that setting is OFF (value = 0 / false), the rule
 * passes immediately without checking the value — no other validation rules
 * are affected.
 */
class NoSpecialCharacters implements ValidationRule
{
    protected string $fieldName;
    protected array  $allowedPatterns;

    /**
     * @param string $fieldName      Human-readable name used in error messages.
     * @param array  $allowedPatterns Additional regex patterns that are allowed.
     */
    public function __construct(string $fieldName = 'field', array $allowedPatterns = [])
    {
        $this->fieldName       = $fieldName;
        $this->allowedPatterns = $allowedPatterns;
    }

    /**
     * Perform validation.
     *
     * When the global 'no_special_characters' toggle is OFF the method
     * returns immediately (passes), leaving all other rules (required, max,
     * email, unique, etc.) completely unaffected.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ── Global toggle check ───────────────────────────────────────────────
        // Reads from ai_settings via the cached AISettings model.
        // Default = true so existing behaviour is preserved when no row exists.
        $enabled = (bool) AISettings::get('no_special_characters', true);
        if (! $enabled) {
            return; // Setting is OFF – allow any characters for this rule only.
        }

        // ── Skip empty values ─────────────────────────────────────────────────
        // Other rules (e.g. 'required') handle the empty-value case.
        if (empty($value)) {
            return;
        }

        // ── Blocked character list ────────────────────────────────────────────
        $blockedChars = [
            '<', '>', '{', '}', '[', ']', '|', '\\', '/',
            ';', ':', '`', '~', '!', '@', '#', '$', '%',
            '^', '&', '*', '(', ')', '=', '+', '?',
        ];

        foreach ($blockedChars as $char) {
            if (str_contains($value, $char)) {
                $fail(
                    "The {$this->fieldName} contains invalid special characters ({$char}). " .
                    "Only letters, numbers, spaces, and basic punctuation (. , - ' \") are allowed."
                );
                return;
            }
        }

        // ── Control characters ────────────────────────────────────────────────
        if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', $value)) {
            $fail("The {$this->fieldName} contains invalid control characters.");
            return;
        }

        // ── Dangerous content patterns ────────────────────────────────────────
        // These checks remain active even when the blocked-char list is relaxed
        // via the toggle, because they guard against XSS / injection.
        // NOTE: these patterns only fire when $enabled is true (see early return
        // above), so they are intentionally inside the enabled block.
        $dangerousPatterns = [
            '/script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',  // onclick=, onerror=, etc.
            '/<\w+/i',       // HTML tags
            '/\${.*}/i',     // Template injection
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail("The {$this->fieldName} contains potentially dangerous content.");
                return;
            }
        }
    }
}
