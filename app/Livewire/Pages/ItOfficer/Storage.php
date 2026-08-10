<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Storage as StorageModel;
use App\Livewire\Traits\HasManagerValidation;

#[Layout('layouts.it-officer')]
#[Title('Manage Storages')]
class Storage extends Component
{
    use WithPagination, HasManagerValidation;

    protected string $paginationTheme = 'tailwind';

    public int $company_id = 0;
    public string $search = '';
    public string $code      = '';
    public string $name      = '';
    public bool   $is_active = true;
    public bool   $showModal      = false;
    public bool   $editMode       = false;
    public ?int   $edit_id        = null;
    public string $edit_code      = '';
    public string $edit_name      = '';
    public bool   $edit_is_active = true;

    public function mount(): void
    {
        $this->company_id = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'storagesPage');
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
            $row = StorageModel::withTrashed()
                ->where('company_id', $this->company_id)
                ->whereKey($id)
                ->firstOrFail();

            $this->edit_id        = $row->storage_id;
            $this->edit_code      = (string) $row->code;
            $this->edit_name      = (string) $row->name;
            $this->edit_is_active = (bool) $row->is_active;

            $this->editMode  = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load storage: ' . $e->getMessage(), duration: 4000);
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
        $this->code           = '';
        $this->name           = '';
        $this->is_active      = true;
        $this->edit_id        = null;
        $this->edit_code      = '';
        $this->edit_name      = '';
        $this->edit_is_active = true;
    }

    protected function createRules(): array
    {
        return [
            'code' => [
                ...$this->managerTextRules('Storage Code', required: true, maxLength: 100),
                Rule::unique('storages', 'code')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                ...$this->managerTextRules('Storage Name', required: true, maxLength: 150),
                Rule::unique('storages', 'name')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_code' => [
                ...$this->managerTextRules('Storage Code', required: true, maxLength: 100),
                Rule::unique('storages', 'code')
                    ->ignore($this->edit_id, 'storage_id')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'edit_name' => [
                ...$this->managerTextRules('Storage Name', required: true, maxLength: 150),
                Rule::unique('storages', 'name')
                    ->ignore($this->edit_id, 'storage_id')
                    ->where(fn($q) => $q->where('company_id', $this->company_id))
                    ->whereNull('deleted_at'),
            ],
            'edit_is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                StorageModel::where('company_id', $this->company_id)
                    ->where('storage_id', $this->edit_id)
                    ->update([
                        'code'      => trim($this->edit_code),
                        'name'      => trim($this->edit_name),
                        'is_active' => (bool) $this->edit_is_active,
                    ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Storage updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                StorageModel::create([
                    'company_id' => $this->company_id,
                    'code'       => trim($this->code),
                    'name'       => trim($this->name),
                    'is_active'  => (bool) $this->is_active,
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Storage created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'storagesPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save storage: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $row = StorageModel::where('company_id', $this->company_id)->findOrFail($id);
            $row->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'Storage deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'storagesPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete storage: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $rows = StorageModel::query()
            ->where('company_id', $this->company_id)
            ->whereNull('deleted_at')
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->where(fn($q2) =>
                    $q2->where('code', 'like', "%{$s}%")
                       ->orWhere('name', 'like', "%{$s}%")
                );
            })
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'storagesPage');

        return view('livewire.pages.it-officer.storage', compact('rows'));
    }
}
