<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enhanced email validation for Manager forms.
 * 
 * Rules:
 * - Maximum 1 @ symbol
 * - Prevent consecutive dots (e.g., user..name@example.com)
 * - Prevent dots at start or end of local part
 * - Prevent excessive dots (e.g., @example....com)
 * - Must pass basic email format validation
 */
class ValidManagerEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Let 'required' rule handle empty validation
        }

        // Count @ symbols - must be exactly 1
        $atCount = substr_count($value, '@');
        if ($atCount === 0) {
            $fail('The email address must contain an @ symbol.');
            return;
        }
        if ($atCount > 1) {
            $fail('The email address cannot contain more than one @ symbol.');
            return;
        }

        // Check for consecutive dots
        if (str_contains($value, '..')) {
            $fail('The email address cannot contain consecutive dots (..).');
            return;
        }

        // Split by @ to check local and domain parts
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            $fail('The email address format is invalid.');
            return;
        }

        [$local, $domain] = $parts;

        // Check local part (before @)
        if (empty($local)) {
            $fail('The email address must have a username before the @ symbol.');
            return;
        }

        // Local part cannot start or end with a dot
        if (str_starts_with($local, '.') || str_ends_with($local, '.')) {
            $fail('The email username cannot start or end with a dot.');
            return;
        }

        // Check domain part (after @)
        if (empty($domain)) {
            $fail('The email address must have a domain after the @ symbol.');
            return;
        }

        // Domain cannot start or end with a dot
        if (str_starts_with($domain, '.') || str_ends_with($domain, '.')) {
            $fail('The email domain cannot start or end with a dot.');
            return;
        }

        // Check for excessive dots in domain (more than 3 consecutive)
        if (preg_match('/\.{4,}/', $domain)) {
            $fail('The email domain contains too many consecutive dots.');
            return;
        }

        // Must contain at least one dot in domain (for TLD)
        if (!str_contains($domain, '.')) {
            $fail('The email domain must include a top-level domain (e.g., .com, .org).');
            return;
        }

        // Basic email format validation using filter_var
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The email address format is invalid.');
            return;
        }

        // Check for common typos with multiple dots
        if (preg_match('/@.*\.{2,}/', $value)) {
            $fail('The email domain appears to have a typo with multiple dots.');
            return;
        }
    }
}
