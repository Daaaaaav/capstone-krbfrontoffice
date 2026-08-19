<?php

namespace App\Services\AI\Tools;

use App\Models\Room;
use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * RoomManagementTool — controlled CRUD operations for Rooms.
 *
 * Exposes only explicitly defined operations to the AI. Uses the same
 * validation rules as the IT Officer Manageroom Livewire page.
 *
 * Supported actions: validate_create, create, find, validate_update, update
 */
class RoomManagementTool implements ToolInterface
{
    public function name(): string
    {
        return 'manage_room';
    }

    public function description(): string
    {
        return 'Create, find, or update a room for the IT Officer. '
             . 'Use action=validate_create to check missing/invalid fields before creating. '
             . 'Use action=create only after all required fields are present and confirmed by user. '
             . 'Use action=find to look up an existing room by name or ID. '
             . 'Use action=validate_update to check what will change before updating. '
             . 'Use action=update only after explicit user confirmation. '
             . 'NEVER use action=create or action=update without prior explicit user confirmation.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['validate_create', 'create', 'find', 'validate_update', 'update'],
                    'description' => 'Operation to perform.',
                ],
                'data' => [
                    'type'        => 'object',
                    'description' => 'Room fields: room_name (required for create), capacity (optional integer), room_id (for update/find).',
                    'properties'  => [
                        'room_id'   => ['type' => 'integer'],
                        'room_name' => ['type' => 'string'],
                        'capacity'  => ['type' => 'integer', 'description' => 'Optional. 0–65535.'],
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

        Log::info('RoomManagementTool: execute', [
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
            default           => ['text' => "[manage_room: unknown action '{$action}']"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_create
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCreate(array $data, ?int $companyId): array
    {
        $errors  = [];
        $missing = [];

        $roomName = trim($data['room_name'] ?? '');
        $capacity = $data['capacity'] ?? null;

        // room_name is required
        if ($roomName === '') {
            $missing[] = 'room_name';
        } else {
            // Max length 255, no special chars
            if (strlen($roomName) > 255) {
                $errors[] = 'Room name must not exceed 255 characters.';
            }
            // Uniqueness check
            if (Room::where('company_id', $companyId)
                ->where('room_name', $roomName)
                ->exists()) {
                $errors[] = "A room named '{$roomName}' already exists.";
            }
        }

        // capacity is nullable/optional
        if ($capacity !== null && $capacity !== '') {
            $cap = (int) $capacity;
            if ($cap < 0 || $cap > 65535) {
                $errors[] = 'Capacity must be an integer between 0 and 65535.';
            }
        }

        $collected = [];
        if ($roomName !== '') $collected['room_name'] = $roomName;
        if ($capacity !== null && $capacity !== '') $collected['capacity'] = (int) $capacity;

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
            return ['text' => 'Cannot create room: ' . $validation['text'], 'success' => false];
        }

        $roomName = trim($data['room_name']);
        $capacity = isset($data['capacity']) && $data['capacity'] !== '' && $data['capacity'] !== null
            ? (int) $data['capacity']
            : null;

        try {
            $room = Room::create([
                'company_id' => $companyId,
                'room_name'  => $roomName,
                'capacity'   => $capacity,
            ]);

            Log::info('RoomManagementTool: room created via chatbot', [
                'created_by' => Auth::id(),
                'room_id'    => $room->room_id,
                'room_name'  => $roomName,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:RoomCreate', [
                'room_id'   => $room->room_id,
                'room_name' => $roomName,
            ]);

            $capStr = $capacity !== null ? $capacity : 'not set';
            return [
                'text'    => "✅ Room created successfully.\n\nName: {$roomName}\nCapacity: {$capStr}\nRoom ID: {$room->room_id}",
                'success' => true,
                'room_id' => $room->room_id,
            ];
        } catch (\Throwable $e) {
            Log::error('RoomManagementTool: create failed', ['error' => $e->getMessage()]);
            return ['text' => 'Room could not be created due to a system error. Please try again.', 'success' => false];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // find
    // ─────────────────────────────────────────────────────────────────────────

    private function find(array $data, ?int $companyId): array
    {
        $roomId   = $data['room_id']   ?? null;
        $roomName = trim($data['room_name'] ?? '');

        $query = Room::where('company_id', $companyId);

        if ($roomId) {
            $query->where('room_id', $roomId);
        } elseif ($roomName !== '') {
            $query->where('room_name', 'like', "%{$roomName}%");
        } else {
            // Return first 10 rooms as a listing
            $rooms = $query->orderBy('room_name')->limit(10)->get();
            if ($rooms->isEmpty()) {
                return ['text' => 'No rooms found for your company.'];
            }
            $lines = ["Rooms (showing up to 10):"];
            foreach ($rooms as $r) {
                $cap = $r->capacity !== null ? $r->capacity : '-';
                $lines[] = "- ID:{$r->room_id} | {$r->room_name} | Capacity:{$cap}";
            }
            return ['text' => implode("\n", $lines)];
        }

        $rooms = $query->limit(5)->get();

        if ($rooms->isEmpty()) {
            return ['text' => 'No room found matching the search criteria.'];
        }

        $lines = ["Found {$rooms->count()} room(s):"];
        foreach ($rooms as $r) {
            $cap = $r->capacity !== null ? $r->capacity : 'not set';
            $lines[] = "- ID:{$r->room_id} | Name:{$r->room_name} | Capacity:{$cap}";
        }

        return [
            'text'  => implode("\n", $lines),
            'rooms' => $rooms->map(fn($r) => [
                'room_id'   => $r->room_id,
                'room_name' => $r->room_name,
                'capacity'  => $r->capacity,
            ])->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_update
    // ─────────────────────────────────────────────────────────────────────────

    private function validateUpdate(array $data, ?int $companyId): array
    {
        $roomId   = $data['room_id']   ?? null;
        $roomName = trim($data['room_name'] ?? '');

        // Must identify the room
        if (! $roomId && $roomName === '') {
            return ['text' => 'Please provide room_id or room_name to identify which room to update.', 'ready' => false];
        }

        // Find the room
        $query = Room::where('company_id', $companyId);
        if ($roomId) {
            $room = $query->find($roomId);
        } else {
            $room = $query->where('room_name', 'like', "%{$roomName}%")->first();
        }

        if (! $room) {
            return ['text' => "Room not found: '{$roomName}'.", 'ready' => false];
        }

        $errors  = [];
        $changes = [];

        $newName = trim($data['room_name'] ?? '');
        if ($newName !== '' && $newName !== $room->room_name) {
            if (strlen($newName) > 255) {
                $errors[] = 'Room name must not exceed 255 characters.';
            } elseif (Room::where('company_id', $companyId)->where('room_name', $newName)->where('room_id', '!=', $room->room_id)->exists()) {
                $errors[] = "A room named '{$newName}' already exists.";
            } else {
                $changes[] = "room_name: '{$room->room_name}' → '{$newName}'";
            }
        }

        $newCap = $data['capacity'] ?? null;
        if ($newCap !== null && $newCap !== '') {
            $cap = (int) $newCap;
            if ($cap < 0 || $cap > 65535) {
                $errors[] = 'Capacity must be between 0 and 65535.';
            } else {
                $oldCap = $room->capacity !== null ? $room->capacity : 'not set';
                $changes[] = "capacity: {$oldCap} → {$cap}";
            }
        }

        if (empty($changes) && empty($errors)) {
            return ['text' => 'No changes detected. Please specify what you want to update.', 'ready' => false, 'room_id' => $room->room_id];
        }

        $ready = empty($errors);
        $text  = "Room found: '{$room->room_name}' (ID:{$room->room_id}).";
        if (! empty($changes)) $text .= "\nProposed changes: " . implode(', ', $changes);
        if (! empty($errors))  $text .= "\nErrors: "           . implode('; ', $errors);
        if ($ready)            $text .= "\nReady to apply after confirmation.";

        return [
            'text'    => $text,
            'ready'   => $ready,
            'room_id' => $room->room_id,
            'changes' => $changes,
            'errors'  => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    private function update(array $data, ?int $companyId): array
    {
        $validation = $this->validateUpdate($data, $companyId);
        if (! ($validation['ready'] ?? false)) {
            return ['text' => 'Cannot update room: ' . $validation['text'], 'success' => false];
        }

        $roomId = $validation['room_id'];
        $room   = Room::where('company_id', $companyId)->find($roomId);

        if (! $room) {
            return ['text' => "Room ID {$roomId} not found.", 'success' => false];
        }

        $updated = [];

        $newName = trim($data['room_name'] ?? '');
        if ($newName !== '' && $newName !== $room->room_name) {
            $room->room_name = $newName;
            $updated[] = "room_name={$newName}";
        }

        $newCap = $data['capacity'] ?? null;
        if ($newCap !== null && $newCap !== '') {
            $room->capacity = (int) $newCap;
            $updated[] = "capacity={$newCap}";
        }

        try {
            $room->save();

            Log::info('RoomManagementTool: room updated via chatbot', [
                'updated_by' => Auth::id(),
                'room_id'    => $roomId,
                'fields'     => $updated,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:RoomUpdate', [
                'room_id' => $roomId,
                'fields'  => $updated,
            ]);

            return [
                'text'    => "✅ Room updated successfully.\n\nRoom ID: {$roomId}\nUpdated: " . implode(', ', $updated),
                'success' => true,
                'room_id' => $roomId,
            ];
        } catch (\Throwable $e) {
            Log::error('RoomManagementTool: update failed', ['error' => $e->getMessage(), 'room_id' => $roomId]);
            return ['text' => 'Room could not be updated due to a system error. Please try again.', 'success' => false];
        }
    }
}
