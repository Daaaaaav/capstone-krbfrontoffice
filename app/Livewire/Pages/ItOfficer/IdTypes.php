<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\IdType;

#[Layout('layouts.it-officer')]
#[Title('Manage ID Types')]
class IdTypes extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $companyId = 0;
    public string $search = '';
    public string $id_type_name = '';
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $edit_id = null;
    public string $edit_id_type_name = '';

    public function mount(): void
    {
        $this->companyId = (int) (Auth::user()->company_id ?? 0);
    }

    public function updatingSearch(): void
    {
        $this->resetPage(pageName: 'idTypesPage');
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
            $idType = IdType::forCompany($this->companyId)->findOrFail($id);

            $this->edit_id = $idType->id;
            $this->edit_id_type_name = (string) $idType->id_type_name;

            $this->editMode = true;
            $this->showModal = true;
            $this->resetErrorBag();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load ID type: ' . $e->getMessage(), duration: 4000);
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
        $this->id_type_name = '';
        $this->edit_id = null;
        $this->edit_id_type_name = '';
    }

    protected function createRules(): array
    {
        return [
            'id_type_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('id_types', 'id_type_name')
                    ->where(fn($q) => $q->where('company_id', $this->companyId)),
            ],
        ];
    }

    protected function editRules(): array
    {
        return [
            'edit_id_type_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('id_types', 'id_type_name')
                    ->ignore($this->edit_id, 'id')
                    ->where(fn($q) => $q->where('company_id', $this->companyId)),
            ],
        ];
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        try {
            if ($this->editMode) {
                $this->validate($this->editRules());

                IdType::forCompany($this->companyId)
                    ->where('id', $this->edit_id)
                    ->update([
                        'id_type_name' => trim($this->edit_id_type_name),
                    ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'ID Type updated successfully!', duration: 3000);
            } else {
                $this->validate($this->createRules());

                IdType::create([
                    'company_id' => $this->companyId,
                    'id_type_name' => trim($this->id_type_name),
                ]);

                $this->dispatch('toast', type: 'success', title: 'Success', message: 'ID Type created successfully!', duration: 3000);
            }

            $this->closeModal();
            $this->resetPage(pageName: 'idTypesPage');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to save ID type: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function delete(int $id): void
    {
        try {
            $idType = IdType::forCompany($this->companyId)->findOrFail($id);

            // Check if this ID type is referenced by any guestbook entries
            if ($idType->isReferenced()) {
                $count = $idType->guestbooks()->count();
                $this->dispatch('toast', 
                    type: 'error', 
                    title: 'Cannot Delete', 
                    message: "This ID type is used by {$count} guestbook " . ($count === 1 ? 'entry' : 'entries') . ". Cannot delete referenced ID types.",
                    duration: 5000
                );
                return;
            }

            $idType->delete();

            $this->dispatch('toast', type: 'success', title: 'Success', message: 'ID Type deleted successfully!', duration: 3000);
            $this->resetPage(pageName: 'idTypesPage');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to delete ID type: ' . $e->getMessage(), duration: 4000);
        }
    }

    public function render()
    {
        $idTypes = IdType::query()
            ->forCompany($this->companyId)
            ->when(trim($this->search) !== '', fn($q) =>
                $q->where('id_type_name', 'like', '%' . trim($this->search) . '%')
            )
            ->withCount('guestbooks')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'idTypesPage');

        return view('livewire.pages.it-officer.id-types', compact('idTypes'));
    }
}
