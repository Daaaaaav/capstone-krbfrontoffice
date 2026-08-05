<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Delivery;
use App\Models\User as UserModel;
use App\Services\ImageHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Documents & Packages — History')]
class DocPackHistory extends Component
{
    use WithPagination;
    use HasViewMode;
    use WithFileUploads;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';
    public ?string $selectedDate = null;
    public string $dateMode = 'semua';
    public string $type = 'all';
    public string $filterSender = '';
    public string $filterReceiver = '';
    public ?int $userId = null;
    public ?int $departmentId = null;
    public string $userQ = '';
    public int $perDone = 6;
    public bool $showFilterModal = false;
    public bool $withTrashed = false;
    public bool $showEdit = false;
    public ?int $editId = null;
    public ?int $deletingId = null;
    public string $deletingSummary = '';
    public bool $showDeleteModal = false;
    public array $edit = [
        'item_name' => null,
        'nama_pengirim' => null,
        'nama_penerima' => null,
    ];
    public $editPhoto = null;
    public ?string $editCurrentImage = null;
    public ?string $editLastEdited = null;
    public ?string $editCreatedAt = null;
    public array $statusLogs = [];

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

        if (in_array($name, ['q', 'selectedDate', 'dateMode', 'type', 'filterSender', 'filterReceiver', 'userId', 'departmentId', 'userQ', 'withTrashed'], true)) {
            $this->resetPage('donePage');
        }
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
        return Delivery::query()
            ->byCompany(Auth::user()->company_id ?? null)
            ->when($this->withTrashed,  fn($q) => $q->withTrashed())
            ->when(!$this->withTrashed, fn($q) => $q->whereNull('deleted_at'));
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

        if ($this->departmentId) {
            $q->where('department_id', $this->departmentId);
        }

        if ($this->userId && Schema::hasColumn('deliveries', 'receptionist_id')) {
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

        if (trim($this->userQ) !== '' && Schema::hasColumn('deliveries', 'receptionist_id')) {
            $userIds = UserModel::query()
                ->where('company_id', Auth::user()->company_id ?? null)
                ->whereNull('deleted_at')
                ->where('full_name', 'like', '%' . trim($this->userQ) . '%')
                ->pluck('user_id');
            if ($userIds->isNotEmpty()) {
                $q->whereIn('receptionist_id', $userIds);
            } else {
                $q->whereRaw('0=1');
            }
        }

        if (trim($this->q) !== '') {
            $term = '%' . trim($this->q) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('item_name', 'like', $term)
                    ->orWhere('nama_pengirim', 'like', $term)
                    ->orWhere('nama_penerima', 'like', $term)
                    ->orWhere(function ($qqq) use ($term) {
                        $qqq->whereHas('receptionist', function ($u) use ($term) {
                            $u->where('full_name', 'like', $term);
                        });
                    });
            });
        }

        if ($this->dateMode === 'terbaru') {
            $q->latest('created_at');
        } elseif ($this->dateMode === 'terlama') {
            $q->oldest('created_at');
        }

        return $q;
    }

    public function getDoneProperty()
    {
        $q = $this->base()->whereIn('status', ['delivered', 'taken']);

        $this->applySharedFilters($q);

        $q->orderByRaw("
            COALESCE(
              CASE
                WHEN status = 'delivered' THEN UNIX_TIMESTAMP(pengiriman)
                WHEN status = 'taken'     THEN UNIX_TIMESTAMP(pengambilan)
                ELSE UNIX_TIMESTAMP(created_at)
              END, 0
            ) DESC
        ");

        return $q->with('receptionist')
            ->paginate($this->perDone, pageName: 'donePage');
    }

    public function openEdit(int $id): void
    {
        $row = $this->base()->findOrFail($id);
        $this->editId = $row->delivery_id ?? $row->id ?? $id;
        $this->editLastEdited = $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d M Y, H:i') : null;
        $this->editCreatedAt = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i') : null;
        $this->edit = [
            'item_name' => $row->item_name,
            'nama_pengirim' => $row->nama_pengirim,
            'nama_penerima' => $row->nama_penerima,
        ];
        $this->editCurrentImage = $row->image;
        $this->editPhoto = null;
        
        $logs = [];
        if ($row->created_at) {
            $logs[] = ['status' => 'Arrived at Receptionist', 'time' => $row->created_at, 'type' => 'info'];
        }
        if ($row->pengiriman) {
            $logs[] = ['status' => 'Delivered to Department', 'time' => $row->pengiriman, 'type' => 'primary'];
        }
        if ($row->pengambilan) {
            $logs[] = ['status' => 'Taken by Employee', 'time' => $row->pengambilan, 'type' => 'success'];
        }
        if ($row->trashed() && $row->deleted_at) {
            $logs[] = ['status' => 'Deleted', 'time' => $row->deleted_at, 'type' => 'danger'];
        }
        
        usort($logs, function($a, $b) {
            return \Carbon\Carbon::parse($a['time'])->timestamp <=> \Carbon\Carbon::parse($b['time'])->timestamp;
        });
        
        $this->statusLogs = $logs;
        
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        if (!$this->editId) {
            return;
        }

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
            $data['image'] = ImageHelper::storeAsWebp(
                $this->editPhoto,
                'images/deliveries',
                'delivery',
                'public'
            );
        }

        $row->fill($data)->save();

        $this->showEdit = false;
        $this->reset('editId', 'edit', 'editPhoto', 'editCurrentImage', 'statusLogs', 'editLastEdited', 'editCreatedAt');
        $this->resetPage('donePage');
        $this->dispatch('toast', type: 'success', title: 'Saved', message: 'Information successfully saved.', duration: 3000);
    }

    public function confirmDelete(int $id, string $summary): void
    {
        $this->deletingId = $id;
        $this->deletingSummary = $summary;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (!$this->deletingId) {
            return;
        }
        $row = $this->base()->findOrFail($this->deletingId);
        $row->delete();
        $this->resetPage('donePage');
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', title: 'Deleted', message: 'Information successfully deleted.', duration: 3000);
    }

    public function restore(int $id): void
    {
        $row = Delivery::withTrashed()->byCompany(Auth::user()->company_id ?? null)->findOrFail($id);
        $row->restore();
        $this->resetPage('donePage');
        $this->dispatch('toast', type: 'success', title: 'Restored', message: 'Information successfully restored.', duration: 3000);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        $users = UserModel::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['user_id', 'full_name', 'department_id'])
            ->unique('user_id');

        $departments = \App\Models\Department::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('department_name')
            ->get(['department_id', 'department_name']);

        return view('livewire.pages.receptionist.docpackhistory', [
            'done' => $this->done,
            'users' => $users,
            'departments' => $departments,
        ]);
    }
}