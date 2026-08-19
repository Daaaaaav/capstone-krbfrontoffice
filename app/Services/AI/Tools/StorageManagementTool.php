<?php

namespace App\Services\AI\Tools;

use App\Models\Storage;
use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * StorageManagementTool — controlled CRUD operations for Storage areas.
 *
 * Uses the same validation rules as the IT Officer Storage Livewire page.
 * Required fields for create: code, name.
 * Optional: is_active (default true).
 *
 * Supported actions: validate_create, create, find, validate_update, update
 */
class StorageManagementTool implements ToolInterface
{
    public function name(): string
    {
        return 'manage_storage';
    }

    public function description(): string
    {
        return 'Create, find, or update a storage area for the IT Officer. '
             . 'Required fields for creation: code (unique identifier), name. '
             . 'Use action=validate_create to check missing/invalid fields. '
             . 'Use action=create only after all required fields are confirmed by user. '
             . 'Use action=find to search existing storage areas. '
             . 'NEVER use action=create or action=update without explicit user confirmation.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['validate_create', 'create', 'find', 'validate_update', 'update'],
                ],
                'data' => [
                    'type'        => 'object',
                    'description' => 'Storage fields.',
                    'properties'  => [
                        'storage_id' => ['type' => 'integer'],
                        'code'       => ['type' => 'string', 'description' => 'Unique storage code, max 100 chars'],
                        'name'       => ['type' => 'string', 'description' => 'Storage name, max 150 chars'],
                        'is_active'  => ['type' => 'boolean'],
                    ],
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments): array
    {
        $action    = $arguments['action'] ?? '';
        $data      = $arguments['data']   ?? [];
        $user      = Auth::user();
        $companyId = $user?->company_id;

        if (! $companyId) {
            return ['text' => 'Company context is missing or unauthorized.', 'success' => false];
        }

        Log::info('StorageManagementTool: execute', [
            'action'     => $action,
            'user_id'    => $user?->user_id,
            'company_id' => $companyId,
            'data'       => $data,
        ]);

        return match ($action) {
            'validate_create' => $this->validateCreate($data, $companyId),
            'create'          => $this->create($data, $companyId),
            'find'            => $this->find($data, $companyId),
            'validate_update' => $this->validateUpdate($data, $companyId),
            'update'          => $this->update($data, $companyId),
            default           => ['text' => "[manage_storage: unknown action '{$action}']"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_create
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCreate(array $data, ?int $companyId): array
    {
        $errors    = [];
        $missing   = [];
        $collected = [];

        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');

        // Required: code
        if ($code === '') {
            $missing[] = 'code';
        } else {
            if (strlen($code) > 100) {
                $errors[] = 'Storage code must not exceed 100 characters.';
            } elseif (Storage::where('company_id', $companyId)->where('code', $code)->whereNull('deleted_at')->exists()) {
                $errors[] = "Storage code '{$code}' is already in use.";
            } else {
                $collected['code'] = $code;
            }
        }

        // Required: name
        if ($name === '') {
            $missing[] = 'name';
        } else {
            if (strlen($name) > 150) {
                $errors[] = 'Storage name must not exceed 150 characters.';
            } elseif (Storage::where('company_id', $companyId)->where('name', $name)->whereNull('deleted_at')->exists()) {
                $errors[] = "Storage named '{$name}' already exists.";
            } else {
                $collected['name'] = $name;
            }
        }

        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : null;
        if ($isActive !== null) {
            $collected['is_active'] = $isActive ? 'active' : 'inactive';
        }

        $ready = empty($missing) && empty($errors);

        $lines = [];
        if (! empty($collected)) {
            $lines[] = 'Collected: ' . implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($collected), $collected));
        }
        if (! empty($missing)) {
            $lines[] = 'Missing required fields: ' . implode(', ', $missing);
        }
        if (! empty($errors)) {
            $lines[] = 'Validation errors: ' . implode('; ', $errors);
        }
        if ($ready) {
            $lines[] = 'All required fields present and valid. Ready for confirmation and creation.';
        }

        return [
            'text'      => implode("\n", $lines),
            'missing'   => $missing,
            'errors'    => $errors,
            'collected' => $collected,
            'ready'     => $ready,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────────────────

    private function create(array $data, ?int $companyId): array
    {
        $validation = $this->validateCreate($data, $companyId);
        if (! $validation['ready']) {
            return ['text' => 'Cannot create storage: ' . $validation['text'], 'success' => false];
        }

        $code     = trim($data['code']);
        $name     = trim($data['name']);
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        try {
            $storage = Storage::create([
                'company_id' => $companyId,
                'code'       => $code,
                'name'       => $name,
                'is_active'  => $isActive,
            ]);

            Log::info('StorageManagementTool: storage created via chatbot', [
                'created_by' => Auth::id(),
                'storage_id' => $storage->storage_id,
                'code'       => $code,
                'name'       => $name,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:StorageCreate', [
                'storage_id' => $storage->storage_id,
                'code'       => $code,
                'name'       => $name,
            ]);

            $statusStr = $isActive ? 'Active' : 'Inactive';
            return [
                'text'       => "✅ Storage created successfully.\n\nCode: {$code}\nName: {$name}\nStatus: {$statusStr}\nStorage ID: {$storage->storage_id}",
                'success'    => true,
                'storage_id' => $storage->storage_id,
            ];
        } catch (\Throwable $e) {
            Log::error('StorageManagementTool: create failed', ['error' => $e->getMessage()]);
            return ['text' => 'Storage could not be created due to a system error. Please try again.', 'success' => false];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // find
    // ─────────────────────────────────────────────────────────────────────────

    private function find(array $data, ?int $companyId): array
    {
        $storageId = $data['storage_id'] ?? null;
        $code      = trim($data['code'] ?? '');
        $name      = trim($data['name'] ?? '');

        $query = Storage::where('company_id', $companyId)->whereNull('deleted_at');

        if ($storageId) {
            $query->where('storage_id', $storageId);
        } elseif ($code !== '') {
            $query->where('code', 'like', "%{$code}%");
        } elseif ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        } else {
            $storages = $query->orderBy('name')->limit(10)->get();
            if ($storages->isEmpty()) {
                return ['text' => 'No storage areas found for your company.'];
            }
            $lines = ['Storage areas (up to 10):'];
            foreach ($storages as $s) {
                $status = $s->is_active ? 'Active' : 'Inactive';
                $lines[] = "- ID:{$s->storage_id} | Code:{$s->code} | Name:{$s->name} | {$status}";
            }
            return ['text' => implode("\n", $lines)];
        }

        $storages = $query->limit(5)->get();
        if ($storages->isEmpty()) {
            return ['text' => 'No storage found matching the search criteria.'];
        }

        $lines = ["Found {$storages->count()} storage(s):"];
        foreach ($storages as $s) {
            $status = $s->is_active ? 'Active' : 'Inactive';
            $lines[] = "- ID:{$s->storage_id} | Code:{$s->code} | Name:{$s->name} | {$status}";
        }

        return [
            'text'     => implode("\n", $lines),
            'storages' => $storages->map(fn($s) => [
                'storage_id' => $s->storage_id,
                'code'       => $s->code,
                'name'       => $s->name,
                'is_active'  => $s->is_active,
            ])->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_update
    // ─────────────────────────────────────────────────────────────────────────

    private function validateUpdate(array $data, ?int $companyId): array
    {
        $storageId = $data['storage_id'] ?? null;
        $code      = trim($data['code'] ?? '');
        $name      = trim($data['name'] ?? '');

        $query = Storage::where('company_id', $companyId)->whereNull('deleted_at');

        if ($storageId) {
            $storage = $query->find($storageId);
        } elseif ($code !== '') {
            $storage = $query->where('code', $code)->first();
        } elseif ($name !== '') {
            $storage = $query->where('name', 'like', "%{$name}%")->first();
        } else {
            return ['text' => 'Please provide storage_id, code, or name to identify the storage.', 'ready' => false];
        }

        if (! $storage) {
            return ['text' => 'Storage not found.', 'ready' => false];
        }

        $errors  = [];
        $changes = [];

        $newCode = trim($data['code'] ?? '');
        if ($newCode !== '' && $newCode !== $storage->code) {
            if (strlen($newCode) > 100) {
                $errors[] = 'Storage code must not exceed 100 characters.';
            } elseif (Storage::where('company_id', $companyId)->where('code', $newCode)->where('storage_id', '!=', $storage->storage_id)->whereNull('deleted_at')->exists()) {
                $errors[] = "Code '{$newCode}' already in use.";
            } else {
                $changes[] = "code: '{$storage->code}' → '{$newCode}'";
            }
        }

        $newName = trim($data['name'] ?? '');
        if ($newName !== '' && $newName !== $storage->name) {
            if (strlen($newName) > 150) {
                $errors[] = 'Storage name must not exceed 150 characters.';
            } elseif (Storage::where('company_id', $companyId)->where('name', $newName)->where('storage_id', '!=', $storage->storage_id)->whereNull('deleted_at')->exists()) {
                $errors[] = "Storage named '{$newName}' already exists.";
            } else {
                $changes[] = "name: '{$storage->name}' → '{$newName}'";
            }
        }

        if (isset($data['is_active']) && (bool) $data['is_active'] !== (bool) $storage->is_active) {
            $oldStr = $storage->is_active ? 'active' : 'inactive';
            $newStr = $data['is_active'] ? 'active' : 'inactive';
            $changes[] = "status: {$oldStr} → {$newStr}";
        }

        if (empty($changes) && empty($errors)) {
            return ['text' => 'No changes detected.', 'ready' => false, 'storage_id' => $storage->storage_id];
        }

        $ready = empty($errors);
        $text  = "Storage found: '{$storage->name}' (Code:{$storage->code}, ID:{$storage->storage_id}).";
        if (! empty($changes)) $text .= "\nProposed changes: " . implode(', ', $changes);
        if (! empty($errors))  $text .= "\nErrors: "           . implode('; ', $errors);
        if ($ready)            $text .= "\nReady to apply after confirmation.";

        return [
            'text'       => $text,
            'ready'      => $ready,
            'storage_id' => $storage->storage_id,
            'changes'    => $changes,
            'errors'     => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    private function update(array $data, ?int $companyId): array
    {
        $validation = $this->validateUpdate($data, $companyId);
        if (! ($validation['ready'] ?? false)) {
            return ['text' => 'Cannot update storage: ' . $validation['text'], 'success' => false];
        }

        $storageId = $validation['storage_id'];
        $storage   = Storage::where('company_id', $companyId)->whereNull('deleted_at')->find($storageId);

        if (! $storage) {
            return ['text' => "Storage ID {$storageId} not found.", 'success' => false];
        }

        $updated = [];

        $newCode = trim($data['code'] ?? '');
        if ($newCode !== '' && $newCode !== $storage->code) {
            $storage->code = $newCode;
            $updated[] = "code={$newCode}";
        }

        $newName = trim($data['name'] ?? '');
        if ($newName !== '' && $newName !== $storage->name) {
            $storage->name = $newName;
            $updated[] = "name={$newName}";
        }

        if (isset($data['is_active'])) {
            $storage->is_active = (bool) $data['is_active'];
            $updated[] = 'is_active=' . ($storage->is_active ? 'active' : 'inactive');
        }

        try {
            $storage->save();

            Log::info('StorageManagementTool: storage updated via chatbot', [
                'updated_by' => Auth::id(),
                'storage_id' => $storageId,
                'fields'     => $updated,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:StorageUpdate', [
                'storage_id' => $storageId,
                'fields'     => $updated,
            ]);

            return [
                'text'       => "✅ Storage updated successfully.\n\nStorage ID: {$storageId}\nUpdated: " . implode(', ', $updated),
                'success'    => true,
                'storage_id' => $storageId,
            ];
        } catch (\Throwable $e) {
            Log::error('StorageManagementTool: update failed', ['error' => $e->getMessage(), 'storage_id' => $storageId]);
            return ['text' => 'Storage could not be updated due to a system error. Please try again.', 'success' => false];
        }
    }
}
