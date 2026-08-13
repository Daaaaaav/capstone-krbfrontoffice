<?php

namespace App\Services\AI;

/**
 * ItOfficerQuickSubmitService
 *
 * Manages structured submission state for the IT Officer chatbot Quick Submit
 * workflow. Instead of relying on free-form AI memory, this service tracks
 * exactly which fields have been collected, which are still missing, and
 * whether a confirmation is pending before a write operation is executed.
 *
 * State shape mirrors the structure used in BookingDraftService so the
 * ChatModal component can follow the same lifecycle pattern.
 *
 * IMPORTANT: passwords are NEVER stored in the state text/summary fields.
 * They are held only in the $collected array and cleared after use.
 */
class ItOfficerQuickSubmitService
{
    /**
     * Return an empty (inactive) state structure.
     */
    public function emptyState(): array
    {
        return [
            'entity'        => null,   // 'user' | 'room' | 'vehicle' | 'storage'
            'action'        => null,   // 'create' | 'update'
            'active'        => false,
            'collected'     => [],     // field => value (NEVER include password here in plain text after hashing)
            'missing'       => [],     // list of required field names still needed
            'errors'        => [],     // validation error messages
            'confirmed'     => false,  // user has explicitly confirmed
            'awaiting_confirm' => false, // we've asked for confirmation, waiting for response
            'turns'         => 0,
            'target_id'     => null,   // For updates: the resolved entity ID
        ];
    }

    /**
     * Start a new Quick Submit flow for the given entity and action.
     */
    public function startFlow(array $state, string $entity, string $action): array
    {
        $fresh              = $this->emptyState();
        $fresh['entity']    = $entity;
        $fresh['action']    = $action;
        $fresh['active']    = true;
        $fresh['collected'] = [];
        $fresh['missing']   = $this->requiredFieldsFor($entity, $action);
        return $fresh;
    }

    /**
     * Merge new field values extracted by the AI into the existing state.
     * Returns updated state. Does NOT overwrite already-collected fields
     * with null/empty values.
     *
     * Password is accepted but never echoed in summaries.
     */
    public function mergeFields(array $state, array $newFields): array
    {
        if (! $state['active']) {
            return $state;
        }

        foreach ($newFields as $key => $value) {
            if ($value !== null && $value !== '') {
                $state['collected'][$key] = $value;
            }
        }

        // Recompute missing
        $state['missing'] = $this->computeMissing($state);
        $state['turns']++;
        $state['confirmed']        = false;
        $state['awaiting_confirm'] = false;

        return $state;
    }

    /**
     * Mark the state as awaiting confirmation (we've shown the summary,
     * waiting for user's "Ya / Yes / Lanjut / Confirm").
     */
    public function requestConfirmation(array $state): array
    {
        $state['awaiting_confirm'] = true;
        $state['confirmed']        = false;
        return $state;
    }

