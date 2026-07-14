<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Delivery;
use App\Models\Department;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Documents & Packages — Status')]
class DocPackStatus extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';

    // Filters
    public string $q = '';
    public ?string $selectedDate = null;
    public string $dateMode = 'semua';
    public string $type = 'all';
    public ?int $departmentId = null;
    public ?int $userId = null;
    public string $departmentQ = '';
    public string $userQ = '';

    // Tabs
    public string $activeTab = 'pending';

    // Pagination per box
    public int $perPending = 6;

    // Mobile filter modal
    public bool $showFilterModal = false;

    // Edit modal
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $edit = [
        'item_name' => null,
        'nama_pengirim' => null,
        'nama_penerima' => null,
    ];

    protected $rules = [
        'edit.item_name' => 'nullable|string|max:255',
        'edit.nama_pengirim' => 'nullable|string|max:255',
        'edit.nama_penerima' => 'nullable|string|max:255',
    ];

    public function updated($name): void
    {
        if ($name === 'departmentId') {
            $this->userId = null;
        }

        if (in_array($name, ['q', 'selectedDate', 'dateMode', 'type', 'departmentId', 'userId', 'departmentQ', 'userQ'], true)) {
            $this->resetPage('pendingPage');
        }
    }

    // ───────── Tabs ─────────
    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['pending'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->resetPage('pendingPage');
    }

    // ───────── Mobile Filter Modal ─────────
    public function openFilterModal(): void
    {
        $this->showFilterModal = true;
    }

    public function closeFilterModal(): void
    {
        $this->showFilterModal = false;
    }

    private function base()
    {
        return Delivery::query()->byCompany(Auth::user()->company_id ?? null);
    }

    private function applySharedFilters($q)
    {
        if ($this->type !== 'all') {
            $q->where('type', $this->type);
        }

        if ($this->selectedDate) {
            $q->whereDate('created_at', $this->selectedDate);
        }

        if ($this->departmentId) {
            $q->where('department_id', $this->departmentId);
        }

        if (trim($this->departmentQ) !== '') {
            $deptIds = Department::query()
                ->where('company_id', Auth::user()->company_id ?? null)
                ->whereNull('deleted_at')
                ->where('department_name', 'like', '%' . trim($this->departmentQ) . '%')
                ->pluck('department_id');

            $deptIds->isNotEmpty() ? $q->whereIn('department_id', $deptIds) : $q->whereRaw('0=1');
        }

        if ($this->userId) {
            $q->where('receptionist_id', $this->userId);
        }

        if (trim($this->userQ) !== '') {
            $userIds = UserModel::query()
                ->where('company_id', Auth::user()->company_id ?? null)
                ->whereNull('deleted_at')
                ->when($this->departmentId, fn($qq) => $qq->where('department_id', $this->departmentId))
                ->where('full_name', 'like', '%' . trim($this->userQ) . '%')
                ->pluck('user_id');

            $userIds->isNotEmpty() ? $q->whereIn('receptionist_id', $userIds) : $q->whereRaw('0=1');
        }

        if (trim($this->q) !== '') {
            $term = '%' . trim($this->q) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('item_name', 'like', $term)
                    ->orWhere('nama_pengirim', 'like', $term)
                    ->orWhere('nama_penerima', 'like', $term)
                    ->orWhereHas('receptionist', function ($u) use ($term) {
                        $u->where('full_name', 'like', $term);
                    });
            });
        }

        if ($this->dateMode === 'terbaru')
            $q->latest('created_at');
        elseif ($this->dateMode === 'terlama')
            $q->oldest('created_at');

        return $q;
    }

    public function getPendingProperty()
    {
        $q = $this->base()->where('status', 'pending');
        $this->applySharedFilters($q)->latest('created_at');

        return $q->with('receptionist')->paginate($this->perPending, pageName: 'pendingPage');
    }

    public function openEdit(int $id): void
    {
        $row = $this->base()->findOrFail($id);
        $this->editId = $row->delivery_id ?? $row->id ?? $id;
        $this->edit = [
            'item_name' => $row->item_name,
            'nama_pengirim' => $row->nama_pengirim,
            'nama_penerima' => $row->nama_penerima,
        ];
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        if (!$this->editId)
            return;

        $this->validate();

        $row = $this->base()->findOrFail($this->editId);
        $row->fill([
            'item_name' => $this->edit['item_name'],
            'nama_pengirim' => $this->edit['nama_pengirim'],
            'nama_penerima' => $this->edit['nama_penerima'],
        ])->save();

        $this->showEdit = false;
        $this->editId = null;
        $this->resetPage('pendingPage');
        $this->dispatch('toast', type: 'success', title: 'Saved', message: 'Information successfully saved.', duration: 3000);
    }



    private function getDirectionFor(Delivery $row): string
    {
        $dir = (string) $row->direction;
        if ($dir === 'deliver' || $dir === 'taken') {
            return $dir;
        }
        return $this->inferDirection($row);
    }

    private function inferDirection(Delivery $row): string
    {
        $companyId = Auth::user()->company_id ?? null;

        $pengirim = trim((string) $row->nama_pengirim);
        $penerima = trim((string) $row->nama_penerima);

        $isPengirimUser = false;
        $isPenerimaUser = false;

        if ($pengirim !== '') {
            $isPengirimUser = UserModel::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower($pengirim)])
                ->exists();
        }
        if ($penerima !== '') {
            $isPenerimaUser = UserModel::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower($penerima)])
                ->exists();
        }

        if ($isPenerimaUser && !$isPengirimUser)
            return 'taken';
        if ($isPengirimUser && !$isPenerimaUser)
            return 'deliver';

        return ($row->type === 'document') ? 'deliver' : 'taken';
    }

    public function markDone(int $id): void
    {
        $row = $this->base()->where('status', 'pending')->findOrFail($id);
        $dir = $this->getDirectionFor($row);

        $when = now();

        if ($dir === 'deliver') {
            $row->status = 'delivered';
            $row->pengiriman = $when;
        } else {
            $row->status = 'taken';
            $row->pengambilan = $when;
        }

        $row->save();

        $this->resetPage('pendingPage');
        $this->dispatch('toast', type: 'success', title: 'Done', message: 'Item successfully marked as done.', duration: 3000);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        $departments = Department::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('department_name')
            ->get(['department_id', 'department_name']);

        $users = UserModel::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['user_id', 'full_name', 'department_id'])
            ->unique('user_id');

        return view('livewire.pages.receptionist.docpackstatus', [
            'pending' => $this->pending,
            'departments' => $departments,
            'users' => $users
        ]);
    }
}