<?php

namespace App\Livewire\Traits;

use App\Rules\NoSpecialCharacters;
use App\Rules\ValidManagerEmail;
use App\Rules\ValidManagerPhone;

/**
 * Trait for adding standardized validation to Manager-facing forms.
 * 
 * This trait provides helper methods to apply consistent validation rules
 * across all Manager pages for text fields, email fields, and phone fields.
 * 
 * Usage in Livewire component:
 * 
 * use HasManagerValidation;
 * 
 * protected function rules()
 * {
 *     return [
 *         'name' => $this->managerTextRules('Name', required: true, maxLength: 255),
 *         'email' => $this->managerEmailRules(required: true),
 *         'phone' => $this->managerPhoneRules(required: false),
 *     ];
 * }
 */
trait HasManagerValidation
{
    /**
     * Get validation rules for a standard text field (name, title, description, etc.)
     * 
     * @param string $fieldLabel Human-readable field name for error messages
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerTextRules(
        string $fieldLabel = 'field',
        bool $required = true,
        int $maxLength = 255,
        array $additionalRules = []
    ): array {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'string';
        $rules[] = "max:{$maxLength}";
        $rules[] = new NoSpecialCharacters($fieldLabel);

        return array_merge($rules, $additionalRules);
    }

    /**
     * Get validation rules for email fields
     * 
     * @param bool $required Whether the field is required
     * @param string|null $uniqueTable Table name for unique validation (e.g., 'users')
     * @param string|null $uniqueColumn Column name for unique validation (e.g., 'email')
     * @param mixed $ignoreId ID to ignore for unique validation (for edit mode)
     * @param string $ignoreColumn Column name for the ID (default: 'id')
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerEmailRules(
        bool $required = true,
        ?string $uniqueTable = null,
        ?string $uniqueColumn = 'email',
        mixed $ignoreId = null,
        string $ignoreColumn = 'id',
        array $additionalRules = []
    ): array {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'email';
        $rules[] = 'max:255';
        $rules[] = new ValidManagerEmail();

        // Add unique validation if specified
        if ($uniqueTable !== null) {
            if ($ignoreId !== null) {
                $rules[] = "unique:{$uniqueTable},{$uniqueColumn},{$ignoreId},{$ignoreColumn}";
            } else {
                $rules[] = "unique:{$uniqueTable},{$uniqueColumn}";
            }
        }

        return array_merge($rules, $additionalRules);
    }

    /**
     * Get validation rules for phone number fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length (default: 20)
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerPhoneRules(
        bool $required = false,
        int $maxLength = 20,
        array $additionalRules = []
    ): array {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'string';
        $rules[] = "max:{$maxLength}";
        $rules[] = new ValidManagerPhone();

        return array_merge($rules, $additionalRules);
    }

    /**
     * Get validation rules for company/organization name fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerCompanyRules(
        bool $required = true,
        int $maxLength = 255,
        array $additionalRules = []
    ): array {
        return $this->managerTextRules('Company name', $required, $maxLength, $additionalRules);
    }

    /**
     * Get validation rules for department name fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerDepartmentRules(
        bool $required = true,
        int $maxLength = 255,
        array $additionalRules = []
    ): array {
        return $this->managerTextRules('Department name', $required, $maxLength, $additionalRules);
    }

    /**
     * Get validation rules for room name fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerRoomRules(
        bool $required = true,
        int $maxLength = 150,
        array $additionalRules = []
    ): array {
        return $this->managerTextRules('Room name', $required, $maxLength, $additionalRules);
    }

    /**
     * Get validation rules for vehicle name fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerVehicleRules(
        bool $required = true,
        int $maxLength = 150,
        array $additionalRules = []
    ): array {
        return $this->managerTextRules('Vehicle name', $required, $maxLength, $additionalRules);
    }

    /**
     * Get validation rules for meeting title fields
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerMeetingTitleRules(
        bool $required = true,
        int $maxLength = 255,
        array $additionalRules = []
    ): array {
        return $this->managerTextRules('Meeting title', $required, $maxLength, $additionalRules);
    }

    /**
     * Get validation rules for general notes/description fields
     * Notes fields typically allow more length and may have slightly different requirements
     * 
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @param array $additionalRules Additional Laravel validation rules
     * @return array
     */
    protected function managerNotesRules(
        bool $required = false,
        int $maxLength = 1000,
        array $additionalRules = []
    ): array {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'string';
        $rules[] = "max:{$maxLength}";
        // Notes can contain more varied content, so we use a more permissive validation
        // but still block the most dangerous characters
        $rules[] = new NoSpecialCharacters('Notes');

        return array_merge($rules, $additionalRules);
    }

    /**
     * Validate a field using the manager text rules and return sanitized value
     * Useful for dynamic validation outside of standard Livewire validation
     * 
     * @param mixed $value The value to validate
     * @param string $fieldLabel Human-readable field name
     * @param bool $required Whether the field is required
     * @param int $maxLength Maximum character length
     * @return string|null Sanitized value or null
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateAndSanitizeManagerText(
        mixed $value,
        string $fieldLabel = 'field',
        bool $required = true,
        int $maxLength = 255
    ): ?string {
        $validator = validator(
            ['field' => $value],
            ['field' => $this->managerTextRules($fieldLabel, $required, $maxLength)]
        );

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $value ? trim($value) : null;
    }
}
