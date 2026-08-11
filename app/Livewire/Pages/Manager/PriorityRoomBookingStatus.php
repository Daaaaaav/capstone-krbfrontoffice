<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PriorityRoomBooking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\ManagerNotification;

#[Layout('layouts.manager')]
#[Title('Priority Room Booking Status')]
class PriorityRoomBookingStatus extends Component
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
        $companyId = Auth::user()->company_id ?? null;

        // Step 1: Auto-complete approved bookings whose end time has passed.
        PriorityRoomBooking::autoCompleteApproved($companyId);

        // Step 2: Auto-approve pending_receipt bookings whose start time has arrived
        //         (mirrors ordinary BookingRoom auto-approve in AutoApproveBookings scheduler).
        //         MUST run BEFORE the reject-expired step so a booking that just hit
        //         its start time is promoted to approved rather than rejected.
        PriorityRoomBooking::autoApproveNonClashing($companyId);

        // Step 3: Reject any pending bookings whose entire window (end time) has now
        //         passed and could therefore never be auto-approved or manually approved.
        //         Mirrors ordinary BookingRoom pending→rejected logic in AutoCompleteBookings.
        PriorityRoomBooking::autoRejectExpiredPending($companyId);
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
    
    public function getDetailBookingProperty(): ?PriorityRoomBooking
    {
        if (!$this->detailId) return null;
        
        $companyId = Auth::user()->company_id ?? null;
        
        return PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->find($this->detailId);
    }
    
    /**
     * Check if a Priority Room Booking has a clash with an existing room booking.
     * Returns true if there's a time-overlap conflict requiring manager approval.
     */
    public function hasClash(PriorityRoomBooking $booking): bool
    {
        if (!$booking->room_id || !$booking->date || !$booking->start_time || !$booking->end_time) {
            return false;
        }

        try {
            $start = Carbon::parse($booking->date . ' ' . $booking->start_time, $this->tz);
            $end   = Carbon::parse($booking->date . ' ' . $booking->end_time, $this->tz);
        } catch (\Throwable) {
            return false;
        }

        if ($end->lte($start)) {
            return false;
        }

        // Check for overlapping bookings using the same logic as PriorityRoomBooking form
        $startExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-' THEN date END,
            CONCAT(date, ' ', start_time)
        )";
        $endExpr = "COALESCE(
            CASE WHEN end_time REGEXP '^[0-9]{4}-' THEN end_time END,
            CASE WHEN date     REGEXP '^[0-9]{4}-' THEN date END,
            CONCAT(date, ' ', end_time)
        )";

        $conflict = BookingRoom::query()
            ->whereIn('status', ['pending', 'approved', 'completed', 'done', '1', '3'])
            ->whereNotIn('booking_type', ['online_meeting', 'onlinemeeting'])
            ->where('room_id', $booking->room_id)
            ->whereDate('date', $booking->date)
            ->whereRaw("$startExpr < ?", [$end->toDateTimeString()])
            ->whereRaw("$endExpr > ?", [$start->toDateTimeString()])
            ->exists();

        return $conflict;
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
                $booking = PriorityRoomBooking::lockForUpdate()
                    ->where('id', $this->approveId)
                    ->forCompany($companyId)
                    ->whereIn('status', [
                        PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                        PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();
                
                // If this booking requires cancelling a conflicting booking
                if ($booking->status === PriorityRoomBooking::STATUS_PENDING_CANCELLATION && $booking->cancels_booking_id) {
                    BookingRoom::where('bookingroom_id', $booking->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved', 'completed', 'done', '1', '3'])
                        ->update([
                            'status' => 'rejected',
                            'book_reject' => 'Cancelled — superseded by manager priority booking #' . $booking->id . '.',
                            'approved_by' => $user->user_id,
                        ]);
                }
                
                $booking->status = PriorityRoomBooking::STATUS_APPROVED;
                $booking->handled_by = $user->user_id;
                $booking->save();
                
                // Update related notifications
                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityRoomBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'approved', 'is_read' => true]);
            });
            
            $this->closeApprove();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Priority room booking has been approved.');
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
                $booking = PriorityRoomBooking::lockForUpdate()
                    ->where('id', $this->rejectId)
                    ->forCompany($companyId)
                    ->whereIn('status', [
                        PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                        PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();
                
                $booking->status = PriorityRoomBooking::STATUS_REJECTED;
                $booking->handled_by = $user->user_id;
                $booking->rejection_reason = $this->rejectReason;
                $booking->save();
                
                // Update related notifications
                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityRoomBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'rejected', 'is_read' => true]);
            });
            
            $this->closeReject();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Rejected', message: 'Priority room booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to reject booking: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;
        
        $query = PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId);
        
        // Apply status filter
        if ($this->statusFilter === 'pending') {
            $query->whereIn('status', [
                PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
            ]);
        } elseif ($this->statusFilter === 'approved') {
            $query->where('status', PriorityRoomBooking::STATUS_APPROVED);
        }
        // 'all' shows all non-history statuses (pending and approved)
        elseif ($this->statusFilter === 'all') {
            $query->whereIn('status', [
                PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                PriorityRoomBooking::STATUS_APPROVED,
            ]);
        }
        
        // Apply search filter
        if ($this->q !== '') {
            $like = '%' . $this->q . '%';
            $query->where(function ($q) use ($like) {
                $q->where('meeting_title', 'like', $like)
                  ->orWhereHas('manager', function ($qm) use ($like) {
                      $qm->where('full_name', 'like', $like)
                         ->orWhere('name', 'like', $like);
                  })
                  ->orWhereHas('room', function ($qr) use ($like) {
                      $qr->where('room_name', 'like', $like);
                  });
            });
        }
        
        $bookings = $query->orderByDesc('created_at')
            ->paginate($this->perPage);
        
        return view('livewire.pages.manager.priority-room-booking-status', [
            'bookings' => $bookings,
            'detailBooking' => $this->detailBooking,
        ]);
    }
}
