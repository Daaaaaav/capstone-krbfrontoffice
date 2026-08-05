<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Vehicle as VehicleModel;

#[Layout('layouts.manager')]
#[Title('Manage Vehicles')]
class Vehicle extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $company_id = 0;
    public string $search = '';
    public string $name         = '';
    public string $category     = '';
    public string $plate_number = '';
    public string $year         = '';
    public bool   $is_active    = true;
    public string $notes        = '';
    public bool    $showModal        = false;
    public bool    $editMode         = false;
    public ?int    $edit_id          = null;
    public string  $edit_name        = '';
    public string  $edit_category    = '';
    public string  $edit_plate_number = '';
    public string  $edit_year        = '';
    public bool    $edit_is_active   = true;
    public string  $edit_notes       = '';

    public function mount(): void
    {
        $this->company_id = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'vehiclesPage');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editMode  = false;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        try {
            $row = VehicleModel::withTrashed()
                ->where('company_id', $this->company_id)
                ->findOrFail($id);

            $this->edit_id           = $row->vehicle_id;
            $this->edit_name         = (string) $row->name;
            $this->edit_category     = (string) $row->category;
            $this->edit_plate_number = (string) $row->plate_number;
            $this->edit_year         = (string) $row->year;
            $this->edit_is_active    = (bool) $row->is_active;
            $this->edit_notes        = (string) ($row->notes ?? '');

            $this->editMode  = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load vehicle: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->name              = '';
        $this->category          = '';
        $this->plate_number      = '';
        $this->year              = '';
        $this->is_active         = true;
        $this->notes             = '';
        $this->edit_id           = null;
        $this->edit_name         = '';
        $this->edit_category     = '';
        $this->edit_plate_number = '';
        $this->edit_year         = '';
        $this->edit_is_active    = true;
        $this->edit_notes        = '';
    }

    protected function createRules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('vehicles', 'name')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'category'     => 'required|string|max:100',
            'plate_number' => 'required|string|max:50',
            'year'         => 'required|string|max:10',
            'is_active'    => 'boolean',
            'notes'        => 'nullable|string|max:255',
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_name' => [
                'required', 'string', 'max:150',
                Rule::unique('vehicles', 'name')
                    ->ignore($this->edit_id, 'vehicle_id')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'edit_category'     => 'required|string|max:100',
            'edit_plate_number' => 'required|string|max:50',
            'edit_year'         => 'required|string|max:10',
            'edit_is_active'    => 'boolean',
            'edit_notes'        => 'nullable|string|max:255',
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                VehicleModel::where('company_id', $this->company_id)
                    ->where('vehicle_id', $this->edit_id)
                    ->update([
                        'name'         => trim($this->edit_name),
                        'category'     => trim($this->edit_category),
                        'plate_number' => trim($this->edit_plate_number),
                        'year'         => trim($this->edit_year),
                        'is_active'    => (bool) $this->edit_is_active,
                        'notes'        => trim($this->edit_notes),
                    ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Vehicle updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                VehicleModel::create([
                    'company_id'   => $this->company_id,
                    'name'         => trim($this->name),
                    'category'     => trim($this->category),
                    'plate_number' => trim($this->plate_number),
                    'year'         => trim($this->year),
                    'is_active'    => (bool) $this->is_active,
                    'notes'        => trim($this->notes),
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Vehicle created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'vehiclesPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save vehicle: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $row = VehicleModel::where('company_id', $this->company_id)->findOrFail($id);
            $row->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'Vehicle deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'vehiclesPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete vehicle: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $rows = VehicleModel::query()
            ->where('company_id', $this->company_id)
            ->whereNull('deleted_at')
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->where(fn($q2) =>
                    $q2->where('name', 'like', "%{$s}%")
                       ->orWhere('plate_number', 'like', "%{$s}%")
                       ->orWhere('category', 'like', "%{$s}%")
                );
            })
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'vehiclesPage');

        return view('livewire.pages.manager.vehicle', compact('rows'));
    }
}
