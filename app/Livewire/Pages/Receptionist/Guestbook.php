<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
use App\Models\Department; 
use App\Models\User;
use App\Models\IdType;
use App\Models\VisitorLanyard;
use App\Mail\GuestbookQrMail;
use App\Services\SecurityMonitoringService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.receptionist')]
#[Title('GuestBook')]
class Guestbook extends Component
{
    public $name;
    public $email;
    public $phone_number;
    public $instansi;
    public $keperluan;
    public $visitor_count = 1;
    public $storage_place;
    public $department_id;
    public $user_id;
    public $id_type_id;
    public $visitor_lanyard_id;
    public $departments_list = [];
    public $users_list = [];
    public $id_types_list = [];
    public $visitor_lanyards_list = [];
    public $date;
    public $jam_in;
    public $petugas_penjaga;
    public $historyGuests = [];
    public $isAutoFilled = false;
    
    public function mount(): void
    {
        $this->date = $this->date ?: now()->format('Y-m-d');
        if ($compId = $this->companyId()) {
            $this->departments_list = Department::where('company_id', $compId)
                ->get(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
            $this->id_types_list = IdType::where('company_id', $compId)
                ->get(['id', 'id_type_name'])
                ->map(fn($t) => ['id' => $t->id, 'name' => $t->id_type_name])
                ->toArray();
            $this->visitor_lanyards_list = VisitorLanyard::where('company_id', $compId)->where('status', 1)
                ->get(['id', 'lanyard_name'])
                ->map(fn($l) => ['id' => $l->id, 'name' => $l->lanyard_name])
                ->toArray();
        } else {
            $this->departments_list = Department::all(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
            $this->id_types_list = IdType::all(['id', 'id_type_name'])
                ->map(fn($t) => ['id' => $t->id, 'name' => $t->id_type_name])
                ->toArray();
            $this->visitor_lanyards_list = VisitorLanyard::where('status', 1)->get(['id', 'lanyard_name'])
                ->map(fn($l) => ['id' => $l->id, 'name' => $l->lanyard_name])
                ->toArray();
        }
        
        if ($this->department_id) {
            $this->loadUsers();
        }
    }

    public function updatedDepartmentId($value)
    {
        $this->user_id = null; 
        $this->loadUsers($value);
        $this->dispatch('users-list-updated', users: $this->users_list);
    }
    
    public function updatedName($value)
    {
        $this->isAutoFilled = false;

        if (strlen($value) >= 2) {
            $companyId = $this->companyId();
            $query = GuestbookModel::where('name', 'like', "%{$value}%");
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
            $rows = $query->orderBy('created_at', 'desc')->get();
            $byName = [];
            foreach ($rows as $g) {
                $name = $g->name;
                if (!isset($byName[$name])) {
                    $byName[$name] = [
                        'name'         => $name,
                        'email'        => $g->email,
                        'phone_number' => $g->phone_number,
                        'instansi'     => $g->instansi,
                        'keperluan'    => $g->keperluan,
                    ];
                } else {
                    foreach (['email', 'phone_number', 'instansi', 'keperluan'] as $field) {
                        if (empty($byName[$name][$field]) && !empty($g->$field)) {
                            $byName[$name][$field] = $g->$field;
                        }
                    }
                }
            }

            $this->historyGuests = array_values(
                array_slice(
                    array_filter($byName, fn($g) => !empty($g['name'])),
                    0,
                    5
                )
            );
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
            $this->keperluan = $guest['keperluan'];

            $this->isAutoFilled = true;
            $this->historyGuests = [];
        }
    }

    private function loadUsers(?string $departmentId = null): void
    {
        $departmentId = $departmentId ?? $this->department_id;
        
        if ($departmentId) {
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
            'keperluan'     => ['required', 'string', 'max:255'],
            'visitor_count' => ['required', 'integer', 'min:1', 'max:999'],
            'storage_place' => ['nullable', 'integer', 'min:1', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,department_id'],
            'user_id'       => ['nullable', 'exists:users,user_id'],
            'id_type_id'         => ['nullable', 'exists:id_types,id'],
            'visitor_lanyard_id' => ['nullable', 'exists:visitor_lanyards,id'],
        ];
    }

    private function companyId(): ?int
    {
        return Auth::user()?->company_id;
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));
        $this->date   = $now->toDateString();
        $this->jam_in = $now->format('H:i');

        $user = Auth::user();
        $this->petugas_penjaga = $user?->full_name ?? $user?->name ?? 'Petugas Receptionist';
        $companyId = $this->companyId();
        
        $this->department_id = $this->department_id === '' ? null : $this->department_id;
        $this->user_id       = $this->user_id === '' ? null : $this->user_id;
        $this->id_type_id    = $this->id_type_id === '' ? null : $this->id_type_id;
        $this->visitor_lanyard_id = $this->visitor_lanyard_id === '' ? null : $this->visitor_lanyard_id;

        $validatedData = $this->validate();

        SecurityMonitoringService::logFormSubmit('guestbook', $validatedData);

        $qrToken = GuestbookModel::generateQrToken();
        $visitorCount = (int) $validatedData['visitor_count'];

        $entryData = array_merge($validatedData, [
            'date'                 => $this->date,
            'jam_in'               => $this->jam_in,
            'petugas_penjaga'      => $this->petugas_penjaga,
            'company_id'           => $companyId,
            'jam_out'              => null,
            'qr_token'             => $qrToken,
            'qr_status'            => 'pending',
            'visitor_count'        => $visitorCount,
            'scheduled_by_manager' => false, 
            'id_type_id'           => $this->id_type_id,
            'visitor_lanyard_id'   => $this->visitor_lanyard_id,
        ]);

        $entry = GuestbookModel::create($entryData);
        $qrTokens = GuestbookQrCode::generateTokenBatch($visitorCount);
        foreach ($qrTokens as $index => $token) {
            GuestbookQrCode::create([
                'guestbook_id'   => $entry->guestbook_id,
                'qr_token'       => $token,
                'visitor_number' => $index + 1,
            ]);
        }

        // Mark the selected lanyard as unavailable
        if ($this->visitor_lanyard_id) {
            VisitorLanyard::where('id', $this->visitor_lanyard_id)->update(['status' => 0]);
        }

        $emailFailed = false;
        $emailLogOnly = false;

        if (!empty($validatedData['email'])) {
            if (config('mail.default') === 'log' || config('mail.default') === 'array' || config('mail.default') === null) {
                $emailLogOnly = true;
                try {
                    $entry->load('qrCodes');
                    $officerEmail = Auth::user()?->email ?: config('mail.from.address');
                    Mail::alwaysFrom($officerEmail, 'Kebun Raya Bogor Receptionist');
                    Mail::to($validatedData['email'])->send(new GuestbookQrMail($entry, $officerEmail));
                } catch (\Throwable $e) {}
            } else {
                try {
                    $entry->load('qrCodes');
                    $officerEmail = Auth::user()?->email ?: config('mail.from.address');
                    Mail::alwaysFrom($officerEmail, 'Kebun Raya Bogor Receptionist');
                    Mail::to($validatedData['email'])->send(new GuestbookQrMail($entry, $officerEmail));
                } catch (\Throwable $e) {
                    Log::error('GuestbookQrMail failed: ' . $e->getMessage(), ['exception' => $e]);
                    $emailFailed = true;
                }
            }
        }

        $this->reset(['name', 'email', 'phone_number', 'instansi', 'keperluan', 'visitor_count', 'department_id', 'user_id', 'storage_place', 'id_type_id', 'visitor_lanyard_id', 'isAutoFilled', 'historyGuests']);
        $this->visitor_count = 1;
        $this->users_list = [];

        // Refresh lanyards list so the used lanyard no longer appears
        $compId = $this->companyId();
        $lanyardQuery = VisitorLanyard::where('status', 1);
        if ($compId) {
            $lanyardQuery->where('company_id', $compId);
        }
        $this->visitor_lanyards_list = $lanyardQuery->get(['id', 'lanyard_name'])
            ->map(fn($l) => ['id' => $l->id, 'name' => $l->lanyard_name])
            ->toArray();

        $this->dispatch('lanyards-list-updated', lanyards: $this->visitor_lanyards_list);
        $this->dispatch('$refresh');
        
        if ($emailFailed) {
            $this->dispatch('toast', type: 'warning', title: 'Data Tersimpan (Tanpa Email)', message: 'Guest ditambah (' . $visitorCount . ' pengunjung). Namun, email gagal terkirim ke alamat tujuan.', duration: 7000);
        } elseif ($emailLogOnly) {
            $this->dispatch('toast', type: 'warning', title: 'Data Tersimpan (Sistem Belum Setup)', message: 'Sistem email belum di-setup (Mode Log). Email tidak benar-benar dikirim ke tamu.', duration: 7000);
        } else {
            $toastMessage = !empty($validatedData['email'])
                ? 'Guest ditambah (' . $visitorCount . ' pengunjung). QR code dikirim ke ' . $validatedData['email'] . '.'
                : 'Guest ditambah (' . $visitorCount . ' pengunjung). (Tidak ada email – QR tidak dikirim)';

            $this->dispatch('toast', type: 'success', title: 'Ditambah', message: $toastMessage, duration: 4000);
        }
        
        session()->flash('saved', true);
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook');
    }
}