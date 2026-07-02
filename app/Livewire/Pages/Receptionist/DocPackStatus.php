<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Delivery;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Documents & Packages — Status')]
class DocPackStatus extends Component
{
    use WithPagination;
    use HasViewMode;
    use WithFileUploads;

    protected string $paginationTheme = 'tailwind';

    // Filters
    public string $q = '';
    public ?string $selectedDate = null;
    public string $dateMode = 'semua';
    public string $type = 'all';
    public string $filterSender = '';
    public string $filterReceiver = '';
    public ?int $userId = null;
    public string $userQ = '';

    // Tabs
    public string $activeTab = 'pending';

    // Pagination per box
    public int $perPending = 6;
    public int $perStored = 6;

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
    public $editPhoto = null;
    public ?string $editCurrentImage = null;

    protected $rules = [
        'edit.item_name' => 'nullable|string|max:255',
        'edit.nama_pengirim' => 'nullable|string|max:255',
        'edit.nama_penerima' => 'nullable|string|max:255',
        'editPhoto' => 'nullable|image|max:2048',
    ];

    public function updated($name): void
    {
        if (in_array($name, ['q', 'selectedDate', 'dateMode', 'type', 'filterSender', 'filterReceiver', 'userId', 'userQ'], true)) {
            $this->resetPage('pendingPage');
            $this->resetPage('storedPage');
        }
    }

    // ───────── Tabs ─────────
    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['pending', 'stored'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->resetPage('pendingPage');
        $this->resetPage('storedPage');
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

        if (trim($this->filterSender) !== '') {
            $q->where('nama_pengirim', 'like', '%' . trim($this->filterSender) . '%');
        }

        if (trim($this->filterReceiver) !== '') {
            $q->where('nama_penerima', 'like', '%' . trim($this->filterReceiver) . '%');
        }

        if ($this->userId) {
            $selectedUser = UserModel::find($this->userId);
            $selectedName = $selectedUser ? $selectedUser->full_name : null;

            $q->where(function ($qq) use ($selectedName) {
                $qq->where('receptionist_id', $this->userId);
                if ($selectedName) {
                    $qq->orWhere('nama_pengirim', $selectedName)
                       ->orWhere('nama_penerima', $selectedName);
                }
            });
        }

        if (trim($this->userQ) !== '') {
            $userIds = UserModel::query()
                ->where('company_id', Auth::user()->company_id ?? null)
                ->whereNull('deleted_at')
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

    public function getStoredProperty()
    {
        $q = $this->base()->where('status', 'stored');
        $this->applySharedFilters($q)->latest('created_at');

        return $q->with('receptionist')->paginate($this->perStored, pageName: 'storedPage');
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
        $this->editCurrentImage = $row->image;
        $this->editPhoto = null;
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        if (!$this->editId)
            return;

        $this->validate();

        $row = $this->base()->findOrFail($this->editId);

        $data = [
            'item_name' => $this->edit['item_name'],
            'nama_pengirim' => $this->edit['nama_pengirim'],
            'nama_penerima' => $this->edit['nama_penerima'],
        ];

        if ($this->editPhoto) {
            // Delete old image if exists
            if ($row->image && Storage::disk('public')->exists($row->image)) {
                Storage::disk('public')->delete($row->image);
            }
            $ext = strtolower($this->editPhoto->getClientOriginalExtension() ?: 'png');
            $filename = 'delivery_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $data['image'] = $this->editPhoto->storeAs('images/deliveries', $filename, 'public');
        }

        $row->fill($data)->save();

        $this->showEdit = false;
        $this->editId = null;
        $this->editPhoto = null;
        $this->editCurrentImage = null;
        $this->resetPage('pendingPage');
        $this->dispatch('toast', type: 'success', title: 'Saved', message: 'Information successfully saved.', duration: 3000);
    }

    public function storeItem(int $id): void
    {
        $row = $this->base()->where('status', 'pending')->findOrFail($id);
        $row->status = 'stored';
        $row->save();

        $this->resetPage('pendingPage');
        $this->resetPage('storedPage');
        $this->dispatch('toast', type: 'success', title: 'Stored', message: 'Item successfully stored.', duration: 3000);
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

    public function finalizeItem(int $id): void
    {
        $row = $this->base()->where('status', 'stored')->findOrFail($id);
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

        $this->resetPage('storedPage');
        $this->dispatch('toast', type: 'success', title: 'Done', message: 'Item successfully finalized.', duration: 3000);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        $users = UserModel::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['user_id', 'full_name']);

        $storedDirections = collect($this->stored->items())
            ->mapWithKeys(function ($row) {
                $dir = $this->getDirectionFor($row);
                return [($row->delivery_id ?? $row->id) => $dir];
            })
            ->toArray();

        return view('livewire.pages.receptionist.docpackstatus', [
            'pending' => $this->pending,
            'stored' => $this->stored,
            'storedDirections' => $storedDirections,
            'users' => $users,
        ]);
    }
}