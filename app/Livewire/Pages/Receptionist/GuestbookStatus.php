<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
#[Layout('layouts.receptionist')]
#[Title('Guestbook Status')]
class GuestbookStatus extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';
    public int $perPage = 9;
    public ?string $petugasFilter = null;
    public ?string $filter_date = null;
    public string $dateMode = 'semua';
    public string $viewMode = 'card';
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $edit = [
        'name'            => null,
        'email'           => null,
        'phone_number'    => null,
        'instansi'        => null,
        'keperluan'       => null,
        'petugas_penjaga' => null,
        'visitor_count'   => 1,
        'department_id'   => null,
        'user_id'         => null,
    ];

    public array $departments_list = [];
    public array $users_list = [];

    public function mount(): void
    {
        $compId = $this->companyId();
        if ($compId) {
            $this->departments_list = \App\Models\Department::where('company_id', $compId)
                ->get(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
        } else {
            $this->departments_list = \App\Models\Department::all(['department_id', 'department_name'])
                ->map(fn($d) => ['id' => $d->department_id, 'name' => $d->department_name])
                ->toArray();
        }
    }

    public function loadUsersForEdit($departmentId)
    {
        $this->edit['user_id'] = null;
        if ($departmentId) {
            $this->users_list = \App\Models\User::where('department_id', (int)$departmentId)
                ->get(['user_id', 'full_name'])
                ->map(fn($u) => ['id' => $u->user_id, 'full_name' => $u->full_name])
                ->toArray();
        } else {
            $this->users_list = [];
        }
    }

    public function updated($property, $value)
    {
        if ($property === 'edit.department_id') {
            $this->loadUsersForEdit($value);
        }
    }

    protected function rulesEdit(): array
    {
        return [
            'edit.name'            => ['required', 'string', 'max:255'],
            'edit.email'           => ['nullable', 'email', 'max:255'],
            'edit.phone_number'    => ['nullable', 'string', 'max:50'],
            'edit.instansi'        => ['nullable', 'string', 'max:255'],
            'edit.keperluan'       => ['nullable', 'string', 'max:255'],
            'edit.petugas_penjaga' => ['required', 'string', 'max:255'],
            'edit.visitor_count'   => ['required', 'integer', 'min:1', 'max:999'],
            'edit.department_id'   => ['nullable', 'exists:departments,department_id'],
            'edit.user_id'         => ['nullable', 'exists:users,user_id'],
        ];
    }

    private function companyId(): ?int
    {
        return Auth::user()?->company_id;
    }

    private function findOwnedOrFail(int $id): GuestbookModel
    {
        return GuestbookModel::whereKey($id)
            ->where('company_id', $this->companyId())
            ->firstOrFail();
    }

    public function updatingQ(): void
    {
        $this->resetPage('activePage');
    }

    public function updatingFilterDate(): void
    {
        $this->resetPage('activePage');
    }

    public function updatingDateMode(): void
    {
        $this->resetPage('activePage');
    }

    public function clearPetugasFilter(): void
    {
        $this->petugasFilter = null;
        $this->resetPage('activePage');
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['card', 'table']) ? $mode : 'card';
    }

    public function getActiveEntriesProperty()
    {
        $q = GuestbookModel::query()
            ->with([
                'idType',
                'visitorLanyard',
                'department',
                'user',
                'scans' => fn($sq) => $sq->orderByDesc('scanned_at'),
            ])
            ->withCount([
                'qrCodes',
                'qrCodes as scanned_qr_count' => fn($cq) => $cq->where('is_scanned', true),
            ])
            ->where('company_id', $this->companyId())
            ->whereNull('jam_out')
            ->whereNull('deleted_at');

        if ($this->petugasFilter) {
            $q->where('petugas_penjaga', $this->petugasFilter);
        }

        if ($this->q !== '') {
            $term = '%' . $this->q . '%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('instansi', 'like', $term)
                    ->orWhere('keperluan', 'like', $term)
                    ->orWhere('petugas_penjaga', 'like', $term);
            });
        }

        if ($this->filter_date) {
            $q->whereDate('date', $this->filter_date);
        }

        if ($this->dateMode === 'terlama') {
            $q->orderBy('created_at', 'asc');
        } else {
            $q->orderByDesc('created_at');
        }

        return $q->paginate($this->perPage, ['*'], 'activePage');
    }

    public function getActiveCountProperty(): int
    {
        return GuestbookModel::where('company_id', $this->companyId())
            ->whereNull('jam_out')
            ->whereNull('deleted_at')
            ->count();
    }

    public function openEdit(int $id): void
    {
        $row = $this->findOwnedOrFail($id);

        $this->editId = $row->guestbook_id;
        $this->edit = [
            'name'            => $row->name,
            'email'           => $row->email,
            'phone_number'    => $row->phone_number,
            'instansi'        => $row->instansi,
            'keperluan'       => $row->keperluan,
            'petugas_penjaga' => $row->petugas_penjaga,
            'visitor_count'   => $row->visitor_count,
            'department_id'   => $row->department_id,
            'user_id'         => $row->user_id,
        ];
        
        if ($row->department_id) {
            $this->loadUsersForEdit($row->department_id);
            $this->edit['user_id'] = $row->user_id; // restore user_id since loadUsersForEdit clears it
        } else {
            $this->users_list = [];
        }
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate($this->rulesEdit());

        $row = $this->findOwnedOrFail($this->editId);

        $oldVisitorCount = $row->visitor_count;
        $newVisitorCount = (int) $this->edit['visitor_count'];

        $row->update([
            'name'            => $this->edit['name'],
            'email'           => $this->edit['email'] ?: null,
            'phone_number'    => $this->edit['phone_number'],
            'instansi'        => $this->edit['instansi'],
            'keperluan'       => $this->edit['keperluan'],
            'petugas_penjaga' => $this->edit['petugas_penjaga'],
            'visitor_count'   => $newVisitorCount,
            'department_id'   => $this->edit['department_id'] === '' ? null : $this->edit['department_id'],
            'user_id'         => $this->edit['user_id'] === '' ? null : $this->edit['user_id'],
        ]);

        $this->showEdit = false;

        if ($oldVisitorCount !== $newVisitorCount) {
            GuestbookQrCode::where('guestbook_id', $row->guestbook_id)->delete();

            $qrTokens = GuestbookQrCode::generateTokenBatch($newVisitorCount);
            $nowTs = now();
            $qrInsertData = [];
            foreach ($qrTokens as $index => $token) {
                $qrInsertData[] = [
                    'guestbook_id'   => $row->guestbook_id,
                    'qr_token'       => $token,
                    'visitor_number' => $index + 1,
                    'is_scanned'     => false,
                    'scanned_at'     => null,
                    'created_at'     => $nowTs,
                ];
            }
            GuestbookQrCode::insert($qrInsertData);
            $this->dispatch('toast', type: 'warning', title: 'Perhatian!', message: 'Jumlah pengunjung berubah. QR Code baru telah dibuat. Harap klik Resend QR.', duration: 7000);
        } else {
            $this->dispatch('toast', type: 'success', title: __('app.toast_updated_title'), message: __('app.toast_updated_message'), duration: 3000);
        }
        $this->dispatch('$refresh');
    }

    public function checkOutNow(int $id): void
    {
        $row = $this->findOwnedOrFail($id);
        
        // Store lanyard ID before updating the guestbook entry
        $lanyardId = $row->visitor_lanyard_id;
        
        $row->update([
            'jam_out'    => Carbon::now()->format('H:i'),
            'qr_status'  => 'completed',
        ]);
        $row->qrCodes()
            ->where('is_scanned', false)
            ->update([
                'is_scanned' => true,
                'scanned_at' => now(),
            ]);

        // Return the lanyard to available status so it can be used again
        if ($lanyardId) {
            $lanyard = \App\Models\VisitorLanyard::find($lanyardId);
            if ($lanyard) {
                $lanyard->update(['status' => 1]);
            }
        }

        $this->dispatch('toast', type: 'success', title: __('app.toast_checkout_title'), message: __('app.toast_checkout_message'), duration: 3000);
        $this->dispatch('$refresh');
    }

    public function resendQr(int $id): void
    {
        $row = $this->findOwnedOrFail($id);

        if (!$row->email || !$row->qr_token) {
            $this->dispatch('toast', type: 'warning', title: 'Tidak dapat dikirim', message: 'Entri ini tidak memiliki email atau token QR.', duration: 4000);
            return;
        }

        try {
            $row->load('qrCodes');
            $officerEmail = Auth::user()?->email ?: config('mail.from.address');
            \Illuminate\Support\Facades\Mail::alwaysFrom($officerEmail, 'Kebun Raya Bogor Receptionist');
            \Illuminate\Support\Facades\Mail::to($row->email)
                ->send(new \App\Mail\GuestbookQrMail($row, $officerEmail));

            $this->dispatch('toast', type: 'success', title: 'QR Dikirim Ulang', message: 'QR code berhasil dikirim ulang ke ' . $row->email . '.', duration: 4000);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GuestbookQrMail resend failed: ' . $e->getMessage(), [
                'exception' => $e,
                'guestbook_id' => $id,
            ]);
            $this->dispatch('toast', type: 'error', title: 'Gagal Kirim', message: 'Gagal mengirim ulang QR code. Periksa konfigurasi mail.', duration: 5000);
        }
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook-status', [
            'activeEntries' => $this->activeEntries,
            'activeCount'   => $this->activeCount,
        ]);
    }
}
