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

    // Filters
    public string $q = '';
    public ?int $vehicleFilter = null;

    /**
     * done     => status completed
     * rejected => status rejected
     */
    public string $statusTab = 'done';

    // Include deleted checkbox
    public bool $withTrashed = false;

    // Date filter (single date)
    public ?string $selectedDate = null;   // 'YYYY-MM-DD' atau null

    // Sort filter
    public string $sortFilter = 'recent';  // recent | oldest | nearest

    // Pagination
    public int $perPage = 5;

    // Edit Modal State
    public bool $showEdit = false;
    public ?int $editId = null;
    
    // Delete Modal State
    public ?int $deletingId = null;
    public string $deletingSummary = '';
    public bool $showDeleteModal = false;
    public bool $isForceDelete = false;
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

    /**
     * Soft delete untuk status 'completed' (Done) dan 'rejected'.
     */
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

    /**
     * Restore soft-deleted row.
     */
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
        $this->edit = [
            'borrower_name' => (string) $booking->borrower_name,
            'purpose'       => (string) $booking->purpose,
            'destination'   => (string) $booking->destination,
            'notes'         => (string) $booking->notes,
            'start_at'      => $booking->start_at ? Carbon::parse($booking->start_at)->format('Y-m-d\TH:i') : '',
            'end_at'        => $booking->end_at ? Carbon::parse($booking->end_at)->format('Y-m-d\TH:i') : '',
        ];

        // Generate pseudo-logs based on timestamps
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
        
        // Sort logs by time
        usort($logs, function($a, $b) {
            return Carbon::parse($a['time'])->timestamp <=> Carbon::parse($b['time'])->timestamp;
        });
        
        $this->statusLogs = $logs;

        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
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
        $this->reset('editId', 'edit');
    }

    public function render()
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $query = VehicleBooking::where('company_id', $companyId);

        // Include / exclude soft-deleted — explicit both branches so the
        // global SoftDeletes scope is always deterministic regardless of
        // query-builder call order.
        if ($this->withTrashed) {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        // Status filter
        if ($this->statusTab === 'rejected') {
            $query->where('status', 'rejected');
        } else {
            $query->where('status', 'completed');
        }

        // Search
        if (strlen(trim($this->q)) > 0) {
            $q = trim($this->q);
            $query->where(function ($qq) use ($q) {
                $qq->where('purpose', 'like', "%{$q}%")
                   ->orWhere('destination', 'like', "%{$q}%")
                   ->orWhere('borrower_name', 'like', "%{$q}%");
            });
        }

        // Filter kendaraan
        if ($this->vehicleFilter) {
            $query->where('vehicle_id', $this->vehicleFilter);
        }

        // Filter tanggal (single date)
        if (!empty($this->selectedDate)) {
            $query->whereDate('start_at', $this->selectedDate);
        }

        // Sorting
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

        // Data kendaraan untuk label
        $vehicles = Vehicle::where('company_id', $companyId)
            ->get(['vehicle_id', 'name', 'plate_number']);

        $vehicleMap = $vehicles->mapWithKeys(function ($v) {
            $label = $v->name ?? $v->plate_number ?? ('#' . $v->vehicle_id);
            return [$v->vehicle_id => $label];
        })->toArray();

        // Manager priority vehicle bookings — approved = done, rejected = rejected
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
        ]);
    }
}
