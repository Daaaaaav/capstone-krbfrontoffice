<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Livewire\Traits\HasManagerValidation;

#[Layout('layouts.it-officer')]
#[Title('Receptionist Users')]
class ReceptionistUsers extends Component
{
    use WithPagination, HasManagerValidation;
  
    public $search = '';
    public $statusFilter = 'all';
    public $showModal = false;
    public $editMode = false;
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $phone = '';
    public $status = 'active';

    public function setStatusFilter($status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();

        $this->editMode = false;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        try {
            $user = User::where('user_id', $id)->firstOrFail();

            $this->userId = $user->user_id;
            $this->name = $user->full_name;
            $this->email = $user->email;
            $this->phone = $user->phone_number;
            $this->status = $user->status ?? 'active';
            $this->password = '';

            $this->editMode = true;
            $this->showModal = true;

        } catch (\Exception $e) {

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to load user data: ' . $e->getMessage(),
                duration: 4000
            );
        }
    }

    public function closeModal()
    {
        $this->showModal = false;

        $this->resetForm();
    }

    private function resetForm()
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->phone = '';
        $this->status = 'active';
    }

    protected function rules()
    {
        return [
            'name' => $this->managerTextRules('Name', required: true, maxLength: 255),

            'email' => $this->managerEmailRules(
                required: true,
                uniqueTable: 'users',
                uniqueColumn: 'email',
                ignoreId: $this->userId ?? null,
                ignoreColumn: 'user_id'
            ),

            'phone' => $this->managerPhoneRules(required: false, maxLength: 20),

            'password' => $this->editMode
                ? 'nullable|min:6'
                : 'required|min:6',

            'status' => 'required|in:active,inactive',
        ];
    }

    public function save()
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate();

        try {

            $companyId = Auth::user()->company_id;

            if ($this->editMode) {

                $user = User::where('user_id', $this->userId)->firstOrFail();

                $user->full_name = $this->name;
                $user->email = $this->email;
                $user->phone_number = $this->phone;
                $user->status = $this->status;

                if (!empty($this->password)) {
                    $user->password = Hash::make($this->password);
                }

                $user->save();

                $this->dispatch(
                    'toast',
                    type: 'success',
                    title: 'Success',
                    message: 'Receptionist updated successfully!',
                    duration: 3000
                );

            } else {
                $role = Role::where('name', 'Receptionist')->first();

                if (!$role) {
                    throw new \Exception(
                        'Receptionist role not found in database'
                    );
                }

                User::create([
                    'full_name'    => $this->name,
                    'email'        => $this->email,
                    'password'     => Hash::make($this->password),
                    'phone_number' => $this->phone ?: '-',
                    'status'       => $this->status,
                    'company_id'   => $companyId,
                    'role_id'      => $role->role_id,
                ]);

                $this->dispatch(
                    'toast',
                    type: 'success',
                    title: 'Success',
                    message: 'Receptionist created successfully!',
                    duration: 3000
                );
            }

            $this->closeModal();

        } catch (\Exception $e) {

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to save receptionist: ' . $e->getMessage(),
                duration: 4000
            );
        }
    }

    public function delete($id)
    {
        try {

            $user = User::where('user_id', $id)->firstOrFail();

            // Prevent deleting yourself
            if ($user->user_id === Auth::id()) {
                $this->dispatch(
                    'toast',
                    type: 'error',
                    title: 'Error',
                    message: 'You cannot delete your own account.',
                    duration: 4000
                );
                return;
            }

            $user->delete();

            $this->dispatch(
                'toast',
                type: 'success',
                title: 'Success',
                message: 'Receptionist deleted successfully!',
                duration: 3000
            );

        } catch (\Exception $e) {

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to delete receptionist: ' . $e->getMessage(),
                duration: 4000
            );
        }
    }

    public function render()
    {
        try {

            $companyId = Auth::user()->company_id;

            $baseQuery = User::query()
                ->where('company_id', $companyId)
                ->whereHas('role', function ($q) {
                    $q->where('roles.name', 'Receptionist');
                });

            $totalCount    = (clone $baseQuery)->count();
            $activeCount   = (clone $baseQuery)->where('status', 'active')->count();
            $inactiveCount = (clone $baseQuery)->where('status', 'inactive')->count();

            $stats = [
                ['label' => 'Total Receptionists', 'value' => $totalCount,    'key' => 'all'],
                ['label' => 'Active',               'value' => $activeCount,   'key' => 'active'],
                ['label' => 'Inactive',             'value' => $inactiveCount, 'key' => 'inactive'],
            ];

            $query = clone $baseQuery;

            if (!empty($this->search)) {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.full_name', 'like', '%' . $search . '%')
                      ->orWhere('users.email', 'like', '%' . $search . '%')
                      ->orWhere('users.phone_number', 'like', '%' . $search . '%');
                });
            }


            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            $receptionists = $query->latest()->paginate(15);

            return view(
                'livewire.pages.it-officer.receptionist-users',
                compact('receptionists', 'stats')
            );

        } catch (\Exception $e) {

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to retrieve data: ' . $e->getMessage(),
                duration: 4000
            );

            return view(
                'livewire.pages.it-officer.receptionist-users',
                [
                    'receptionists' => User::where('user_id', 0)->paginate(15),
                    'stats' => [
                        ['label' => 'Total Receptionists', 'value' => 0, 'key' => 'all'],
                        ['label' => 'Active',               'value' => 0, 'key' => 'active'],
                        ['label' => 'Inactive',             'value' => 0, 'key' => 'inactive'],
                    ],
                ]
            );
        }
    }
}
