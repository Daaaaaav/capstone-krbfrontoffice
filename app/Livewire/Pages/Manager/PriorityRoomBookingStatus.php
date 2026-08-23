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
        PriorityRoomBooking::autoApproveNonClashing($companyId);

        // Step 3: Reject any pending bookings whose entire window (end time) has now passed.
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

    public function isOngoing(PriorityRoomBooking $booking): bool
    {
        if ($booking->status !== PriorityRoomBooking::STATUS_APPROVED) {
            return false;
        }

        if (!$booking->date || !$booking->start_time) {
            return false;
        }

        try {
            $dateStr = $booking->date instanceof \DateTimeInterface
                ? $booking->date->format('Y-m-d')
                : substr((string)$booking->date, 0, 10);
            $start = Carbon::parse($dateStr . ' ' . $booking->start_time, $this->tz);
            $now   = Carbon::now($this->tz);
            return $now->gte($start);
        } catch (\Throwable) {
            return false;
        }
    }

    public function markDone(int $id): void
    {
        $user = Auth::user();
        $companyId = $user->company_id ?? null;

        try {
            DB::transaction(function () use ($id, $companyId) {
                $booking = PriorityRoomBooking::lockForUpdate()
                    ->where('id', $id)
                    ->forCompany($companyId)
                    ->firstOrFail();

                if ($booking->status !== PriorityRoomBooking::STATUS_APPROVED) {
                    throw new \RuntimeException("Booking #{$booking->id} is not in approved status.");
                }

                if (!$this->isOngoing($booking)) {
                    throw new \RuntimeException("Booking #{$booking->id} is upcoming and cannot be marked as done yet.");
                }

                $booking->status = PriorityRoomBooking::STATUS_COMPLETED;
                $booking->updated_at = Carbon::now($this->tz)->toDateTimeString();
                $booking->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Priority room booking marked as completed.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Complete', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to complete booking: ' . $e->getMessage());
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
