<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
use App\Models\Department;
use App\Models\User;
use App\Mail\GuestbookQrMail;
use App\Services\SecurityMonitoringService;

#[Layout('layouts.receptionist')]
#[Title('GuestBook')]
class Guestbook extends Component
{
    // Form fields yang diisi user
    public $name;
    public $email;
    public $phone_number;
    public $instansi;
    public $keperluan;
    public $visitor_count = 1;
    public $storage_place;
    
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
        // Push updated list to Alpine (needed because the dropdown uses wire:ignore)
        $this->dispatch('users-list-updated', users: $this->users_list);
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
            'email'         => ['required', 'email', 'max:255'],
            'phone_number'  => ['nullable', 'string', 'max:50'],
            'instansi'      => ['nullable', 'string', 'max:255'],
            'keperluan'     => ['nullable', 'string', 'max:255'],
            'visitor_count' => ['required', 'integer', 'min:1', 'max:999'],
            'storage_place' => ['nullable', 'integer', 'min:1', 'max:100'],
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

        // Generate master QR token for backward compat
        $qrToken = GuestbookModel::generateQrToken();
        $visitorCount = (int) $validatedData['visitor_count'];

        // Prepare data including auto-filled fields
        $entryData = array_merge($validatedData, [
            'date'              => $this->date,
            'jam_in'            => $this->jam_in,
            'petugas_penjaga'   => $this->petugas_penjaga,
            'company_id'        => $companyId,
            'jam_out'           => null,
            'qr_token'          => $qrToken,
            'qr_status'         => 'pending',
            'visitor_count'     => $visitorCount,
        ]);

        // Saves data to the database
        $entry = GuestbookModel::create($entryData);

        // Generate individual QR codes for each visitor
        $qrTokens = GuestbookQrCode::generateTokenBatch($visitorCount);
        foreach ($qrTokens as $index => $token) {
            GuestbookQrCode::create([
                'guestbook_id'   => $entry->guestbook_id,
                'qr_token'       => $token,
                'visitor_number' => $index + 1,
            ]);
        }

        // Send QR code email if an email address was provided
        if (!empty($validatedData['email'])) {
            try {
                // Reload with qrCodes for the email
                $entry->load('qrCodes');
                Mail::to($validatedData['email'])->send(new GuestbookQrMail($entry));
            } catch (\Throwable $e) {
                Log::error('GuestbookQrMail failed: ' . $e->getMessage(), ['exception' => $e]);
                // Non-fatal — entry is already saved, just warn the receptionist
                $this->dispatch(
                    'toast',
                    type: 'warning',
                    title: 'Email Gagal Terkirim',
                    message: 'Data tamu disimpan, namun QR code gagal dikirim ke email. Periksa konfigurasi mail.',
                    duration: 6000
                );
            }
        }

        // Reset form
        $this->reset(['name', 'email', 'phone_number', 'instansi', 'keperluan', 'visitor_count', 'department_id', 'user_id', 'storage_place']);
        $this->visitor_count = 1;
        // Reset user list 
        $this->users_list = [];

        $this->dispatch('$refresh');
        // Tell Alpine dropdowns to clear (needed because they use wire:ignore)
        $this->dispatch('guestbook-form-reset');

        $toastMessage = !empty($validatedData['email'])
            ? 'Guest ditambah (' . $visitorCount . ' pengunjung). QR code dikirim ke ' . $validatedData['email'] . '.'
            : 'Guest ditambah (' . $visitorCount . ' pengunjung). (Tidak ada email – QR tidak dikirim)';

        $this->dispatch('toast', type: 'success', title: 'Ditambah', message: $toastMessage, duration: 4000);
        session()->flash('saved', true);
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook');
    }
}