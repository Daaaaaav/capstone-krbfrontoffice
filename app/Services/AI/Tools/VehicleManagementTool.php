<?php

namespace App\Services\AI\Tools;

use App\Models\Vehicle;
use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * VehicleManagementTool — controlled CRUD operations for Vehicles.
 *
 * Uses the same validation rules as the IT Officer Vehicle Livewire page.
 * Required fields for create: name, category, plate_number, year.
 * Optional: notes, is_active (default true).
 *
 * Supported actions: validate_create, create, find, validate_update, update
 */
class VehicleManagementTool implements ToolInterface
{
    public function name(): string
    {
        return 'manage_vehicle';
    }

    public function description(): string
    {
        return 'Create, find, or update a vehicle for the IT Officer. '
             . 'Required fields for creation: name, category, plate_number, year. '
             . 'Use action=validate_create to check missing/invalid fields. '
             . 'Use action=create only after all required fields are confirmed by user. '
             . 'Use action=find to search existing vehicles. '
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
                    'description' => 'Vehicle fields.',
                    'properties'  => [
                        'vehicle_id'   => ['type' => 'integer'],
                        'name'         => ['type' => 'string', 'description' => 'Vehicle name, e.g. Toyota Innova'],
                        'category'     => ['type' => 'string', 'description' => 'Vehicle category/type, e.g. SUV, Sedan, Van'],
                        'plate_number' => ['type' => 'string', 'description' => 'License plate, e.g. B 1234 ABC'],
                        'year'         => ['type' => 'string', 'description' => 'Manufacture year, e.g. 2022'],
                        'notes'        => ['type' => 'string'],
                        'is_active'    => ['type' => 'boolean', 'description' => 'true = available, false = inactive'],
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
        $companyId = Auth::user()?->company_id;

        Log::info('VehicleManagementTool: execute', [
            'action'     => $action,
            'user_id'    => Auth::id(),
            'company_id' => $companyId,
            'data'       => $data,
        ]);

        return match ($action) {
            'validate_create' => $this->validateCreate($data, $companyId),
            'create'          => $this->create($data, $companyId),
            'find'            => $this->find($data, $companyId),
            'validate_update' => $this->validateUpdate($data, $companyId),
            'update'          => $this->update($data, $companyId),
            default           => ['text' => "[manage_vehicle: unknown action '{$action}']"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_create
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCreate(array $data, ?int $companyId): array
    {
        $errors  = [];
        $missing = [];
        $collected = [];

        $name   = trim($data['name']         ?? '');
        $cat    = trim($data['category']     ?? '');
        $plate  = trim($data['plate_number'] ?? '');
        $year   = trim($data['year']         ?? '');
        $notes  = trim($data['notes']        ?? '');

        // Required: name
        if ($name === '') {
            $missing[] = 'name';
        } else {
            if (strlen($name) > 150) {
                $errors[] = 'Vehicle name must not exceed 150 characters.';
            } elseif (Vehicle::where('company_id', $companyId)->where('name', $name)->whereNull('deleted_at')->exists()) {
                $errors[] = "A vehicle named '{$name}' already exists.";
            } else {
                $collected['name'] = $name;
            }
        }

        // Required: category
        if ($cat === '') {
            $missing[] = 'category';
        } else {
            if (strlen($cat) > 100) {
                $errors[] = 'Category must not exceed 100 characters.';
            } else {
                $collected['category'] = $cat;
            }
        }

        // Required: plate_number
        if ($plate === '') {
            $missing[] = 'plate_number';
        } else {
            if (strlen($plate) > 50) {
                $errors[] = 'Plate number must not exceed 50 characters.';
            } else {
                $collected['plate_number'] = $plate;
            }
        }

        // Required: year
        if ($year === '') {
            $missing[] = 'year';
        } else {
            if (strlen($year) > 10) {
                $errors[] = 'Year must not exceed 10 characters.';
            } else {
                $collected['year'] = $year;
            }
        }

        if ($notes !== '') $collected['notes'] = $notes;

        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : null;
        if ($isActive !== null) $collected['is_active'] = $isActive ? 'active' : 'inactive';

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
            return ['text' => 'Cannot create vehicle: ' . $validation['text'], 'success' => false];
        }

        $name      = trim($data['name']);
        $category  = trim($data['category']);
        $plate     = trim($data['plate_number']);
        $year      = trim($data['year']);
        $notes     = trim($data['notes'] ?? '');
        $isActive  = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        try {
            $vehicle = Vehicle::create([
                'company_id'   => $companyId,
                'name'         => $name,
                'category'     => $category,
                'plate_number' => $plate,
                'year'         => $year,
                'notes'        => $notes ?: null,
                'is_active'    => $isActive,
            ]);

            Log::info('VehicleManagementTool: vehicle created via chatbot', [
                'created_by' => Auth::id(),
                'vehicle_id' => $vehicle->vehicle_id,
                'name'       => $name,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:VehicleCreate', [
                'vehicle_id' => $vehicle->vehicle_id,
                'name'       => $name,
            ]);

            $statusStr = $isActive ? 'Active' : 'Inactive';
            return [
                'text'       => "✅ Vehicle created successfully.\n\nName: {$name}\nCategory: {$category}\nPlate: {$plate}\nYear: {$year}\nStatus: {$statusStr}\nVehicle ID: {$vehicle->vehicle_id}",
                'success'    => true,
                'vehicle_id' => $vehicle->vehicle_id,
            ];
        } catch (\Throwable $e) {
            Log::error('VehicleManagementTool: create failed', ['error' => $e->getMessage()]);
            return ['text' => 'Vehicle could not be created due to a system error. Please try again.', 'success' => false];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // find
    // ─────────────────────────────────────────────────────────────────────────

    private function find(array $data, ?int $companyId): array
    {
        $vehicleId = $data['vehicle_id']   ?? null;
        $name      = trim($data['name']         ?? '');
        $plate     = trim($data['plate_number'] ?? '');

        $query = Vehicle::where('company_id', $companyId)->whereNull('deleted_at');

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        } elseif ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        } elseif ($plate !== '') {
            $query->where('plate_number', 'like', "%{$plate}%");
        } else {
            // List all
            $vehicles = $query->orderBy('name')->limit(10)->get();
            if ($vehicles->isEmpty()) {
                return ['text' => 'No vehicles found for your company.'];
            }
            $lines = ['Vehicles (up to 10):'];
            foreach ($vehicles as $v) {
                $status = $v->is_active ? 'Active' : 'Inactive';
                $lines[] = "- ID:{$v->vehicle_id} | {$v->name} | {$v->plate_number} | {$v->category} | {$v->year} | {$status}";
            }
            return ['text' => implode("\n", $lines)];
        }

        $vehicles = $query->limit(5)->get();

        if ($vehicles->isEmpty()) {
            return ['text' => 'No vehicle found matching the search criteria.'];
        }

        $lines = ["Found {$vehicles->count()} vehicle(s):"];
        foreach ($vehicles as $v) {
            $status = $v->is_active ? 'Active' : 'Inactive';
            $lines[] = "- ID:{$v->vehicle_id} | Name:{$v->name} | Plate:{$v->plate_number} | Cat:{$v->category} | Year:{$v->year} | {$status}";
        }

        return [
            'text'     => implode("\n", $lines),
            'vehicles' => $vehicles->map(fn($v) => [
                'vehicle_id'   => $v->vehicle_id,
                'name'         => $v->name,
                'plate_number' => $v->plate_number,
                'category'     => $v->category,
                'year'         => $v->year,
                'is_active'    => $v->is_active,
            ])->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validate_update
    // ─────────────────────────────────────────────────────────────────────────

    private function validateUpdate(array $data, ?int $companyId): array
    {
        $vehicleId = $data['vehicle_id'] ?? null;
        $name      = trim($data['name']         ?? '');
        $plate     = trim($data['plate_number'] ?? '');

        $query = Vehicle::where('company_id', $companyId)->whereNull('deleted_at');

        if ($vehicleId) {
            $vehicle = $query->find($vehicleId);
        } elseif ($name !== '') {
            $vehicle = $query->where('name', 'like', "%{$name}%")->first();
        } elseif ($plate !== '') {
            $vehicle = $query->where('plate_number', 'like', "%{$plate}%")->first();
        } else {
            return ['text' => 'Please provide vehicle_id, name, or plate_number to identify the vehicle.', 'ready' => false];
        }

        if (! $vehicle) {
            return ['text' => 'Vehicle not found.', 'ready' => false];
        }

        $errors  = [];
        $changes = [];

        $newName = trim($data['name'] ?? '');
        if ($newName !== '' && $newName !== $vehicle->name) {
            if (strlen($newName) > 150) {
                $errors[] = 'Vehicle name must not exceed 150 characters.';
            } elseif (Vehicle::where('company_id', $companyId)->where('name', $newName)->where('vehicle_id', '!=', $vehicle->vehicle_id)->whereNull('deleted_at')->exists()) {
                $errors[] = "A vehicle named '{$newName}' already exists.";
            } else {
                $changes[] = "name: '{$vehicle->name}' → '{$newName}'";
            }
        }

        $newPlate = trim($data['plate_number'] ?? '');
        if ($newPlate !== '' && $newPlate !== $vehicle->plate_number) {
            if (strlen($newPlate) > 50) {
                $errors[] = 'Plate number must not exceed 50 characters.';
            } else {
                $changes[] = "plate_number: '{$vehicle->plate_number}' → '{$newPlate}'";
            }
        }

        $newCat = trim($data['category'] ?? '');
        if ($newCat !== '' && $newCat !== $vehicle->category) {
            $changes[] = "category: '{$vehicle->category}' → '{$newCat}'";
        }

        $newYear = trim($data['year'] ?? '');
        if ($newYear !== '' && $newYear !== $vehicle->year) {
            $changes[] = "year: '{$vehicle->year}' → '{$newYear}'";
        }

        if (isset($data['is_active']) && (bool) $data['is_active'] !== (bool) $vehicle->is_active) {
            $oldStr = $vehicle->is_active ? 'active' : 'inactive';
            $newStr = $data['is_active'] ? 'active' : 'inactive';
            $changes[] = "status: {$oldStr} → {$newStr}";
        }

        if (empty($changes) && empty($errors)) {
            return ['text' => 'No changes detected.', 'ready' => false, 'vehicle_id' => $vehicle->vehicle_id];
        }

        $ready = empty($errors);
        $text  = "Vehicle found: '{$vehicle->name}' ({$vehicle->plate_number}, ID:{$vehicle->vehicle_id}).";
        if (! empty($changes)) $text .= "\nProposed changes: " . implode(', ', $changes);
        if (! empty($errors))  $text .= "\nErrors: "           . implode('; ', $errors);
        if ($ready)            $text .= "\nReady to apply after confirmation.";

        return [
            'text'       => $text,
            'ready'      => $ready,
            'vehicle_id' => $vehicle->vehicle_id,
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
            return ['text' => 'Cannot update vehicle: ' . $validation['text'], 'success' => false];
        }

        $vehicleId = $validation['vehicle_id'];
        $vehicle   = Vehicle::where('company_id', $companyId)->whereNull('deleted_at')->find($vehicleId);

        if (! $vehicle) {
            return ['text' => "Vehicle ID {$vehicleId} not found.", 'success' => false];
        }

        $updated = [];

        $newName = trim($data['name'] ?? '');
        if ($newName !== '' && $newName !== $vehicle->name) {
            $vehicle->name = $newName;
            $updated[] = "name={$newName}";
        }

        $newPlate = trim($data['plate_number'] ?? '');
        if ($newPlate !== '') {
            $vehicle->plate_number = $newPlate;
            $updated[] = "plate_number={$newPlate}";
        }

        $newCat = trim($data['category'] ?? '');
        if ($newCat !== '') {
            $vehicle->category = $newCat;
            $updated[] = "category={$newCat}";
        }

        $newYear = trim($data['year'] ?? '');
        if ($newYear !== '') {
            $vehicle->year = $newYear;
            $updated[] = "year={$newYear}";
        }

        $newNotes = trim($data['notes'] ?? '');
        if ($newNotes !== '') {
            $vehicle->notes = $newNotes;
            $updated[] = 'notes=updated';
        }

        if (isset($data['is_active'])) {
            $vehicle->is_active = (bool) $data['is_active'];
            $updated[] = 'is_active=' . ($vehicle->is_active ? 'active' : 'inactive');
        }

        try {
            $vehicle->save();

            Log::info('VehicleManagementTool: vehicle updated via chatbot', [
                'updated_by' => Auth::id(),
                'vehicle_id' => $vehicleId,
                'fields'     => $updated,
                'company_id' => $companyId,
            ]);

            \App\Services\SecurityMonitoringService::logFormSubmit('ItOfficerChatbot:VehicleUpdate', [
                'vehicle_id' => $vehicleId,
                'fields'     => $updated,
            ]);

            return [
                'text'       => "✅ Vehicle updated successfully.\n\nVehicle ID: {$vehicleId}\nUpdated: " . implode(', ', $updated),
                'success'    => true,
                'vehicle_id' => $vehicleId,
            ];
        } catch (\Throwable $e) {
            Log::error('VehicleManagementTool: update failed', ['error' => $e->getMessage(), 'vehicle_id' => $vehicleId]);
            return ['text' => 'Vehicle could not be updated due to a system error. Please try again.', 'success' => false];
        }
    }
}
