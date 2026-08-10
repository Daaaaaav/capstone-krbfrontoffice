<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\VisitorLanyard;

#[Layout('layouts.it-officer')]
#[Title('Manage Visitor Lanyards')]
class VisitorLanyards extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $companyId = 0;
    public string $search = '';
    public string $lanyard_name = '';
    public bool $status = true;
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $edit_id = null;
    public string $edit_lanyard_name = '';
    public bool $edit_status = true;

    public function mount(): void
    {
        $this->companyId = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'lanyardsPage');
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
            $lanyard = VisitorLanyard::forCompany($this->companyId)->findOrFail($id);

            $this->edit_id = $lanyard->id;
            $this->edit_lanyard_name = (string) $lanyard->lanyard_name;
            $this->edit_status = (bool) $lanyard->status;

            $this->editMode = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load lanyard: ' . $e->getMessage(), duration: 4000);
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
        $this->lanyard_name = '';
        $this->status = true;
        $this->edit_id = null;
        $this->edit_lanyard_name = '';
        $this->edit_status = true;
    }

    protected function createRules(): array
    {
        return [
            'lanyard_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('visitor_lanyards', 'lanyard_name')
                    ->where(fn($q) => $q->where('company_id', $this->companyId)),
            ],
            'status' => 'boolean',
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_lanyard_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('visitor_lanyards', 'lanyard_name')
                    ->ignore($this->edit_id, 'id')
                    ->where(fn($q) => $q->where('company_id', $this->companyId)),
            ],
            'edit_status' => 'boolean',
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                VisitorLanyard::forCompany($this->companyId)
                    ->where('id', $this->edit_id)
                    ->update([
                        'lanyard_name' => trim($this->edit_lanyard_name),
                        'status' => (bool) $this->edit_status,
                    ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Lanyard updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                VisitorLanyard::create([
                    'company_id' => $this->companyId,
                    'lanyard_name' => trim($this->lanyard_name),
                    'status' => (bool) $this->status,
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'Lanyard created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'lanyardsPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save lanyard: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function toggleStatus(int $id): void
    {
        try {
            $lanyard = VisitorLanyard::forCompany($this->companyId)->findOrFail($id);
            $newStatus = ! $lanyard->status;
            
            // If trying to make it available, check if it's currently assigned
            if ($newStatus && $lanyard->isCurrentlyAssigned()) {
                $this->dispatch('toast', 
                    type: 'warning', 
                    title: 'Cannot Activate', 
                    message: 'This lanyard is currently assigned to an active visitor. Please check them out first.',
                    duration: 5000
                );
                return;
            }

            $lanyard->update(['status' => $newStatus]);

            $state = $newStatus ? 'available' : 'unavailable';
            $this->dispatch('toast', 
                type: 'success', 
                title: 'Status Updated', 
                message: "Lanyard marked as {$state}.",
                duration: 3000
            );
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update status: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $lanyard = VisitorLanyard::forCompany($this->companyId)->findOrFail($id);

            // Check if this lanyard is currently assigned to an active visitor
            if ($lanyard->isCurrentlyAssigned()) {
                $this->dispatch('toast', 
                    type: 'error', 
                    title: 'Cannot Delete', 
                    message: 'This lanyard is currently assigned to an active visitor. Please check them out before deleting.',
                    duration: 5000
                );
                return;
            }

            // Check if this lanyard has historical references
            if ($lanyard->isReferenced()) {
                $count = $lanyard->guestbooks()->count();
                $this->dispatch('toast', 
                    type: 'error', 
                    title: 'Cannot Delete', 
                    message: "This lanyard has been used by {$count} guestbook " . ($count === 1 ? 'entry' : 'entries') . ". Cannot delete lanyards with historical references.",
                    duration: 5000
                );
                return;
            }

            $lanyard->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'Lanyard deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'lanyardsPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete lanyard: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $lanyards = VisitorLanyard::query()
            ->forCompany($this->companyId)
            ->when(trim($this->search) !== '', fn($q) =>
                $q->where('lanyard_name', 'like', '%' . trim($this->search) . '%')
            )
            ->withCount('guestbooks')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'lanyardsPage');

        return view('livewire.pages.it-officer.visitor-lanyards', compact('lanyards'));
    }
}
