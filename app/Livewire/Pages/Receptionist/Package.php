<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Delivery as DeliveryModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Services\SecurityMonitoringService;

#[Layout('layouts.receptionist')]
#[Title('Package')]
class Package extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editId = null;             // delivery_id
    public ?string $createdAtDisplay = null;

    // Delete Modal State
    public ?int $deletingId = null;
    public string $deletingSummary = '';
    public bool $showDeleteModal = false;
    public bool $isForceDelete = false;

    /**
     * form mapping:
     * - package_name  -> deliveries.item_name
     * - penyimpanan   -> deliveries.storage_id
     * - pengambilan   -> deliveries.pengambilan (datetime)
     */
    public array $form = [
        'package_name' => '',
        'nama_pengirim' => '',
        'nama_penerima' => '',
        'penyimpanan' => null,  // storage_id (nullable)
        'pengambilan' => null,  // Y-m-d\TH:i (nullable)
    ];

    protected function rules(): array
    {
        return [
            'form.package_name' => ['required', 'string', 'max:255'],
            'form.nama_pengirim' => ['required', 'string', 'max:255'],
            'form.nama_penerima' => ['required', 'string', 'max:255'],
            'form.penyimpanan' => ['nullable', 'integer', 'exists:storages,storage_id'],
            'form.pengambilan' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editId = null;
        $this->createdAtDisplay = now()->format('d M Y, H:i');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $pkg = DeliveryModel::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('type', 'package')
            ->where('delivery_id', $id)
            ->firstOrFail();

        $this->editId = $pkg->delivery_id;

        $this->form = [
            'package_name' => $pkg->item_name,
            'nama_pengirim' => $pkg->nama_pengirim,
            'nama_penerima' => $pkg->nama_penerima,
            'penyimpanan' => $pkg->storage_id,
            // jika model belum di-cast datetime, fallback handle string
            'pengambilan' => $pkg->pengambilan
                ? ($pkg->pengambilan instanceof Carbon
                    ? $pkg->pengambilan->format('Y-m-d\TH:i')
                    : Carbon::parse($pkg->pengambilan)->format('Y-m-d\TH:i'))
                : null,
        ];

        $this->createdAtDisplay = $pkg->created_at
            ? ($pkg->created_at instanceof Carbon
                ? $pkg->created_at->format('d M Y, H:i')
                : Carbon::parse($pkg->created_at)->format('d M Y, H:i'))
            : null;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        SecurityMonitoringService::logFormSubmit('package', $this->form);

        $pengambilan = $this->form['pengambilan']
            ? Carbon::createFromFormat('Y-m-d\TH:i', $this->form['pengambilan'], config('app.timezone', 'Asia/Jakarta'))
            : null;

        // Aturan status sederhana: ada pengambilan -> taken, belum -> stored
        $status = $pengambilan ? 'taken' : 'stored';

        $payload = [
            'company_id' => Auth::user()->company_id,
            'receptionist_id' => Auth::id(),
            'item_name' => $this->form['package_name'],
            'nama_pengirim' => $this->form['nama_pengirim'],
            'nama_penerima' => $this->form['nama_penerima'],
            'storage_id' => $this->form['penyimpanan'] ?: null,
            'pengambilan' => $pengambilan,
            'pengiriman' => null,       // tidak dipakai di halaman ini
            'status' => $status,    // 'stored' | 'taken'
            'type' => 'package',  // paksa type package (default tabel = document)
        ];

        if ($this->editId) {
            DeliveryModel::query()
                ->where('company_id', $payload['company_id'])
                ->where('type', 'package')
                ->where('delivery_id', $this->editId)
                ->update($payload);
        } else {
            DeliveryModel::create($payload);
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', title: $this->editId ? 'Diubah' : 'Ditambah', message: 'Paket disimpan.', duration: 3000);
        $this->resetForm();
        $this->resetPage();
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

    /** Soft delete ONLY */
    private function deleteAction(int $id): void
    {
        $pkg = DeliveryModel::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('type', 'package')
            ->where('delivery_id', $id)
            ->firstOrFail();

        $pkg->delete(); // soft delete

        $this->dispatch('toast', type: 'success', title: 'Dihapus', message: 'Paket dihapus (soft delete).', duration: 3000);
        $this->resetPage();
    }

    public function markDone(int $id): void
    {
        DeliveryModel::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('type', 'package')
            ->where('delivery_id', $id)
            ->update([
                'status' => 'taken',
                'pengambilan' => now(config('app.timezone', 'Asia/Jakarta')),
            ]);

        $this->dispatch('toast', message: 'Moved to Done.');
    }

    public function markStored(int $id): void
    {
        DeliveryModel::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('type', 'package')
            ->where('delivery_id', $id)
            ->update([
                'status' => 'stored',
                'pengambilan' => null,
            ]);

        $this->dispatch('toast', message: 'Moved back to On-going.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    private function resetForm(): void
    {
        $this->form = [
            'package_name' => '',
            'nama_pengirim' => '',
            'nama_penerima' => '',
            'penyimpanan' => null,
            'pengambilan' => null,
        ];
        $this->createdAtDisplay = null;
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $ongoing = DeliveryModel::query()
            ->with('receptionist') // pastikan relasi ada di model
            ->where('company_id', $companyId)
            ->where('type', 'package')
            ->where('status', 'stored')
            ->select([
                'deliveries.*',
                'item_name as package_name',   // alias untuk Blade lama
                'storage_id as penyimpanan',
            ])
            ->latest('created_at')
            ->paginate(8, pageName: 'ongoing');

        $done = DeliveryModel::query()
            ->with('receptionist')
            ->where('company_id', $companyId)
            ->where('type', 'package')
            ->where('status', 'taken')
            ->select([
                'deliveries.*',
                'item_name as package_name',
                'storage_id as penyimpanan',
            ])
            ->latest('pengambilan')
            ->paginate(8, pageName: 'done');

        return view('livewire.pages.receptionist.package', compact('ongoing', 'done'));
    }
}
