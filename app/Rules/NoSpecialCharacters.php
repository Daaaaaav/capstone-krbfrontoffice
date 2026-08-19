<?php

// namespace App\Rules;

// use Closure;
// use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule to block inappropriate special characters in text fields.
 * 
 * This rule prevents SQL injection patterns, scripting attempts, and other
 * suspicious special characters while allowing legitimate characters needed
 * for normal text input.
 * 
 * Allowed: letters, numbers, spaces, basic punctuation (. , - ' ")
 * Blocked: <, >, {, }, [, ], |, \, /, ;, :, `, ~, !, @, #, $, %, ^, &, *, (, ), =, +, ?
 */
// class NoSpecialCharacters implements ValidationRule
// {
//     protected array $allowedPatterns;
//     protected string $fieldName;

//     /**
//      * @param string $fieldName Field name for error message
//      * @param array $allowedPatterns Additional regex patterns to allow (optional)
//      */
//     public function __construct(string $fieldName = 'field', array $allowedPatterns = [])
//     {
//         $this->fieldName = $fieldName;
//         $this->allowedPatterns = $allowedPatterns;
//     }

//     /**
//      * Run the validation rule.
//      */
//     public function validate(string $attribute, mixed $value, Closure $fail): void
//     {
//         if (empty($value)) {
//             return; // Skip validation for empty values
//         }

//         // Define blocked characters
//         $blockedChars = [
//             '<', '>', '{', '}', '[', ']', '|', '\\', '/', 
//             ';', ':', '`', '~', '!', '@', '#', '$', '%', 
//             '^', '&', '*', '(', ')', '=', '+', '?'
//         ];

//         // Check for blocked characters
//         foreach ($blockedChars as $char) {
//             if (str_contains($value, $char)) {
//                 $fail("The {$this->fieldName} contains invalid special characters ({$char}). Only letters, numbers, spaces, and basic punctuation (. , - ' \") are allowed.");
//                 return;
//             }
//         }

//         // Additional check: block any control characters or unusual Unicode
//         if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', $value)) {
//             $fail("The {$this->fieldName} contains invalid control characters.");
//             return;
//         }

//         // Check for script injection patterns
//         $dangerousPatterns = [
//             '/script/i',
//             '/javascript:/i',
//             '/on\w+\s*=/i', // onclick=, onerror=, etc.
//             '/<\w+/i',      // HTML tags
//             '/\${.*}/i',    // Template injection
//         ];

//         foreach ($dangerousPatterns as $pattern) {
//             if (preg_match($pattern, $value)) {
//                 $fail("The {$this->fieldName} contains potentially dangerous content.");
//                 return;
//             }
//         }
//     }
// }
