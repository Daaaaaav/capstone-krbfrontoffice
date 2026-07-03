<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Guestbook as GuestbookModel;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('GuestBook History')]
class GuestbookHistory extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';

    // --- Pagination Controls ---
    // Set default data per page to 12
    public int $perLatest = 6; // for "Kunjungan Terbaru"
    public int $perEntries = 6; // for "Riwayat Kunjungan"

    // Shared property for the dropdown control in Blade
    public int $selectedPerPage = 6; // Default selection

    // Filters for history box
    public ?string $filter_date = null; // YYYY-MM-DD (used in Blade as wire:model="filter_date")
    public ?string $selectedDate = null;
    public string $q = '';

    // Sorting (follow BookingsApproval pattern)
    public string $dateMode = 'semua'; // semua | terbaru | terlama

    // Include soft-deleted rows in history list
    public bool $withTrashed = false;

    // Filter by security / receptionist officer in sidebar
    public ?string $petugasFilter = null;

    // Edit modal state
    public bool $showEdit = false;
    public ?int $editId = null;

    // Delete modal state
    public bool $showDeleteModal = false;
    public ?int $deleteId = null;
    public bool $isForceDelete = false;
    public string $deletingSummary = '';

    // Active tab: entries | latest
    public string $activeTab = 'entries';

    public array $edit = [
        'date' => null,
        'jam_in' => null,
        'jam_out' => null,
        'name' => null,
        'phone_number' => null,
        'instansi' => null,
        'keperluan' => null,
        'petugas_penjaga' => null,
        'visitor_count' => null,
    ];

    protected function rulesEdit(): array
    {
        return [
            'edit.date' => ['required', 'date'],
            'edit.jam_in' => ['required', 'date_format:H:i'],
            'edit.jam_out' => ['nullable', 'date_format:H:i'],
            'edit.name' => ['required', 'string', 'max:255'],
            'edit.phone_number' => ['nullable', 'string', 'max:50'],
            'edit.instansi' => ['nullable', 'string', 'max:255'],
            'edit.keperluan' => ['nullable', 'string', 'max:255'],
            'edit.petugas_penjaga' => ['required', 'string', 'max:255'],
            'edit.visitor_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** ==== Normalizers for edit ==== */
    public function updatedEditDate($v): void
    {
        $this->edit['date'] = $this->normalizeDate($v);
    }

    public function updatedEditJamIn($v): void
    {
        $this->edit['jam_in'] = $this->normalizeTime($v);
    }

    public function updatedEditJamOut($v): void
    {
        $this->edit['jam_out'] = $this->normalizeTime($v, true);
    }

    private function normalizeDate($v): ?string
    {
        if (!$v) {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $v))->format('Y-m-d');
        } catch (\Throwable) {
            return $v;
        }
    }

    private function normalizeTime($v, bool $nullable = false): ?string
    {
        if ($nullable && $v === '') {
            return null;
        }
        if (!$v) {
            return null;
        }

        try {
            return Carbon::parse($v)->format('H:i');
        } catch (\Throwable) {
            return $v;
        }
    }

    private function companyId(): ?int
    {
        return Auth::user()?->company_id;
    }

    /**
     * Pastikan data milik company yang sama; tidak ikut yang sudah di-trashed kecuali withTrashed()
     */
    private function findOwnedOrFail(int $id): GuestbookModel
    {
        return GuestbookModel::withTrashed()
            ->whereKey($id)
            ->where('company_id', $this->companyId())
            ->firstOrFail();
    }

    /** ==== Reset the correct paginator when filters / per-page change ==== */
    public function updatingQ(): void
    {
        $this->resetPage('entriesPage');
    }

    public function updatingFilterDate(): void
    {
        $this->resetPage('entriesPage');
    }

    public function updatedWithTrashed(): void
    {
        $this->resetPage('entriesPage');
    }

    public function updatedDateMode(): void
    {
        $this->resetPage('entriesPage');
    }

    // Bind shared property to the list-specific pagination properties
    public function updatedSelectedPerPage($value): void
    {
        $this->perLatest = (int) $value;
        $this->perEntries = (int) $value;
        $this->resetPage(); // Reset both paginations when size changes
    }

    /** Tabs switcher (Riwayat / Terbaru) */
    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['entries', 'latest'], true)) {
            return;
        }

        $this->activeTab = $tab;

        // Reset the page of the tab you are switching TO
        if ($tab === 'entries') {
            $this->resetPage('entriesPage');
        } else {
            $this->resetPage('latestPage');
        }
    }

    /** Sorting helper like BookingsApproval */
    private function sortingDirection(): string
    {
        return $this->dateMode === 'terlama' ? 'ASC' : 'DESC';
    }

    /** ==== Computed props with pagination (using Livewire v3 syntax) ==== */

    /**
     * Kunjungan hari ini yang BELUM keluar, paginated (independent page name)
     */
    public function getLatestProperty()
    {
        $q = GuestbookModel::where('company_id', $this->companyId())
            ->whereDate('date', now()->toDateString())
            ->whereNull('jam_out')
            ->whereNull('deleted_at'); // always exclude soft-deleted from active visitors

        if ($this->petugasFilter) {
            $q->where('petugas_penjaga', $this->petugasFilter);
        }

        // Newest first
        $q->orderByDesc('created_at');

        return $q->paginate($this->perLatest, ['*'], 'latestPage');
    }

    /**
     * Riwayat kunjungan (sudah keluar), with soft delete toggle, paginated (independent page name)
     */
    public function getEntriesProperty()
    {
        $q = GuestbookModel::query()
            ->where('company_id', $this->companyId())
            ->whereNotNull('jam_out');

        // withTrashed toggle
        if ($this->withTrashed) {
            $q->withTrashed();
        } else {
            $q->whereNull('deleted_at');
        }

        if ($this->petugasFilter) {
            $q->where('petugas_penjaga', $this->petugasFilter);
        }

        if ($this->filter_date) {
            $q->whereDate('date', $this->filter_date);
        }

        if ($this->q !== '') {
            $term = '%' . $this->q . '%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term)
                    ->orWhere('instansi', 'like', $term)
                    ->orWhere('keperluan', 'like', $term)
                    ->orWhere('petugas_penjaga', 'like', $term);
            });
        }

        // Sort by date+jam_in / jam_out similar to BookingsApproval (dateMode)
        $dir = $this->sortingDirection();

        // MySQL/MariaDB only: Order by a calculated datetime based on jam_out (if present) or jam_in
        // Note: For other DBs (Postgres/SQLite) this raw query must be adapted.
        $dtExpr = "COALESCE(
            CASE WHEN `jam_out` REGEXP '^[0-9]{2}:' THEN CONCAT(`date`, ' ', `jam_out`) ELSE CONCAT(`date`, ' ', `jam_in`) END,
            CONCAT(`date`, ' 00:00:00')
        )";

        $q->orderByRaw("$dtExpr $dir")
            ->orderByDesc('created_at');

        return $q->paginate($this->perEntries, ['*'], 'entriesPage');
    }

    /** ====== Actions for history (edit/delete/restore) ====== */
    public function openEdit(int $id): void
    {
        $row = $this->findOwnedOrFail($id);

        $this->editId = $row->getKey();
        $this->edit = [
            'date' => $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null,
            'jam_in' => $row->jam_in ? Carbon::parse($row->jam_in)->format('H:i') : null,
            'jam_out' => $row->jam_out ? Carbon::parse($row->jam_out)->format('H:i') : null,
            'name' => $row->name,
            'phone_number' => $row->phone_number,
            'instansi' => $row->instansi,
            'keperluan' => $row->keperluan,
            'petugas_penjaga' => $row->petugas_penjaga,
            'visitor_count' => $row->visitor_count ?? 0,
        ];

        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        $this->validate($this->rulesEdit());

        $row = $this->findOwnedOrFail($this->editId);

        $row->update([
            'date' => $this->edit['date'],
            'jam_in' => $this->edit['jam_in'],
            'jam_out' => $this->edit['jam_out'] ?: null,
            'name' => $this->edit['name'],
            'phone_number' => $this->edit['phone_number'],
            'instansi' => $this->edit['instansi'],
            'keperluan' => $this->edit['keperluan'],
            'petugas_penjaga' => $this->edit['petugas_penjaga'],
            'visitor_count' => $this->edit['visitor_count'] ?? 0,
        ]);

        $this->showEdit = false;

        $this->dispatch(
            'toast',
            type: 'success',
            title: __('app.toast_updated_title'),
            message: __('app.toast_updated_message'),
            duration: 3000
        );

        $this->dispatch('$refresh');
    }

    /** Keluar sekarang (set jam_out real-time) */
    public function setJamKeluarNow(int $id): void
    {
        $row = $this->findOwnedOrFail($id);

        $row->update([
            'jam_out'    => Carbon::now()->format('H:i'),
            'qr_status'  => 'completed',
        ]);

        $this->dispatch(
            'toast',
            type: 'success',
            title: __('app.toast_checkout_title'),
            message: __('app.toast_checkout_message'),
            duration: 2500
        );

        // Explicitly refresh only the relevant list (latest will move it to entries)
        $this->dispatch('$refresh');
    }

    /** SOFT DELETE */
    public function delete(int $id): void
    {
        $row = $this->findOwnedOrFail($id);
        $row->delete();

        // If page becomes empty after deletion, go back one page
        $entries = $this->entries;
        if ($entries->isEmpty() && $entries->currentPage() > 1) {
            $this->setPage($entries->currentPage() - 1, 'entriesPage');
        }

        $this->dispatch(
            'toast',
            type: 'success',
            title: __('app.toast_deleted_title'),
            message: __('app.toast_deleted_message'),
            duration: 3000
        );

        $this->dispatch('$refresh');
    }

    public function restore(int $id): void
    {
        $row = GuestbookModel::onlyTrashed()
            ->where('company_id', $this->companyId())
            ->whereKey($id)
            ->first();

        if ($row) {
            $row->restore();

            $this->dispatch(
                'toast',
                type: 'success',
                title: __('app.toast_restored_title'),
                message: __('app.toast_restored_message'),
                duration: 2500
            );

            $this->dispatch('$refresh');
        }
    }

    public function destroyForever(int $id): void
    {
        $row = GuestbookModel::onlyTrashed()
            ->where('company_id', $this->companyId())
            ->whereKey($id)
            ->first();

        if ($row) {
            $row->forceDelete();

            $this->dispatch(
                'toast',
                type: 'success',
                title: __('app.toast_perm_deleted_title'),
                message: __('app.toast_perm_deleted_message'),
                duration: 2500
            );

            $this->dispatch('$refresh');
        }
    }

    public function confirmDelete(int $id, string $name, bool $forceDelete = false): void
    {
        $this->deleteId = $id;
        $this->isForceDelete = $forceDelete;
        $this->deletingSummary = $name;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        if ($this->isForceDelete) {
            $this->destroyForever($this->deleteId);
        } else {
            $this->delete($this->deleteId);
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
        $this->deletingSummary = '';
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->resetValidation();
    }

    /**
     * Get unique officers/security guards who have recorded entries
     */
    public function getPetugasOptionsProperty(): array
    {
        return GuestbookModel::query()
            ->where('company_id', $this->companyId())
            ->whereNotNull('petugas_penjaga')
            ->where('petugas_penjaga', '!=', '')
            ->select('petugas_penjaga')
            ->distinct()
            ->orderBy('petugas_penjaga')
            ->pluck('petugas_penjaga')
            ->toArray();
    }

    public function selectPetugas(?string $petugas = null): void
    {
        $this->petugasFilter = $petugas ?: null;
        $this->resetPage('entriesPage');
        $this->resetPage('latestPage');
    }

    public function clearPetugasFilter(): void
    {
        $this->selectPetugas(null);
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbookhistory', [
            'latest' => $this->latest,
            'entries' => $this->entries,
            'petugasOptions' => $this->petugasOptions,
        ]);
    }
}