<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Delivery; // gunakan tabel deliveries
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\SecurityMonitoringService;

#[Layout('layouts.receptionist')]
#[Title('Deliveries')]
class Documents extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';
    protected $queryString = ['q', 'filter_date'];

    /**
     * Menjaga kompatibilitas dengan form/blade lama:
     * - document_name  -> item_name (DB)
     * - penyimpanan    -> storage_id (DB)
     */
    public $document_name, $nama_pengirim, $nama_penerima;
    public $type = 'document';         // 'document' | 'package'
    public $penyimpanan;               // storage_id
    public $pengambilan_date, $pengambilan_time;
    public $status = 'pending';        // 'pending' | 'stored' | 'delivered' | 'taken'
    public $filter_date;
    public $q = '';

    public bool $showEdit = false;
    public $editId = null;

    // Delete Modal State
    public ?int $deletingId = null;
    public string $deletingSummary = '';
    public bool $showDeleteModal = false;
    public bool $isForceDelete = false;

    public $edit = [
        'document_name'    => null,
        'nama_pengirim'    => null,
        'nama_penerima'    => null,
        'type'             => 'document',
        'penyimpanan'      => null,
        'pengambilan_date' => null,
        'pengambilan_time' => null,
        'pengiriman'       => null,
        'status'           => 'pending',
    ];

    private function companyId()
    {
        return optional(Auth::user())->company_id;
    }

    private function findOwnedOrFail(int $id): Delivery
    {
        $row = Delivery::whereKey($id)
            ->where('company_id', $this->companyId())
            ->first();

        if (!$row) {
            throw new ModelNotFoundException('Delivery not found or not owned.');
        }
        return $row;
    }

    private function combineDateTime(?string $date, ?string $time): ?Carbon
    {
        if (empty($date) && empty($time)) return null;
        if (empty($date)) return null;
        $time = $time ?: '00:00';
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", config('app.timezone', 'Asia/Jakarta'));
    }

    protected function rules(): array
    {
        return [
            'document_name'    => ['required', 'string', 'max:255'],                 // -> item_name
            'nama_pengirim'    => ['nullable', 'string', 'max:255'],
            'nama_penerima'    => ['nullable', 'string', 'max:255'],
            'type'             => ['required', 'in:document,package'],
            'penyimpanan'      => ['nullable', 'integer', 'exists:storages,storage_id'], // -> storage_id
            'pengambilan_date' => ['nullable', 'date'],
            'pengambilan_time' => ['nullable', 'date_format:H:i'],
            'status'           => ['required', 'in:pending,stored,delivered,taken'],
        ];
    }

    protected function rulesEdit(): array
    {
        return [
            'edit.document_name'    => ['required', 'string', 'max:255'],
            'edit.nama_pengirim'    => ['nullable', 'string', 'max:255'],
            'edit.nama_penerima'    => ['nullable', 'string', 'max:255'],
            'edit.type'             => ['required', 'in:document,package'],
            'edit.penyimpanan'      => ['nullable', 'integer', 'exists:storages,storage_id'],
            'edit.pengambilan_date' => ['nullable', 'date'],
            'edit.pengambilan_time' => ['nullable', 'date_format:H:i'],
            'edit.pengiriman'       => ['nullable', 'date'],
            'edit.status'           => ['required', 'in:pending,stored,delivered,taken'],
        ];
    }

    public function updatedQ(): void { $this->resetPage(); }
    public function updatedFilterDate(): void { $this->resetPage(); }

    public function save(): void
    {
        $data = $this->validate();

        SecurityMonitoringService::logFormSubmit('documents', $data);

        $pengambilan = $this->combineDateTime($data['pengambilan_date'] ?? null, $data['pengambilan_time'] ?? null);
        $statusInput = $data['status'] ?? 'pending';

        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));
        $pengiriman = ($statusInput === 'delivered') ? $now : null;

        $storageId = isset($data['penyimpanan']) && $data['penyimpanan'] !== ''
            ? (int) $data['penyimpanan']
            : null;

        $payload = [
            'company_id'      => $this->companyId(),
            'receptionist_id' => Auth::id(),
            'item_name'       => $data['document_name'],
            'nama_pengirim'   => $data['nama_pengirim'] ?? null,
            'nama_penerima'   => $data['nama_penerima'] ?? null,
            'type'            => $data['type'],
            'storage_id'      => $storageId,
            'pengambilan'     => $pengambilan,
            'pengiriman'      => $pengiriman,
            'status'          => $statusInput,
        ];

        Delivery::create($payload);

        $this->resetForm();

        $msg = match ($statusInput) {
            'delivered' => 'Item langsung masuk Riwayat (Delivered).',
            'taken'     => 'Item disimpan ke kotak Taken.',
            'stored'    => 'Item disimpan ke kotak Stored.',
            default     => 'Item disimpan ke kotak Pending.',
        };

        $this->dispatch('toast', type: 'success', title: __('app.success'), message: 'Item berhasil ditambahkan. ' . $msg, duration: 3000);
    }

    public function openEdit(int $id): void
    {
        $r = $this->findOwnedOrFail($id);
        $this->editId = $r->getKey();

        $this->edit = [
            'document_name'    => $r->item_name,
            'nama_pengirim'    => $r->nama_pengirim,
            'nama_penerima'    => $r->nama_penerima,
            'type'             => $r->type,
            'penyimpanan'      => $r->storage_id,
            'pengambilan_date' => optional($r->pengambilan)?->format('Y-m-d'),
            'pengambilan_time' => optional($r->pengambilan)?->format('H:i'),
            'pengiriman'       => optional($r->pengiriman)?->format('Y-m-d\TH:i'),
            'status'           => $r->status,
        ];

        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        $this->validate($this->rulesEdit());
        $row = $this->findOwnedOrFail($this->editId);

        $pengambilan = $this->combineDateTime(
            $this->edit['pengambilan_date'] ?? null,
            $this->edit['pengambilan_time'] ?? null
        );

        $desiredStatus = $this->edit['status'] ?? 'pending';

        if ($desiredStatus !== 'delivered') {
            $pengiriman = null;
            $status = $desiredStatus;
        } else {
            $pengiriman = !empty($this->edit['pengiriman'])
                ? Carbon::parse($this->edit['pengiriman'], config('app.timezone', 'Asia/Jakarta'))
                : Carbon::now(config('app.timezone', 'Asia/Jakarta'));
            $status = 'delivered';
        }

        $row->update([
            'item_name'     => $this->edit['document_name'],
            'nama_pengirim' => $this->edit['nama_pengirim'],
            'nama_penerima' => $this->edit['nama_penerima'],
            'type'          => $this->edit['type'],
            'storage_id'    => ($this->edit['penyimpanan'] !== '' ? (int)$this->edit['penyimpanan'] : null),
            'pengambilan'   => $pengambilan,
            'pengiriman'    => $pengiriman,
            'status'        => $status,
        ]);

        $this->showEdit = false;

        $msg = 'Perubahan disimpan. Posisi kartu diperbarui sesuai status.';
        $this->dispatch('toast', type: 'success', title: __('app.success'), message: 'Item berhasil diperbarui. ' . $msg, duration: 3000);
        $this->dispatch('$refresh');
    }

    public function confirmDelete(int $id, string $summary, bool $force = false): void
    {
        $this->deletingId = $id;
        $this->deletingSummary = $summary;
        $this->isForceDelete = $force;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (!$this->deletingId) {
            return;
        }

        $this->deleteAction($this->deletingId);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->isForceDelete = false;
    }

    /** Soft delete only */
    private function deleteAction(int $id): void
    {
        $this->findOwnedOrFail($id)->delete(); // soft delete
        $this->dispatch('toast', type: 'success', title: __('app.success'), message: 'Item dihapus (soft delete).', duration: 3000);
        $this->dispatch('$refresh');
    }

    private function resetForm(): void
    {
        $this->reset([
            'document_name',
            'nama_pengirim',
            'nama_penerima',
            'type',
            'penyimpanan',
            'pengambilan_date',
            'pengambilan_time',
            'status',
            'editId',
            'showEdit',
            'q',
            'filter_date',
        ]);

        $this->type = 'document';
        $this->status = 'pending';
    }

    // ======== Lists / Query (otomatis exclude soft-deleted) ========

    public function getPendingListProperty()
    {
        return Delivery::byCompany($this->companyId())
            ->where('status', 'pending')
            ->whereNull('pengiriman')
            ->latest('created_at')
            ->take(50)
            ->get();
    }

    public function getStoredListProperty()
    {
        return Delivery::byCompany($this->companyId())
            ->where('status', 'stored')
            ->whereNull('pengiriman')
            ->latest('created_at')
            ->take(50)
            ->get();
    }

    public function getTakenListProperty()
    {
        return Delivery::byCompany($this->companyId())
            ->where('status', 'taken')
            ->whereNull('pengiriman')
            ->latest('created_at')
            ->take(50)
            ->get();
    }

    public function getEntriesProperty()
    {
        $q = Delivery::byCompany($this->companyId())
            ->whereNotNull('pengiriman'); // riwayat (delivered)

        if ($this->filter_date) {
            $q->whereDate('pengambilan', $this->filter_date);
        }

        $q->search($this->q);

        return $q->latest('pengiriman')->paginate(10);
    }

    public function getServerClockProperty(): string
    {
        return Carbon::now(config('app.timezone', 'Asia/Jakarta'))->format('H:i:s');
    }

    public function render()
    {
        // tetap gunakan blade lama jika struktur UI kamu belum diubah
        return view('livewire.pages.receptionist.documents', [
            'pendingList' => $this->pendingList,
            'storedList'  => $this->storedList,
            'takenList'   => $this->takenList,
            'entries'     => $this->entries,
        ]);
    }
}
