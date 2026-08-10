<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PriorityVehicleBooking;
use App\Models\VehicleBooking;
use App\Models\ManagerNotification;

#[Layout('layouts.manager')]
#[Title('Priority Vehicle Booking Status')]
class PriorityVehicleBookingStatus extends Component
{
    use WithPagination;
    
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';
    
    // Filters
    public string $q = '';
    public string $statusFilter = 'pending'; // pending | approved | all
    public int $perPage = 10;
    
    // Detail modal
    public bool $showDetailModal = false;
    public ?int $detailId = null;
    
    // Approve modal
    public bool $showApproveModal = false;
    public ?int $approveId = null;
    
    // Reject modal
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';
    
    protected $queryString = [
        'q' => ['except' => ''],
        'statusFilter' => ['except' => 'pending'],
        'page' => ['except' => 1],
    ];
    
    public function updatedQ(): void
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
    
    public function mount(): void
    {
        // Auto-expire pending bookings whose end time has passed
        PriorityVehicleBooking::autoExpirePending(Auth::user()->company_id ?? null);
    }
    
    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetailModal = true;
    }
    
    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->detailId = null;
    }
    
    public function getDetailBookingProperty(): ?PriorityVehicleBooking
    {
        if (!$this->detailId) return null;
        
        $companyId = Auth::user()->company_id ?? null;
        
        return PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->find($this->detailId);
    }
    
    public function openApprove(int $id): void
    {
        $this->approveId = $id;
        $this->showApproveModal = true;
    }
    
    public function closeApprove(): void
    {
        $this->showApproveModal = false;
        $this->approveId = null;
    }
    
    public function confirmApprove(): void
    {
        if (!$this->approveId) {
            return;
        }
        
        $user = Auth::user();
        $companyId = $user->company_id ?? null;
        
        try {
            DB::transaction(function () use ($user, $companyId) {
                $booking = PriorityVehicleBooking::lockForUpdate()
                    ->where('id', $this->approveId)
                    ->forCompany($companyId)
                    ->whereIn('status', [
                        PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                        PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();
                
                // If this booking requires cancelling a conflicting booking
                if ($booking->status === PriorityVehicleBooking::STATUS_PENDING_CANCELLATION && $booking->cancels_booking_id) {
                    VehicleBooking::where('vehiclebooking_id', $booking->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->update([
                            'status' => 'rejected',
                            'notes' => DB::raw(
                                "TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), '[Cancelled] Cancelled to accommodate manager priority booking.'))"
                            ),
                        ]);
                }
                
                $booking->status = PriorityVehicleBooking::STATUS_APPROVED;
                $booking->handled_by = $user->user_id;
                $booking->save();
                
                // Update related notifications
                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityVehicleBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'approved', 'is_read' => true]);
            });
            
            $this->closeApprove();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Priority vehicle booking has been approved.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve booking: ' . $e->getMessage());
        }
    }
    
    public function openReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }
    
    public function closeReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectReason = '';
    }
    
    public function confirmReject(): void
    {
        $this->validate([
            'rejectReason' => 'required|string|max:500',
        ]);
        
        if (!$this->rejectId) {
            return;
        }
        
        $user = Auth::user();
        $companyId = $user->company_id ?? null;
        
        try {
            DB::transaction(function () use ($user, $companyId) {
                $booking = PriorityVehicleBooking::lockForUpdate()
                    ->where('id', $this->rejectId)
                    ->forCompany($companyId)
                    ->whereIn('status', [
                        PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                        PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();
                
                $booking->status = PriorityVehicleBooking::STATUS_REJECTED;
                $booking->handled_by = $user->user_id;
                $booking->rejection_reason = $this->rejectReason;
                $booking->save();
                
                // Update related notifications
                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityVehicleBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'rejected', 'is_read' => true]);
            });
            
            $this->closeReject();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Rejected', message: 'Priority vehicle booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to reject booking: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;
        
        $query = PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId);
        
        // Apply status filter
        if ($this->statusFilter === 'pending') {
            $query->whereIn('status', [
                PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
            ]);
        } elseif ($this->statusFilter === 'approved') {
            $query->where('status', PriorityVehicleBooking::STATUS_APPROVED);
        }
        // 'all' shows all non-history statuses (pending and approved)
        elseif ($this->statusFilter === 'all') {
            $query->whereIn('status', [
                PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                PriorityVehicleBooking::STATUS_APPROVED,
            ]);
        }
        
        // Apply search filter
        if ($this->q !== '') {
            $like = '%' . $this->q . '%';
            $query->where(function ($q) use ($like) {
                $q->where('purpose', 'like', $like)
                  ->orWhere('borrower_name', 'like', $like)
                  ->orWhere('destination', 'like', $like)
                  ->orWhereHas('manager', function ($qm) use ($like) {
                      $qm->where('full_name', 'like', $like)
                         ->orWhere('name', 'like', $like);
                  })
                  ->orWhereHas('vehicle', function ($qv) use ($like) {
                      $qv->where('name', 'like', $like)
                         ->orWhere('plate_number', 'like', $like);
                  });
            });
        }
        
        $bookings = $query->orderByDesc('created_at')
            ->paginate($this->perPage);
        
        return view('livewire.pages.manager.priority-vehicle-booking-status', [
            'bookings' => $bookings,
            'detailBooking' => $this->detailBooking,
        ]);
    }
}
