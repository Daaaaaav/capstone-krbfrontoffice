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
use Livewire\WithFileUploads;
use App\Services\ImageHelper;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Documents & Packages — Status')]
class DocPackStatus extends Component
{
    use WithPagination;
    use WithFileUploads;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';
    public ?string $selectedDate = null;
    public string $dateMode = 'semua';
    public string $type = 'all';
    public ?int $departmentId = null;
    public ?int $userId = null;
    public string $departmentQ = '';
    public string $userQ = '';
    public string $activeTab = 'pending';
    public int $perPending = 6;
    public bool $showFilterModal = false;
    public bool $showEdit = false;
    public ?int $editId = null;
    public ?string $editImageUrl = null;
    public $editPhoto = null;
    public bool $showDoneModal = false;
    public ?int $doneId = null;
    public ?string $photoData = null;
    public array $edit = [
        'item_name' => null,
        'nama_pengirim' => null,
        'nama_penerima' => null,
    ];

    protected $rules = [
        'edit.item_name' => 'nullable|string|max:255',
        'edit.nama_pengirim' => 'nullable|string|max:255',
        'edit.nama_penerima' => 'nullable|string|max:255',
        'editPhoto' => 'nullable|image|max:2048',
    ];

    public function updated($name): void
    {
        if ($name === 'departmentId') {
            $this->userId = null;
        }

        if ($name === 'editPhoto') {
            $this->validateOnly('editPhoto');
        }

        if (in_array($name, ['q', 'selectedDate', 'dateMode', 'type', 'departmentId', 'userId', 'departmentQ', 'userQ'], true)) {
            $this->resetPage('pendingPage');
        }
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['pending'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->resetPage('pendingPage');
    }

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
        $this->editImageUrl = $row->image;
        $this->editPhoto = null;
        $this->edit = [
            'item_name' => $row->item_name,
            'nama_pengirim' => $row->nama_pengirim,
            'nama_penerima' => $row->nama_penerima,
        ];
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        if (!$this->editId)
            return;

        $this->validate();

        $row = $this->base()->findOrFail($this->editId);
        
        $dataToUpdate = [
            'item_name' => $this->edit['item_name'],
            'nama_pengirim' => $this->edit['nama_pengirim'],
            'nama_penerima' => $this->edit['nama_penerima'],
        ];

        if ($this->editPhoto) {
            $imagePath = ImageHelper::storeAsWebp(
                $this->editPhoto,
                'images/deliveries',
                'delivery',
                'public'
            );
            $dataToUpdate['image'] = $imagePath;
        }

        $row->fill($dataToUpdate)->save();

        $this->showEdit = false;
        $this->editId = null;
        $this->editPhoto = null;
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

    public function openDoneModal(int $id): void
    {
        $this->doneId = $id;
        $this->photoData = null;
        $this->showDoneModal = true;
    }

    public function closeDoneModal(): void
    {
        $this->showDoneModal = false;
        $this->doneId = null;
        $this->photoData = null;
    }

    public function submitDone(): void
    {
        $this->validate([
            'doneId'    => 'required|integer',
            'photoData' => 'required|string',
        ]);

        $row = $this->base()->where('status', 'pending')->findOrFail($this->doneId);
        $dir = $this->getDirectionFor($row);

        $when = now();

        if ($dir === 'deliver') {
            $row->status = 'delivered';
            $row->pengiriman = $when;
        } else {
            $row->status = 'taken';
            $row->pengambilan = $when;
        }

        if ($this->photoData) {
            $row->proof_image = ImageHelper::storeBase64AsWebp(
                $this->photoData,
                'images/deliveries',
                'done_' . $row->delivery_id
            );
        }

        $row->save();

        $this->closeDoneModal();
        $this->resetPage('pendingPage');
        $this->dispatch('toast', type: 'success', title: 'Done', message: 'Item successfully marked as done with evidence.', duration: 3000);
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