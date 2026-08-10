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
use App\Models\Department;
use App\Livewire\Traits\HasManagerValidation;

#[Layout('layouts.it-officer')]
#[Title('Manage Users per Department')]
class UsersPerDepartment extends Component
{
    use WithPagination, HasManagerValidation;

    public $search = '';
    public $showModal = false;
    public $editMode = false;
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $phone = '';
    public $status = 'active';
    
    // Track expanded departments
    public $expandedDepartments = [];
    
    // Track pagination per department
    public $departmentPages = [];

    public function mount()
    {
        // Initialize expanded state for all departments
        $departments = Department::where('company_id', Auth::user()->company_id)
            ->orderBy('department_name')
            ->get();
            
        foreach ($departments as $dept) {
            $this->expandedDepartments[$dept->department_id] = true;
            $this->departmentPages[$dept->department_id] = 1;
        }
        
        // Initialize state for unassigned users (department_id = 0)
        $this->expandedDepartments[0] = true;
        $this->departmentPages[0] = 1;
    }

    public function toggleDepartment($departmentId)
    {
        $this->expandedDepartments[$departmentId] = !($this->expandedDepartments[$departmentId] ?? false);
    }

    public function setDepartmentPage($departmentId, $page)
    {
        $this->departmentPages[$departmentId] = $page;
    }

    public function updatingSearch()
    {
        // Reset all department pages when search changes
        foreach ($this->departmentPages as $deptId => $page) {
            $this->departmentPages[$deptId] = 1;
        }
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
                    message: 'User updated successfully!',
                    duration: 3000
                );
            } else {
                // For create, you would need to specify role and department
                // This is simplified - you may want to add role/department fields to the form
                $this->dispatch(
                    'toast',
                    type: 'info',
                    title: 'Info',
                    message: 'User creation not implemented in this view. Use role-specific pages.',
                    duration: 4000
                );
                $this->closeModal();
                return;
            }

            $this->closeModal();

        } catch (\Exception $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to save user: ' . $e->getMessage(),
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
                message: 'User deleted successfully!',
                duration: 3000
            );

        } catch (\Exception $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Failed to delete user: ' . $e->getMessage(),
                duration: 4000
            );
        }
    }

    public function render()
    {
        try {
            $companyId = Auth::user()->company_id;

            // Get departments with user counts
            // Match Receptionist behavior: count ALL users in department, regardless of role
            $departments = Department::where('company_id', $companyId)
                ->withCount('users')
                ->orderBy('department_name')
                ->get();

            // Build department users data with pagination
            $departmentUsers = [];
            
            foreach ($departments as $department) {
                $page = $this->departmentPages[$department->department_id] ?? 1;
                $perPage = 10;

                // Match Receptionist behavior: retrieve ALL users in department
                $query = User::query()
                    ->where('company_id', $companyId)
                    ->where('department_id', $department->department_id)
                    ->with(['role', 'department']);

                // Apply search filter
                if (!empty($this->search)) {
                    $search = $this->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('users.full_name', 'like', '%' . $search . '%')
                          ->orWhere('users.email', 'like', '%' . $search . '%')
                          ->orWhere('users.phone_number', 'like', '%' . $search . '%');
                    });
                }

                $total = $query->count();
                $lastPage = ceil($total / $perPage);
                
                // Ensure current page is valid
                if ($page > $lastPage && $lastPage > 0) {
                    $page = $lastPage;
                    $this->departmentPages[$department->department_id] = $page;
                }

                $users = $query->latest()
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get();

                $departmentUsers[$department->department_id] = [
                    'users' => $users,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => max($lastPage, 1),
                ];
            }

            // Handle users without department
            // Match Receptionist behavior: show ALL unassigned users
            $unassignedQuery = User::query()
                ->where('company_id', $companyId)
                ->whereNull('department_id')
                ->with(['role']);

            if (!empty($this->search)) {
                $search = $this->search;
                $unassignedQuery->where(function ($q) use ($search) {
                    $q->where('users.full_name', 'like', '%' . $search . '%')
                      ->orWhere('users.email', 'like', '%' . $search . '%')
                      ->orWhere('users.phone_number', 'like', '%' . $search . '%');
                });
            }

            $unassignedPage = $this->departmentPages[0] ?? 1;
            $perPage = 10;
            $unassignedTotal = $unassignedQuery->count();
            $unassignedLastPage = ceil($unassignedTotal / $perPage);
            
            if ($unassignedPage > $unassignedLastPage && $unassignedLastPage > 0) {
                $unassignedPage = $unassignedLastPage;
                $this->departmentPages[0] = $unassignedPage;
            }

            $unassignedUsers = $unassignedQuery->latest()
                ->skip(($unassignedPage - 1) * $perPage)
                ->take($perPage)
                ->get();

            $unassignedData = [
                'users' => $unassignedUsers,
                'total' => $unassignedTotal,
                'current_page' => $unassignedPage,
                'per_page' => $perPage,
                'last_page' => max($unassignedLastPage, 1),
            ];

            return view(
                'livewire.pages.it-officer.users-per-department',
                compact('departments', 'departmentUsers', 'unassignedData')
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
                'livewire.pages.it-officer.users-per-department',
                [
                    'departments' => collect(),
                    'departmentUsers' => [],
                    'unassignedData' => [
                        'users' => collect(),
                        'total' => 0,
                        'current_page' => 1,
                        'per_page' => 10,
                        'last_page' => 1,
                    ],
                ]
            );
        }
    }
}