<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Phone number validation for Manager forms.
 * 
 * Rules:
 * - Maximum 1 plus (+) symbol
 * - Plus symbol only allowed at the beginning
 * - Maximum 4 dash (-) symbols
 * - Must contain at least some digits
 * - Allows spaces, numbers, +, and -
 */
class ValidManagerPhone implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Let 'required' rule handle empty validation if needed
        }

        // Count plus symbols - maximum 1 allowed
        $plusCount = substr_count($value, '+');
        if ($plusCount > 1) {
            $fail('The phone number cannot contain more than one plus (+) symbol.');
            return;
        }

        // If there's a plus, it must be at the beginning
        if ($plusCount === 1) {
            $firstPlusPos = strpos($value, '+');
            if ($firstPlusPos !== 0) {
                $fail('The plus (+) symbol can only appear at the beginning of the phone number.');
                return;
            }
        }

        // Count dash symbols - maximum 4 allowed
        $dashCount = substr_count($value, '-');
        if ($dashCount > 4) {
            $fail('The phone number cannot contain more than 4 dashes (-).');
            return;
        }

        // Must contain at least one digit
        if (!preg_match('/\d/', $value)) {
            $fail('The phone number must contain at least one digit.');
            return;
        }

        // Check for valid characters only: digits, spaces, +, -, and parentheses
        if (!preg_match('/^[\d\s\+\-\(\)]+$/', $value)) {
            $fail('The phone number contains invalid characters. Only numbers, spaces, +, -, and parentheses are allowed.');
            return;
        }

        // Prevent excessive consecutive dashes or spaces
        if (preg_match('/[-\s]{4,}/', $value)) {
            $fail('The phone number contains too many consecutive dashes or spaces.');
            return;
        }

        // Check minimum length (at least 7 digits for a valid phone number)
        $digitCount = preg_match_all('/\d/', $value);
        if ($digitCount < 7) {
            $fail('The phone number must contain at least 7 digits.');
            return;
        }

        // Check maximum length (international numbers typically max 15 digits)
        if (strlen(preg_replace('/[^\d]/', '', $value)) > 15) {
            $fail('The phone number is too long (maximum 15 digits).');
            return;
        }
    }
}
