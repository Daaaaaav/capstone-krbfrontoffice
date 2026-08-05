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
    ];

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
        ];
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
        ]);

        $this->showEdit = false;

        if ($oldVisitorCount !== $newVisitorCount) {
            GuestbookQrCode::where('guestbook_id', $row->guestbook_id)->delete();

            $qrTokens = GuestbookQrCode::generateTokenBatch($newVisitorCount);
            foreach ($qrTokens as $index => $token) {
                GuestbookQrCode::create([
                    'guestbook_id'   => $row->guestbook_id,
                    'qr_token'       => $token,
                    'visitor_number' => $index + 1,
                ]);
            }
            $this->dispatch('toast', type: 'warning', title: 'Perhatian!', message: 'Jumlah pengunjung berubah. QR Code baru telah dibuat. Harap klik Resend QR.', duration: 7000);
        } else {
            $this->dispatch('toast', type: 'success', title: __('app.toast_updated_title'), message: __('app.toast_updated_message'), duration: 3000);
        }
        $this->dispatch('$refresh');
    }

    public function checkOutNow(int $id): void
    {
        $row = $this->findOwnedOrFail($id);
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
            \Illuminate\Support\Facades\Mail::to($row->email)
                ->send(new \App\Mail\GuestbookQrMail($row));

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
