<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
use App\Models\Department;
use App\Models\User;
use App\Mail\GuestbookQrMail;

#[Layout('layouts.manager')]
#[Title('Guestbook — Schedule Future Visitor')]
class GuestbookForm extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    // ── Tabs ───────────────────────────────────────────────────────────────
    public string $activeTab = 'form'; // form | upcoming

    // ── Form fields ────────────────────────────────────────────────────────
    public string $name          = '';
    public string $email         = '';
    public string $phone_number  = '';
    public string $instansi      = '';
    public string $keperluan     = '';
    public int    $visitor_count = 1;
    public ?int   $storage_place = null;

    // Scheduled arrival (manager can set a future date & time)
    public string $scheduled_date = '';
    public string $scheduled_time = '';

    // Optional target department / user inside the company
    public ?int $department_id = null;
    public ?int $user_id       = null;

    // ── Dropdown data ──────────────────────────────────────────────────────
    public array $departments_list = [];
    public array $users_list       = [];

    // ── Upcoming list filters ──────────────────────────────────────────────
    public string $q = '';
    public int $perPage = 8;

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $this->departments_list = Department::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('department_name')
            ->get(['department_id', 'department_name'])
            ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
            ->toArray();

        // Default to tomorrow so it's clearly a future booking
        $this->scheduled_date = now($this->tz)->addDay()->toDateString();
        $this->scheduled_time = '09:00';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['form', 'upcoming']) ? $tab : 'form';
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->user_id    = null;
        $this->users_list = [];

        if (!$this->department_id) {
            return;
        }

        $this->users_list = User::where('department_id', $this->department_id)
            ->orderBy('full_name')
            ->get(['user_id', 'full_name'])
            ->map(fn($u) => ['id' => $u->user_id, 'full_name' => $u->full_name])
            ->toArray();

        $this->dispatch('users-list-updated', users: $this->users_list);
    }

    protected function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone_number'   => ['nullable', 'string', 'max:50'],
            'instansi'       => ['nullable', 'string', 'max:255'],
            'keperluan'      => ['required', 'string', 'max:255'],
            'visitor_count'  => ['required', 'integer', 'min:1', 'max:999'],
            'storage_place'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'string'],
            'department_id'  => ['nullable', 'exists:departments,department_id'],
            'user_id'        => ['nullable', 'exists:users,user_id'],
        ];
    }

    public function save(): void
    {
        $this->department_id = $this->department_id === '' ? null : $this->department_id;
        $this->user_id       = $this->user_id === '' ? null : $this->user_id;

        $validated = $this->validate();

        $user      = Auth::user();
        $companyId = $user->company_id ?? null;

        // For manager-scheduled entries:
        //  - date  = scheduled_date (could be future)
        //  - jam_in = scheduled_time
        //  - jam_out = null  (will be filled when visitor actually checks out)
        //  - petugas_penjaga = manager name
        $qrToken = GuestbookModel::generateQrToken();
        $visitorCount = (int) $validated['visitor_count'];

        $entry = GuestbookModel::create([
            'company_id'      => $companyId,
            'department_id'   => $validated['department_id'] ?? null,
            'user_id'         => $validated['user_id'] ?? null,
            'date'            => $validated['scheduled_date'],
            'jam_in'          => $validated['scheduled_time'],
            'jam_out'         => null,
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'phone_number'    => $validated['phone_number'] ?? null,
            'instansi'        => $validated['instansi'] ?? null,
            'keperluan'       => $validated['keperluan'],
            'petugas_penjaga' => $user->full_name ?? $user->name ?? 'Manager',
            'storage_place'   => $validated['storage_place'] ?? null,
            'visitor_count'   => $visitorCount,
            'qr_token'        => $qrToken,
            'qr_status'       => 'pending',
        ]);

        // Generate individual QR codes
        $qrTokens = GuestbookQrCode::generateTokenBatch($visitorCount);
        foreach ($qrTokens as $index => $token) {
            GuestbookQrCode::create([
                'guestbook_id'   => $entry->guestbook_id,
                'qr_token'       => $token,
                'visitor_number' => $index + 1,
            ]);
        }

        // Send QR email
        if (!empty($validated['email'])) {
            try {
                $entry->load('qrCodes');
                Mail::to($validated['email'])->send(new GuestbookQrMail($entry));
            } catch (\Throwable $e) {
                Log::error('GuestbookQrMail (manager) failed: ' . $e->getMessage());
                $this->dispatch('toast', type: 'warning', title: 'Email Failed',
                    message: 'Entry saved, but QR email could not be sent. Check mail config.',
                    duration: 6000);
            }
        }

        $this->reset([
            'name', 'email', 'phone_number', 'instansi', 'keperluan',
            'visitor_count', 'storage_place', 'department_id', 'user_id',
        ]);
        $this->visitor_count   = 1;
        $this->users_list      = [];
        $this->scheduled_date  = now($this->tz)->addDay()->toDateString();
        $this->scheduled_time  = '09:00';
        $this->activeTab       = 'upcoming';

        $this->dispatch('guestbook-form-reset');

        $msg = !empty($validated['email'])
            ? 'Visitor scheduled (' . $visitorCount . ' pax). QR sent to ' . $validated['email'] . '.'
            : 'Visitor scheduled (' . $visitorCount . ' pax). No email provided.';

        $this->dispatch('toast', type: 'success', title: 'Scheduled', message: $msg, duration: 4000);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;
        $today = now($this->tz)->toDateString();

        // Show upcoming entries (future date) created with manager as petugas_penjaga
        $upcoming = GuestbookModel::query()
            ->where('company_id', $companyId)
            ->whereNull('jam_out')
            ->where('date', '>=', $today)
            ->when($this->q !== '', function ($q) {
                $like = '%' . $this->q . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)
                    ->orWhere('instansi', 'like', $like)
                    ->orWhere('keperluan', 'like', $like));
            })
            ->orderBy('date')
            ->orderBy('jam_in')
            ->paginate($this->perPage);

        return view('livewire.pages.manager.guestbook-form', [
            'upcoming' => $upcoming,
        ]);
    }
}
