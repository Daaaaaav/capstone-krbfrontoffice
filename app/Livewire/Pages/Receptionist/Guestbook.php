<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;
use App\Models\Department; 
use App\Models\User;
use App\Services\SecurityMonitoringService;

#[Layout('layouts.receptionist')]
#[Title('GuestBook')]
class Guestbook extends Component
{
    // Form fields yang diisi user
    public $name;
    public $phone_number;
    public $instansi;
    public $keperluan;
    
    // Field baru (Nullable / Optional)
    public $department_id;
    public $user_id;

    // Data Lists untuk Dropdown
    public $departments_list = [];
    public $users_list = [];

    // Field internal (diisi otomatis)
    public $date;
    public $jam_in;
    public $petugas_penjaga;

    // Autocomplete state
    public $historyGuests = [];
    public $isAutoFilled = false;

    // ---- Compatibility props (omitted for brevity, assume they exist) ----
    
    public function mount(): void
    {
        $this->date = $this->date ?: now()->format('Y-m-d');

        // Load list departemen
        if ($compId = $this->companyId()) {
            // Load departments belonging to the current user's company
            $this->departments_list = Department::where('company_id', $compId)
                ->get(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
        } else {
            // Fallback: Load all departments if company_id is null/not used
            $this->departments_list = Department::all(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
        }
        
        // Load users if department_id is already set (e.g., via session or initial state)
        if ($this->department_id) {
            $this->loadUsers();
        }
    }

    // Hook: Ketika department_id berubah, load user yang sesuai
    public function updatedDepartmentId($value)
    {
        // Reset user yang dipilih sebelumnya
        $this->user_id = null; 
        $this->loadUsers($value);
    }
    
    // Autocomplete for Name
    public function updatedName($value)
    {
        $this->isAutoFilled = false;

        if (strlen($value) >= 2) {
            $companyId = $this->companyId();
            $query = GuestbookModel::where('name', 'like', "%{$value}%");
            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            // Get unique guests by name, sorted alphabetically
            $this->historyGuests = $query->orderBy('name', 'asc')
                ->get()
                ->unique('name')
                ->take(5)
                ->map(fn($g) => [
                    'name' => $g->name,
                    'email' => $g->email,
                    'phone_number' => $g->phone_number,
                    'instansi' => $g->instansi,
                    'keperluan' => $g->keperluan
                ])
                ->values()
                ->toArray();
        } else {
            $this->historyGuests = [];
        }
    }

    public function selectHistoryGuest($index)
    {
        if (isset($this->historyGuests[$index])) {
            $guest = $this->historyGuests[$index];
            $this->name = $guest['name'];
            $this->email = $guest['email'];
            $this->phone_number = $guest['phone_number'];
            $this->instansi = $guest['instansi'];
            // Force visitor_count to be empty so the receptionist must verify and input it
            $this->visitor_count = null;

            $this->isAutoFilled = true;
            $this->historyGuests = [];
        }
    }

    // Helper function to load users
    private function loadUsers(?string $departmentId = null): void
    {
        $departmentId = $departmentId ?? $this->department_id;
        
        if ($departmentId) {
            // Load users based on the selected department ID
            // Convert to plain array so Livewire serializes it correctly between requests
            $this->users_list = User::where('department_id', (int)$departmentId)
                ->get(['user_id', 'full_name'])
                ->map(fn($u) => ['id' => $u->user_id, 'full_name' => $u->full_name])
                ->toArray();
        } else {
            $this->users_list = [];
        }
    }


    protected function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'phone_number'  => ['nullable', 'string', 'max:50'],
            'instansi'      => ['nullable', 'string', 'max:255'],
            'keperluan'     => ['nullable', 'string', 'max:255'],
            // Ensures department_id and user_id are nullable
            'department_id' => ['nullable', 'exists:departments,department_id'],
            'user_id'       => ['nullable', 'exists:users,user_id'],
        ];
    }

    // Helper functions (omitted for brevity, assume they exist)
    private function companyId(): ?int
    {
        return Auth::user()?->company_id;
    }

    public function save(): void
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));
        $this->date   = $now->toDateString();
        $this->jam_in = $now->format('H:i');

        $user = Auth::user();
        $this->petugas_penjaga = $user?->full_name ?? $user?->name ?? 'Petugas Receptionist';
        $companyId = $this->companyId();
        
        // 🔥 FIX FOR SQLSTATE[22007]: Converts empty string '' (from the select box) to null
        // so MySQL accepts it for an INT/Foreign Key column.
        $this->department_id = $this->department_id === '' ? null : $this->department_id;
        $this->user_id       = $this->user_id === '' ? null : $this->user_id;

        $validatedData = $this->validate();

        SecurityMonitoringService::logFormSubmit('guestbook', $validatedData);

        // Prepare data including auto-filled fields
        $entryData = array_merge($validatedData, [
            'date'              => $this->date,
            'jam_in'            => $this->jam_in,
            'petugas_penjaga'   => $this->petugas_penjaga,
            'company_id'        => $companyId, 
            'jam_out'           => null, 
        ]);

        // Saves data to the database
        GuestbookModel::create($entryData); 

        // Reset form
        $this->reset(['name', 'email', 'phone_number', 'instansi', 'keperluan', 'visitor_count', 'department_id', 'user_id', 'storage_place', 'isAutoFilled', 'historyGuests']);
        $this->visitor_count = 1;
        // Reset user list 
        $this->users_list = []; 

        $this->dispatch('$refresh');
        $this->dispatch('toast', type: 'success', title: 'Ditambah', message: 'Guest ditambah.', duration: 3000);
        session()->flash('saved', true);
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook');
    }
}