<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Room;

#[Layout('layouts.it-officer')]
#[Title('Manage Rooms')]
class Manageroom extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $companyId = 0;
    public string $search = '';
    public string $room_name = '';
    public $capacity = null;

    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $edit_id = null;
    public string $edit_room_name = '';
    public $edit_capacity = null;

    public function mount(): void
    {
        $this->companyId = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'roomsPage');
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
            $room = Room::where('company_id', $this->companyId)->findOrFail($id);

            $this->edit_id        = $room->room_id;
            $this->edit_room_name = (string) $room->room_name;
            $this->edit_capacity  = $room->capacity;

            $this->editMode  = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load room: ' . $e->getMessage(), duration: 4000);
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
        $this->room_name      = '';
        $this->capacity       = null;
        $this->edit_id        = null;
        $this->edit_room_name = '';
        $this->edit_capacity  = null;
    }

    protected function createRules(): array
    {
        return [
            'room_name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms', 'room_name')
                    ->where(fn($q) => $q->where('company_id', $this->companyId)),
            ],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_room_name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms', 'room_name')
                    ->where(fn($q) => $q->where('company_id', $this->companyId))
                    ->ignore($this->edit_id, 'room_id'),
            ],
            'edit_capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                $room = Room::where('company_id', $this->companyId)->findOrFail($this->edit_id);
                $room->update([
                    'room_name' => trim($this->edit_room_name),
                    'capacity'  => $this->edit_capacity === '' ? null : (int) $this->edit_capacity,
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Room updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                Room::create([
                    'company_id' => $this->companyId,
                    'room_name'  => trim($this->room_name),
                    'capacity'   => $this->capacity === '' ? null : (int) $this->capacity,
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Room created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'roomsPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save room: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function toggleEarlyApproval(int $id): void
    {
        try {
            $room = Room::where('company_id', $this->companyId)->findOrFail($id);
            $newValue = ! $room->requires_early_approval;
            $room->update(['requires_early_approval' => $newValue]);

            $state = $newValue ? 'enabled' : 'disabled';
            $this->dispatch(
                'toast',
                type: 'success',
                title: 'Setting Updated',
                message: "Approval time validation {$state} for \"{$room->room_name}\".",
                duration: 3000
            );
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update setting: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $room = Room::where('company_id', $this->companyId)->findOrFail($id);
            $room->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'Room deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'roomsPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete room: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $rooms = Room::query()
            ->where('company_id', $this->companyId)
            ->when(trim($this->search) !== '', fn($q) =>
                $q->where('room_name', 'like', '%' . trim($this->search) . '%')
            )
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'roomsPage');

        return view('livewire.pages.it-officer.manageroom', compact('rooms'));
    }
}
