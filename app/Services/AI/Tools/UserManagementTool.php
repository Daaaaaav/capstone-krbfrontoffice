<?php

namespace App\Services\AI\Tools;

use App\Models\Role;
use App\Models\User;
use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * UserManagementTool — controlled CRUD operations for Users.
 *
 * Exposes only explicitly defined operations to the AI. Does NOT expose
 * arbitrary model methods or raw SQL. All writes go through Laravel
 * validation using the same rules as the IT Officer Livewire pages.
 *
 * Supported actions: validate_create, create, find, validate_update, update, list_roles
 */
class UserManagementTool implements ToolInterface
{
    public function name(): string
    {
        return 'manage_user';
    }

    public function description(): string
    {
        return 'Create, find, or update a user (Manager or Receptionist) for the IT Officer. '
             . 'Use action=validate_create to check missing/invalid fields before creating. '
             . 'Use action=create only after all required fields are present and confirmed. '
             . 'Use action=find to look up an existing user. '
             . 'Use action=list_roles to get available roles. '
             . 'NEVER use action=create without prior explicit user confirmation.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['validate_create', 'create', 'find', 'validate_update', 'update', 'list_roles'],
                    'description' => 'Operation to perform.',
                ],
                'data' => [
                    'type'        => 'object',
                    'description' => 'User fields: full_name, email, password (for create), phone_number, role (Manager|Receptionist), status (active|inactive), user_id (for update/find).',
                    'properties'  => [
                        'user_id'      => ['type' => 'integer'],
                        'full_name'    => ['type' => 'string'],
                        'email'        => ['type' => 'string'],
                        'password'     => ['type' => 'string', 'description' => 'Plain text. Will be hashed server-side. Never echo back.'],
                        'phone_number' => ['type' => 'string'],
                        'role'         => ['type' => 'string', 'description' => 'Manager or Receptionist'],
                        'status'       => ['type' => 'string', 'enum' => ['active', 'inactive']],
                    ],
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments): array
    {
        $action    = $arguments['action']    ?? '';
        $data      = $arguments['data']      ?? [];
        $companyId = Auth::user()?->company_id;

        Log::info('UserManagementTool: execute', [
            'action'     => $action,
            'user_id'    => Auth::id(),
            'company_id' => $companyId,
            // Never log password
            'data_keys'  => array_keys($data),
        ]);

        return match ($action) {
            'list_roles'       => $this->listRoles(),
            'validate_create'  => $this->validateCreate($data, $companyId),
            'create'           => $this->create($data, $companyId),
            'find'             => $this->find($data, $companyId),
            'validate_update'  => $this->validateUpdate($data, $companyId),
            'update'           => $this->update($data, $companyId),
            default            => ['text' => "[manage_user: unknown action '{$action}']"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // list_roles
    // ─────────────────────────────────────────────────────────────────────────

    private function listRoles(): array
    {
        $roles = Role::whereIn('name', ['Manager', 'Receptionist'])
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        return ['text' => 'Available roles: ' . implode(', ', $roles)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_create — returns missing fields or validation errors
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCreate(array $data, ?int $companyId): array
    {
        $errors   = [];
        $missing  = [];
        $collected = [];

        // Required: full_name, email, password, role
        $fullName = trim($data['full_name'] ?? '');
        $email    = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $role     = trim($data['role'] ?? '');
        $status   = trim($data['status'] ?? 'active');
        $phone    = trim($data['phone_number'] ?? '');

        if ($fullName === '') {
            $missing[] = 'full_name';
        } else {
            $collected['full_name'] = $fullName;
        }

        if ($email === '') {
            $missing[] = 'email';
        } else {
            $collected['email'] = $email;
            // Validate email format
            $emailValidator = Validator::make(
                ['email' => $email],
                ['email' => 'required|email|max:255']
            );
            if ($emailValidator->fails()) {
                $errors[] = 'Email format is invalid.';
            } elseif (User::where('email', $email)->whereNull('deleted_at')->exists()) {
                $errors[] = "Email '{$email}' is already registered.";
            }
        }

        if ((string) $password === '') {
            $missing[] = 'password';
        } else {
            // Validate password length (never store or echo the value back)
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
        }

        if ($role === '') {
            $missing[] = 'role';
        } else {
            $collected['role'] = $role;
            if (! in_array(strtolower($role), ['manager', 'receptionist'], true)) {
                $errors[] = "Role must be 'Manager' or 'Receptionist'. Got: '{$role}'.";
            }
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            $errors[] = "Status must be 'active' or 'inactive'. Got: '{$status}'.";
        } else {
            $collected['status'] = $status;
        }

        if ($phone !== '') {
            $collected['phone_number'] = $phone;
        }

        $result = [
            'action'    => 'validate_create',
            'missing'   => $missing,
            'errors'    => $errors,
            'collected' => $collected, // NEVER include password here
            'ready'     => empty($missing) && empty($errors),
        ];

        $lines = [];
        if (! empty($collected)) {
            $lines[] = 'Collected so far: ' . implode(', ', array_map(
                fn($k, $v) => "{$k}={$v}",
                array_keys($collected),
                $collected
            ));
        }
        if (! empty($missing)) {
            $lines[] = 'Missing required fields: ' . implode(', ', $missing);
        }
        if (! empty($errors)) {
            $lines[] = 'Validation errors: ' . implode('; ', $errors);
        }
        if ($result['ready']) {
            $lines[] = 'All required fields present. Ready for confirmation and creation.';
        }

        $result['text'] = implode("\n", $lines);
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create — performs the actual DB write after validation + confirmation
    // ─────────────────────────────────────────────────────────────────────────

    private function create(array $data, ?int $companyId): array
    {
        // Full validation before write
        $validation = $this->validateCreate($data, $companyId);
        if (! $validation['ready']) {
            return [
                'text'    => 'Cannot create user: ' . $validation['text'],
                'success' => false,
            ];
        }

        $fullName = trim($data['full_name']);
        $email    = trim(strtolower($data['email']));
        $password = $data['password'];
        $roleName = ucfirst(strtolower(trim($data['role'])));
        $status   = $data['status'] ?? 'active';
        $phone    = trim($data['phone_number'] ?? '') ?: '-';

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            Log::error('UserManagementTool: role not found in DB', ['role' => $roleName]);
            return ['text' => "System error: role '{$roleName}' not found. Contact administrator.", 'success' => false];
        }

        try {
            $user = User::create([
                'company_id'   => $companyId,
                'full_name'    => $fullName,
                'email'        => $email,
                'password'     => Hash::make($password),
                'phone_number' => $phone,
                'role_id'      => $role->role_id,
                'status'       => $status,
            ]);

            Log::info('UserManagementTool: user created via chatbot', [
                'created_by' => Auth::id(),
                'user_id'    => $user->user_id,
                'role'       => $roleName,
                'company_id' => $companyId,
                // Never log password
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:UserCreate', [
                'user_id' => $user->user_id,
                'role'    => $roleName,
            ]);

            return [
                'text'    => "✅ User created successfully.\n\nName: {$fullName}\nEmail: {$email}\nRole: {$roleName}\nStatus: {$status}\nUser ID: {$user->user_id}",
                'success' => true,
                'user_id' => $user->user_id,
            ];
        } catch (\Throwable $e) {
            Log::error('UserManagementTool: create failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['text' => 'User could not be created due to a system error. Please try again.', 'success' => false];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // find — look up user by email or name
    // ─────────────────────────────────────────────────────────────────────────

    private function find(array $data, ?int $companyId): array
    {
        $userId = $data['user_id'] ?? null;
        $email  = trim(strtolower($data['email'] ?? ''));
        $name   = trim($data['full_name'] ?? '');

        $query = User::where('company_id', $companyId)->whereNull('deleted_at');

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($email !== '') {
            $query->where('email', $email);
        } elseif ($name !== '') {
            $query->where('full_name', 'like', "%{$name}%");
        } else {
            return ['text' => 'Please provide user_id, email, or full_name to search.'];
        }

        $users = $query->with('role')->limit(5)->get();

        if ($users->isEmpty()) {
            return ['text' => 'No user found matching the search criteria.'];
        }

        $lines = ["Found {$users->count()} user(s):"];
        foreach ($users as $u) {
            $lines[] = "- ID:{$u->user_id} | {$u->full_name} | {$u->email} | Role:{$u->role?->name} | Status:{$u->status}";
        }

        return ['text' => implode("\n", $lines), 'users' => $users->map(fn($u) => [
            'user_id'   => $u->user_id,
            'full_name' => $u->full_name,
            'email'     => $u->email,
            'role'      => $u->role?->name,
            'status'    => $u->status,
        ])->toArray()];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_update
    // ─────────────────────────────────────────────────────────────────────────

    private function validateUpdate(array $data, ?int $companyId): array
    {
        $userId = $data['user_id'] ?? null;
        if (! $userId) {
            return ['text' => 'Missing user_id for update. Please find the user first.', 'ready' => false];
        }

        $user = User::where('company_id', $companyId)->where('user_id', $userId)->whereNull('deleted_at')->first();
        if (! $user) {
            return ['text' => "User ID {$userId} not found in your company.", 'ready' => false];
        }

        $errors = [];

        $email = trim(strtolower($data['email'] ?? ''));
        if ($email !== '' && $email !== $user->email) {
            $emailValidator = Validator::make(['email' => $email], ['email' => 'required|email|max:255']);
            if ($emailValidator->fails()) {
                $errors[] = 'Email format is invalid.';
            } elseif (User::where('email', $email)->where('user_id', '!=', $userId)->whereNull('deleted_at')->exists()) {
                $errors[] = "Email '{$email}' is already registered to another user.";
            }
        }

        $status = $data['status'] ?? null;
        if ($status !== null && ! in_array($status, ['active', 'inactive'], true)) {
            $errors[] = "Status must be 'active' or 'inactive'.";
        }

        $password = $data['password'] ?? '';
        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        // Prevent self-modification of critical fields
        if ((int) $userId === (int) Auth::id()) {
            $errors[] = 'You cannot modify your own account through the chatbot.';
        }

        $currentValues = [
            'full_name'    => $user->full_name,
            'email'        => $user->email,
            'role'         => $user->role?->name,
            'status'       => $user->status,
            'phone_number' => $user->phone_number,
        ];

        $ready = empty($errors);
        $text  = "Current user: {$user->full_name} ({$user->email}), Role: {$user->role?->name}, Status: {$user->status}.";
        if (! empty($errors)) {
            $text .= "\nValidation errors: " . implode('; ', $errors);
        } else {
            $text .= "\nReady to update after confirmation.";
        }

        return ['text' => $text, 'ready' => $ready, 'current' => $currentValues, 'errors' => $errors];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    private function update(array $data, ?int $companyId): array
    {
        $validation = $this->validateUpdate($data, $companyId);
        if (! ($validation['ready'] ?? false)) {
            return ['text' => 'Cannot update: ' . $validation['text'], 'success' => false];
        }

        $userId = (int) $data['user_id'];
        $user   = User::where('company_id', $companyId)->where('user_id', $userId)->whereNull('deleted_at')->first();

        if (! $user) {
            return ['text' => "User ID {$userId} not found.", 'success' => false];
        }

        $updated = [];

        $fullName = trim($data['full_name'] ?? '');
        if ($fullName !== '') {
            $user->full_name = $fullName;
            $updated[] = "full_name={$fullName}";
        }

        $email = trim(strtolower($data['email'] ?? ''));
        if ($email !== '') {
            $user->email = $email;
            $updated[] = "email={$email}";
        }

        $phone = trim($data['phone_number'] ?? '');
        if ($phone !== '') {
            $user->phone_number = $phone;
            $updated[] = "phone={$phone}";
        }

        $status = $data['status'] ?? '';
        if ($status !== '') {
            $user->status = $status;
            $updated[] = "status={$status}";
        }

        $password = $data['password'] ?? '';
        if ($password !== '') {
            $user->password = Hash::make($password);
            $updated[] = 'password=***updated***';
        }

        try {
            $user->save();

            Log::info('UserManagementTool: user updated via chatbot', [
                'updated_by' => Auth::id(),
                'user_id'    => $userId,
                'fields'     => array_filter($updated, fn($f) => ! str_contains($f, 'password')),
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:UserUpdate', [
                'user_id' => $userId,
                'fields'  => array_filter($updated, fn($f) => ! str_contains($f, 'password')),
            ]);

            return [
                'text'    => "✅ User updated successfully.\n\nUser ID: {$userId}\nUpdated: " . implode(', ', array_filter($updated, fn($f) => ! str_contains($f, 'password'))),
                'success' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('UserManagementTool: update failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return ['text' => 'User could not be updated due to a system error. Please try again.', 'success' => false];
        }
    }
}
