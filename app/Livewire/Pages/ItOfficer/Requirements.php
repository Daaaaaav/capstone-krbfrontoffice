<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Requirement;

#[Layout('layouts.it-officer')]
#[Title('Manage Room Booking Requirements')]
class Requirements extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $companyId = 0;
    public string $search = '';
    public string $name = '';
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $edit_id = null;
    public string $edit_name = '';

    public function mount(): void
    {
        $this->companyId = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'requirementsPage');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        try {
            $requirement = Requirement::where('company_id', $this->companyId)
                ->findOrFail($id);

            $this->edit_id = $requirement->requirement_id;
            $this->edit_name = (string) $requirement->name;

            $this->editMode = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load requirement: ' . $e->getMessage(), duration: 4000);
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
        $this->name = '';
        $this->edit_id = null;
        $this->edit_name = '';
    }

    protected function createRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('requirements', 'name')
                    ->where(fn($q) => $q->where('company_id', $this->companyId))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('requirements', 'name')
                    ->ignore($this->edit_id, 'requirement_id')
                    ->where(fn($q) => $q->where('company_id', $this->companyId))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                Requirement::where('company_id', $this->companyId)
                    ->where('requirement_id', $this->edit_id)
                    ->update([
                        'name' => trim($this->edit_name),
                    ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Requirement updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                Requirement::create([
                    'company_id' => $this->companyId,
                    'name' => trim($this->name),
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Requirement created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'requirementsPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save requirement: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $requirement = Requirement::where('company_id', $this->companyId)
                ->findOrFail($id);

            // Check if this requirement is referenced by any booking rooms
            $bookingCount = $requirement->bookingRooms()->count();
            if ($bookingCount > 0) {
                $this->dispatch('toast', 
                    type: 'error', 
                    title: 'Cannot Delete', 
                    message: "This requirement is used by {$bookingCount} room " . ($bookingCount === 1 ? 'booking' : 'bookings') . ". Cannot delete referenced requirements.",
                    duration: 5000
                );
                return;
            }

            $requirement->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'Requirement deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'requirementsPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete requirement: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $requirements = Requirement::query()
            ->where('company_id', $this->companyId)
            ->when(trim($this->search) !== '', fn($q) =>
                $q->where('name', 'like', '%' . trim($this->search) . '%')
            )
            ->withCount('bookingRooms')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'requirementsPage');

        return view('livewire.pages.it-officer.requirements', compact('requirements'));
    }
}
