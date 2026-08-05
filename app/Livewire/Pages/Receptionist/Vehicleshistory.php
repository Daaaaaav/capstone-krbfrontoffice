<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use App\Models\PriorityVehicleBooking;
use Carbon\Carbon;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Vehicle History')]
class Vehicleshistory extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    public string $q = '';
    public ?int $vehicleFilter = null;
    public string $statusTab = 'done';
    public bool $withTrashed = false;
    public ?string $selectedDate = null;   // 'YYYY-MM-DD' | null (default)
    public string $sortFilter = 'recent';  // recent (default) | oldest | nearest
    public int $perPage = 5;
    public bool $showEdit = false;
    public ?int $editId = null;
    public ?string $editLastEdited = null;
    public ?string $editCreatedAt = null;
    public ?int $deletingId = null;
    public string $deletingSummary = '';
    public bool $showDeleteModal = false;
    public bool $isForceDelete = false;
    public bool $showDetailModal = false;
    public ?VehicleBooking $selectedBooking = null;
    public array $selectedPhotos = ['before' => [], 'after' => []];
    public bool $showPriorityDetailModal = false;
    public ?int $priorityDetailId = null;
    public bool $showPriorityEdit = false;
    public ?int $priorityEditId = null;
    public ?string $priorityEditLastEdited = null;
    public ?string $priorityEditCreatedAt = null;
    public array $priorityEdit = [
        'borrower_name' => '',
        'purpose'       => '',
        'destination'   => '',
        'special_notes' => '',
        'start_at'      => '',
        'end_at'        => '',
    ];
    public ?int $priorityDeletingId = null;
    public string $priorityDeletingSummary = '';
    public bool $showPriorityDeleteModal = false;

    public array $edit = [
        'borrower_name' => '',
        'purpose'       => '',
        'destination'   => '',
        'notes'         => '',
        'start_at'      => '',
        'end_at'        => '',
    ];

    public array $statusLogs = [];

    protected $queryString = [
        'q'              => ['except' => ''],
        'vehicleFilter'  => ['except' => null],
        'statusTab'      => ['except' => 'done'],
        'withTrashed' => ['except' => false],
        'selectedDate'   => ['except' => null],
        'sortFilter'     => ['except' => 'recent'],
        'page'           => ['except' => 1],
    ];

    public function updatingQ(): void                { $this->resetPage(); }
    public function updatingVehicleFilter(): void    { $this->resetPage(); }
    public function updatingStatusTab(): void        { $this->resetPage(); }
    public function updatingWithTrashed(): void   { $this->resetPage(); }
    public function updatingSelectedDate(): void     { $this->resetPage(); }
    public function updatingSortFilter(): void       { $this->resetPage(); }

    public function mount(): void
    {
        if (!in_array($this->statusTab, ['done', 'rejected'], true)) {
            $this->statusTab = 'done';
        }
        if (!in_array($this->sortFilter, ['recent', 'oldest', 'nearest'], true)) {
            $this->sortFilter = 'recent';
        }
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

        $this->softDeleteAction($this->deletingId);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->isForceDelete = false;
    }

    private function softDeleteAction(int $vehiclebookingId): void
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $booking = VehicleBooking::where('company_id', $companyId)
            ->where('vehiclebooking_id', $vehiclebookingId)
            ->first();

        if (!$booking) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Data tidak ditemukan.', duration: 3000);
            return;
        }

        if (!in_array($booking->status, ['completed', 'rejected'], true)) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Hanya data Completed (Done) atau Rejected yang bisa dihapus.', duration: 3000);
            return;
        }

        if (method_exists($booking, 'delete')) {
            $booking->delete();
            $this->dispatch('toast', type: 'success', title: 'Dihapus', message: "Data #{$vehiclebookingId} berhasil dihapus.", duration: 3000);
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Model belum mendukung soft delete.', duration: 3000);
        }

        $this->resetPage();
    }

    public function restore(int $vehiclebookingId): void
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $booking = VehicleBooking::withTrashed()
            ->where('company_id', $companyId)
            ->where('vehiclebooking_id', $vehiclebookingId)
            ->first();

        if (!$booking) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Data tidak ditemukan untuk di-restore.', duration: 3000);
            return;
        }

        if (method_exists($booking, 'restore') && $booking->trashed()) {
            $booking->restore();
            $this->dispatch('toast', type: 'success', title: 'Dipulihkan', message: "Data #{$vehiclebookingId} berhasil direstore.", duration: 3000);
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Data tidak dalam kondisi terhapus atau model belum mendukung restore.', duration: 3000);
        }

        $this->resetPage();
    }

    public function openEdit(int $id): void
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);
        
        $query = VehicleBooking::where('company_id', $companyId);
        if ($this->withTrashed) {
            $query->withTrashed();
        }
        $booking = $query->find($id);
        
        if (!$booking) return;

        $this->editId = $id;
        $this->editLastEdited = $booking->updated_at ? \Carbon\Carbon::parse($booking->updated_at)->format('d M Y, H:i') : null;
        $this->editCreatedAt = $booking->created_at ? \Carbon\Carbon::parse($booking->created_at)->format('d M Y, H:i') : null;
        $this->edit = [
            'borrower_name' => (string) $booking->borrower_name,
            'purpose'       => (string) $booking->purpose,
            'destination'   => (string) $booking->destination,
            'notes'         => (string) $booking->notes,
            'start_at'      => $booking->start_at ? Carbon::parse($booking->start_at)->format('Y-m-d\TH:i') : '',
            'end_at'        => $booking->end_at ? Carbon::parse($booking->end_at)->format('Y-m-d\TH:i') : '',
        ];

        $logs = [];
        if ($booking->created_at) {
            $logs[] = ['status' => 'Created', 'time' => $booking->created_at, 'type' => 'info'];
        }
        if ($booking->start_at) {
            $logs[] = ['status' => 'Started', 'time' => $booking->start_at, 'type' => 'primary'];
        }
        if ($booking->end_at) {
            $logs[] = ['status' => 'Ended', 'time' => $booking->end_at, 'type' => 'primary'];
        }
        if ($booking->status === 'completed' && $booking->updated_at && $booking->updated_at->gt($booking->end_at ?? $booking->start_at)) {
            $logs[] = ['status' => 'Completed', 'time' => $booking->updated_at, 'type' => 'success'];
        }
        if ($booking->status === 'rejected' && $booking->updated_at) {
            $logs[] = ['status' => 'Rejected', 'time' => $booking->updated_at, 'type' => 'danger'];
        }
        if ($booking->trashed() && $booking->deleted_at) {
            $logs[] = ['status' => 'Deleted', 'time' => $booking->deleted_at, 'type' => 'warning'];
        }
        
        usort($logs, function($a, $b) {
            return Carbon::parse($a['time'])->timestamp <=> Carbon::parse($b['time'])->timestamp;
        });
        
        $this->statusLogs = $logs;

        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'edit.borrower_name' => 'required|string|max:255',
            'edit.purpose'       => 'nullable|string|max:255',
            'edit.destination'   => 'nullable|string|max:255',
            'edit.notes'         => 'nullable|string',
            'edit.start_at'      => 'required|date',
            'edit.end_at'        => 'required|date|after_or_equal:edit.start_at',
        ]);

        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);
        
        $query = VehicleBooking::where('company_id', $companyId);
        if ($this->withTrashed) {
            $query->withTrashed();
        }
        $booking = $query->find($this->editId);

        if ($booking) {
            $booking->update([
                'borrower_name' => $this->edit['borrower_name'],
                'purpose'       => $this->edit['purpose'],
                'destination'   => $this->edit['destination'],
                'notes'         => $this->edit['notes'],
                'start_at'      => Carbon::parse($this->edit['start_at'])->format('Y-m-d H:i:s'),
                'end_at'        => Carbon::parse($this->edit['end_at'])->format('Y-m-d H:i:s'),
            ]);
            $this->dispatch('toast', type: 'success', title: 'Disimpan', message: "Data #{$this->editId} berhasil diperbarui.", duration: 3000);
        }

        $this->showEdit = false;
        $this->reset('editId', 'edit', 'editLastEdited', 'editCreatedAt');
    }

    public function showDetails(int $id): void
    {
        try {
            $booking = VehicleBooking::withTrashed()->findOrFail($id);

            $this->selectedBooking = $booking;

            $this->showDetailModal = true;
            $this->resetErrorBag();

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load details: ' . $e->getMessage());
        }
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBooking = null;
        $this->selectedPhotos = ['before' => [], 'after' => []];
        $this->resetErrorBag();
    }

    public function openPriorityDetail(int $id): void
    {
        $this->priorityDetailId        = $id;
        $this->showPriorityDetailModal = true;
    }

    public function closePriorityDetail(): void
    {
        $this->showPriorityDetailModal = false;
        $this->priorityDetailId        = null;
    }

    public function getPriorityDetailBookingProperty(): ?\App\Models\PriorityVehicleBooking
    {
        if (!$this->priorityDetailId) return null;
        return \App\Models\PriorityVehicleBooking::with(['vehicle', 'manager', 'department'])
            ->find($this->priorityDetailId);
    }

    public function openPriorityEdit(int $id): void
    {
        $user      = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $pvb = \App\Models\PriorityVehicleBooking::where('company_id', $companyId)
            ->find($id);

        if (!$pvb) return;

        $this->priorityEditId          = $id;
        $this->priorityEditLastEdited  = $pvb->updated_at ? Carbon::parse($pvb->updated_at)->format('d M Y, H:i') : null;
        $this->priorityEditCreatedAt   = $pvb->created_at ? Carbon::parse($pvb->created_at)->format('d M Y, H:i') : null;
        $this->priorityEdit = [
            'borrower_name' => (string) ($pvb->borrower_name ?? ''),
            'purpose'       => (string) ($pvb->purpose ?? ''),
            'destination'   => (string) ($pvb->destination ?? ''),
            'special_notes' => (string) ($pvb->special_notes ?? ''),
            'start_at'      => $pvb->start_at ? Carbon::parse($pvb->start_at)->format('Y-m-d\TH:i') : '',
            'end_at'        => $pvb->end_at   ? Carbon::parse($pvb->end_at)->format('Y-m-d\TH:i')   : '',
        ];

        $this->showPriorityDetailModal = false;
        $this->showPriorityEdit        = true;
    }

    public function savePriorityEdit(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'priorityEdit.borrower_name' => 'required|string|max:255',
            'priorityEdit.purpose'       => 'nullable|string|max:255',
            'priorityEdit.destination'   => 'nullable|string|max:255',
            'priorityEdit.special_notes' => 'nullable|string',
            'priorityEdit.start_at'      => 'required|date',
            'priorityEdit.end_at'        => 'required|date|after_or_equal:priorityEdit.start_at',
        ]);

        $user      = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $pvb = \App\Models\PriorityVehicleBooking::where('company_id', $companyId)
            ->find($this->priorityEditId);

        if ($pvb) {
            $pvb->update([
                'borrower_name' => $this->priorityEdit['borrower_name'],
                'purpose'       => $this->priorityEdit['purpose'],
                'destination'   => $this->priorityEdit['destination'],
                'special_notes' => $this->priorityEdit['special_notes'],
                'start_at'      => Carbon::parse($this->priorityEdit['start_at'])->format('Y-m-d H:i:s'),
                'end_at'        => Carbon::parse($this->priorityEdit['end_at'])->format('Y-m-d H:i:s'),
            ]);
            $this->dispatch('toast', type: 'success', title: 'Saved', message: "Priority booking #{$this->priorityEditId} updated.", duration: 3000);
        }

        $this->showPriorityEdit = false;
        $this->reset('priorityEditId', 'priorityEdit', 'priorityEditLastEdited', 'priorityEditCreatedAt');
    }

    public function confirmPriorityDelete(int $id, string $summary): void
    {
        $this->priorityDeletingId      = $id;
        $this->priorityDeletingSummary = $summary;
        $this->showPriorityDeleteModal = true;
    }

    public function executePriorityDelete(): void
    {
        if (!$this->priorityDeletingId) return;

        $user      = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $pvb = \App\Models\PriorityVehicleBooking::where('company_id', $companyId)
            ->find($this->priorityDeletingId);

        if (!$pvb) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Record not found.', duration: 3000);
            $this->showPriorityDeleteModal = false;
            $this->priorityDeletingId      = null;
            return;
        }

        $pvb->delete();
        $this->dispatch('toast', type: 'success', title: 'Deleted', message: "Priority booking #{$this->priorityDeletingId} deleted.", duration: 3000);

        $this->showPriorityDeleteModal = false;
        $this->priorityDeletingId      = null;
        $this->priorityDeletingSummary = '';
    }

    public function render()
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $query = VehicleBooking::where('company_id', $companyId);

        if ($this->withTrashed) {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        if ($this->statusTab === 'rejected') {
            $query->where('status', 'rejected');
        } else {
            $query->where('status', 'completed');
        }

        if (strlen(trim($this->q)) > 0) {
            $q = trim($this->q);
            $query->where(function ($qq) use ($q) {
                $qq->where('purpose', 'like', "%{$q}%")
                   ->orWhere('destination', 'like', "%{$q}%")
                   ->orWhere('borrower_name', 'like', "%{$q}%");
            });
        }

        if ($this->vehicleFilter) {
            $query->where('vehicle_id', $this->vehicleFilter);
        }

        if (!empty($this->selectedDate)) {
            $query->whereDate('start_at', $this->selectedDate);
        }

        $now = Carbon::now($this->tz);
        switch ($this->sortFilter) {
            case 'oldest':
                $query->orderBy('start_at', 'asc');
                break;
            case 'nearest':
                $query->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, start_at, ?))', [$now]);
                break;
            case 'recent':
            default:
                $query->orderBy('start_at', 'desc');
                break;
        }

        $bookings = $query->paginate($this->perPage);

        $vehicles = Vehicle::where('company_id', $companyId)
            ->get(['vehicle_id', 'name', 'plate_number']);

        $vehicleMap = $vehicles->mapWithKeys(function ($v) {
            $label = $v->name ?? $v->plate_number ?? ('#' . $v->vehicle_id);
            return [$v->vehicle_id => $label];
        })->toArray();
        
        $priorityVehicleHistory = PriorityVehicleBooking::with(['vehicle', 'manager'])
            ->forCompany($companyId)
            ->when($this->statusTab === 'done', fn($q) => $q->where('status', PriorityVehicleBooking::STATUS_APPROVED))
            ->when($this->statusTab === 'rejected', fn($q) => $q->whereIn('status', [
                PriorityVehicleBooking::STATUS_REJECTED,
                PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
            ]))
            ->when(strlen(trim($this->q)) > 0, function($q) {
                $like = '%' . trim($this->q) . '%';
                $q->where(fn($qq) => $qq->where('purpose', 'like', $like)
                    ->orWhere('borrower_name', 'like', $like)
                    ->orWhere('destination', 'like', $like));
            })
            ->when($this->vehicleFilter, fn($q) => $q->where('vehicle_id', $this->vehicleFilter))
            ->when(!empty($this->selectedDate), fn($q) => $q->whereDate('start_at', $this->selectedDate))
            ->orderByDesc('updated_at')
            ->get();

        return view('livewire.pages.receptionist.vehicleshistory', [
            'bookings'               => $bookings,
            'vehicleMap'             => $vehicleMap,
            'vehicles'               => $vehicles,
            'priorityVehicleHistory' => $priorityVehicleHistory,
            'priorityDetailBooking'  => $this->priorityDetailBooking,
        ]);
    }
}