    /**
     * Check whether a user message is an explicit confirmation.
     * Accepts: ya, yes, lanjut, submit, confirm, konfirmasi, iya, ok, setuju
     */
    public function isConfirmation(string $message): bool
    {
        $msg = mb_strtolower(trim($message));
        $confirmPhrases = [
            'ya', 'yes', 'lanjut', 'submit', 'confirm', 'konfirmasi',
            'iya', 'ok', 'setuju', 'proceed', 'lanjutkan', 'oke',
        ];
        foreach ($confirmPhrases as $phrase) {
            if ($msg === $phrase || str_starts_with($msg, $phrase . ' ') || str_ends_with($msg, ' ' . $phrase)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mark as confirmed.
     */
    public function confirm(array $state): array
    {
        $state['confirmed']        = true;
        $state['awaiting_confirm'] = false;
        return $state;
    }

    /**
     * Reset / clear the state.
     */
    public function reset(): array
    {
        return $this->emptyState();
    }

    /**
     * Build a human-readable context block injected into the system prompt
     * so the AI understands what has been collected.
     * NEVER includes passwords.
     */
    public function buildStateContext(array $state): string
    {
        if (! $state['active']) {
            return '';
        }

        $lines = [
            'ACTIVE QUICK SUBMIT STATE:',
            'Entity : ' . ($state['entity'] ?? '-'),
            'Action : ' . ($state['action'] ?? '-'),
        ];

        $safeCollected = $this->safeCollectedFields($state);
        if (! empty($safeCollected)) {
            $lines[] = 'Collected fields:';
            foreach ($safeCollected as $k => $v) {
                $lines[] = "  {$k} = {$v}";
            }
        }

        if (! empty($state['missing'])) {
            $lines[] = 'Still missing: ' . implode(', ', $state['missing']);
        }

        if (! empty($state['errors'])) {
            $lines[] = 'Validation errors: ' . implode('; ', $state['errors']);
        }

        if ($state['awaiting_confirm']) {
            $lines[] = 'STATUS: Waiting for user confirmation. Do NOT re-ask for fields.';
            $lines[] = 'Accept: Ya / Yes / Lanjut / Submit / Confirm / Konfirmasi';
        } elseif ($state['confirmed']) {
            $lines[] = 'STATUS: User has confirmed. Execute the operation.';
        } elseif (empty($state['missing'])) {
            $lines[] = 'STATUS: All required fields collected. Show confirmation summary and ask user to confirm.';
        } else {
            $lines[] = 'STATUS: Still collecting fields. Ask ONLY for the next missing field(s).';
        }

        if ($state['target_id']) {
            $lines[] = 'Target entity ID: ' . $state['target_id'];
        }

        return implode("\n", $lines);
    }

    /**
     * Build a safe confirmation summary (no passwords).
     */
    public function buildConfirmationSummary(array $state): string
    {
        $entity = ucfirst($state['entity'] ?? 'entity');
        $action = $state['action'] === 'create' ? 'create' : 'update';
        $lines  = ["Please confirm — I will {$action} the following {$entity}:"];

        foreach ($this->safeCollectedFields($state) as $k => $v) {
            $label   = ucwords(str_replace('_', ' ', $k));
            $lines[] = "  {$label}: {$v}";
        }

        if ($state['action'] === 'create') {
            $lines[] = '';
            $lines[] = 'Type **Ya / Yes / Confirm** to proceed, or correct any details.';
        } else {
            $lines[] = '';
            $lines[] = 'Type **Ya / Yes / Confirm** to apply these changes, or type what to correct.';
        }

        return implode("\n", $lines);
    }

    /**
     * Detect entity and action intent from a user message.
     * Returns ['entity' => '...', 'action' => '...'] or empty array if none found.
     */
    public function detectIntent(string $message): array
    {
        $msg = mb_strtolower($message);

        // Detect action
        $action = null;
        if ($this->matches($msg, ['tambah', 'buat', 'add', 'create', 'daftarkan', 'register', 'new', 'baru'])) {
            $action = 'create';
        } elseif ($this->matches($msg, ['ubah', 'update', 'edit', 'ganti', 'change', 'modify', 'perbarui'])) {
            $action = 'update';
        }

        if (! $action) {
            return [];
        }

        // Detect entity
        $entity = null;
        if ($this->matches($msg, ['user', 'pengguna', 'receptionist', 'manager', 'akun', 'account'])) {
            $entity = 'user';
        } elseif ($this->matches($msg, ['ruang', 'room', 'ruangan', 'meeting room', 'aula', 'hall'])) {
            $entity = 'room';
        } elseif ($this->matches($msg, ['kendaraan', 'vehicle', 'mobil', 'car', 'bus', 'motor', 'truck'])) {
            $entity = 'vehicle';
        } elseif ($this->matches($msg, ['storage', 'gudang', 'penyimpanan', 'store'])) {
            $entity = 'storage';
        }

        if (! $entity) {
            return ['action' => $action]; // Action detected but entity unclear
        }

        return ['entity' => $entity, 'action' => $action];
    }

    /**
     * Whether the state is complete (all required fields collected and no errors).
     */
    public function isReadyForConfirmation(array $state): bool
    {
        return $state['active']
            && empty($state['missing'])
            && empty($state['errors'])
            && ! $state['confirmed']
            && ! $state['awaiting_confirm'];
    }

    /**
     * Whether the state is confirmed and ready for execution.
     */
    public function isReadyToExecute(array $state): bool
    {
        return $state['active']
            && empty($state['missing'])
            && empty($state['errors'])
            && $state['confirmed'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function requiredFieldsFor(string $entity, string $action): array
    {
        if ($action === 'update') {
            // For updates we need at minimum the target identifier
            return match ($entity) {
                'user'    => [],  // target_id + at least one field to change
                'room'    => [],
                'vehicle' => [],
                'storage' => [],
                default   => [],
            };
        }

        // Create requirements mirror the Livewire validation
        return match ($entity) {
            'user'    => ['full_name', 'email', 'password', 'role'],
            'room'    => ['room_name'],
            'vehicle' => ['name', 'category', 'plate_number', 'year'],
            'storage' => ['code', 'name'],
            default   => [],
        };
    }

    private function computeMissing(array $state): array
    {
        $required = $this->requiredFieldsFor($state['entity'] ?? '', $state['action'] ?? 'create');
        $missing  = [];
        foreach ($required as $field) {
            $value = $state['collected'][$field] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Return collected fields with password redacted for display.
     */
    private function safeCollectedFields(array $state): array
    {
        $safe = [];
        foreach ($state['collected'] as $k => $v) {
            if ($k === 'password') {
                // Never echo password value in summaries
                $safe[$k] = '***';
            } else {
                $safe[$k] = $v;
            }
        }
        return $safe;
    }

    private function matches(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }
}
