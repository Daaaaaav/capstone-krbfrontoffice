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
use App\Services\ImageHelper;

#[Layout('layouts.manager')]
#[Title('Priority Vehicle Booking Status')]
class PriorityVehicleBookingStatus extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    // Filters
    public string $q = '';
    public int $perPage = 10;

    // Detail modal
    public bool $showDetailModal = false;
    public ?int $detailId = null;

    // Approve modal (requires proof-before / handover photo — mirrors Vehiclestatus::submitApprove)
    public bool $showApproveModal = false;
    public ?int $approveId = null;
    public ?string $photoData = null;

    // Reject modal (requires reason — mirrors Vehiclestatus::submitReject)
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    // Done modal (requires proof-after / return photo — mirrors Vehiclestatus::submitDone)
    public bool $showDoneModal = false;
    public ?int $doneId = null;
    public ?string $donePhotoData = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        // Step 1: Transition approved → on_progress when start_at arrives
        //         (mirrors VehicleBooking auto-start in Vehiclestatus::render() and scheduler)
        PriorityVehicleBooking::autoProgressToOnProgress($companyId);

        // Step 2: Flag on_progress/approved as late_return when end_at + 1 hour passes
        //         (mirrors AutoApproveBookings late_return logic for VehicleBooking)
        PriorityVehicleBooking::autoFlagLateReturns($companyId);

        // Step 3: Reject pending bookings whose entire window has passed (end_at < now)
        //         and were never approved.  Must run AFTER step 1 so a booking that just
        //         hit its start_at is promoted to on_progress first.
        PriorityVehicleBooking::autoExpirePending($companyId);
    }

    // ─── Detail ───────────────────────────────────────────────────────────────

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

    // ─── Approve (with proof-before / handover photo) ────────────────────────

    public function openApprove(int $id): void
    {
        $this->approveId = $id;
        $this->photoData = null;
        $this->showApproveModal = true;
    }

    public function closeApprove(): void
    {
        $this->showApproveModal = false;
        $this->approveId = null;
        $this->photoData = null;
    }

    /**
     * Approve a pending Priority Vehicle Booking.
     *
     * Behaviour mirrors Vehiclestatus::submitApprove() exactly:
     *  - Requires a handover (before) photo.
     *  - If now < start_at  → status = approved  (waiting to start).
     *  - If now >= start_at → status = on_progress (vehicle already due to go).
     *  - If this booking had a conflict (pending_cancellation), cancel the
     *    conflicting ordinary booking first.
     */
    public function confirmApprove(): void
    {
        $this->validate([
            'photoData' => 'required|string',
        ]);

        if (!$this->approveId) {
            return;
        }

        $user      = Auth::user();
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

                // Cancel the conflicting ordinary booking if needed
                if ($booking->status === PriorityVehicleBooking::STATUS_PENDING_CANCELLATION
                    && $booking->cancels_booking_id) {
                    VehicleBooking::where('vehiclebooking_id', $booking->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->update([
                            'status' => 'rejected',
                            'notes'  => DB::raw(
                                "TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), " .
                                DB::getPdo()->quote('[Cancelled] Cancelled to accommodate manager priority booking #' . $booking->id . '.') . "))"
                            ),
                        ]);
                }

                // Same logic as ordinary: approved if before start, on_progress if at/past start
                $booking->status = now($this->tz)->lt($booking->start_at)
                    ? PriorityVehicleBooking::STATUS_APPROVED
                    : PriorityVehicleBooking::STATUS_ON_PROGRESS;

                $booking->handled_by = $user->user_id;

                // Save handover / proof-before photo (mirrors handover_photo in VehicleBooking)
                $booking->handover_photo = ImageHelper::storeBase64AsWebp(
                    $this->photoData,
                    'vehicle_evidences',
                    'priority_handover_' . $booking->id
                );

                $booking->save();

                // Mark related notifications as actioned
                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityVehicleBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'approved', 'is_read' => true]);
            });

            $this->closeApprove();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Approved',
                message: 'Priority vehicle booking approved with handover photo evidence.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed to approve booking: ' . $e->getMessage());
        }
    }

    // ─── Reject (requires reason — mirrors Vehiclestatus::submitReject) ───────

    public function openReject(int $id): void
    {
        $this->rejectId     = $id;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function closeReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId     = null;
        $this->rejectReason = '';
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:5|max:2000',
        ]);

        if (!$this->rejectId) {
            return;
        }

        $user      = Auth::user();
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

                $booking->status           = PriorityVehicleBooking::STATUS_REJECTED;
                $booking->handled_by       = $user->user_id;
                $booking->rejection_reason = $this->rejectReason;
                $booking->save();

                ManagerNotification::where('notifiable_id', $booking->id)
                    ->where('notifiable_type', PriorityVehicleBooking::class)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'rejected', 'is_read' => true]);
            });

            $this->closeReject();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Rejected',
                message: 'Priority vehicle booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed to reject booking: ' . $e->getMessage());
        }
    }

    // ─── Mark Done (requires proof-after / return photo) ────────────────────

    public function openDone(int $id): void
    {
        $this->doneId        = $id;
        $this->donePhotoData = null;
        $this->showDoneModal = true;
    }

    public function closeDone(): void
    {
        $this->showDoneModal = false;
        $this->doneId        = null;
        $this->donePhotoData = null;
    }

    /**
     * Mark a Priority Vehicle Booking as completed.
     *
     * Mirrors Vehiclestatus::submitDone() exactly:
     *  - Allowed from: on_progress, late_return, or approved (vehicle already out).
     *  - Requires a return (after) photo.
     *  - Sets status = completed.
     *  - After completion the booking is no longer shown on the status page
     *    and appears in PriorityVehicleBookingHistory instead.
     */
    public function confirmDone(): void
    {
        $this->validate([
            'donePhotoData' => 'required|string',
        ]);

        if (!$this->doneId) {
            return;
        }

        $companyId = Auth::user()->company_id ?? null;

        try {
            DB::transaction(function () use ($companyId) {
                $booking = PriorityVehicleBooking::lockForUpdate()
                    ->where('id', $this->doneId)
                    ->forCompany($companyId)
                    ->firstOrFail();

                // Allow done from same statuses as ordinary Vehiclestatus::submitDone
                if (!in_array($booking->status, [
                    PriorityVehicleBooking::STATUS_ON_PROGRESS,
                    PriorityVehicleBooking::STATUS_LATE_RETURN,
                    PriorityVehicleBooking::STATUS_APPROVED,   // started but auto-start hasn't fired yet
                ], true)) {
                    throw new \RuntimeException(
                        "Booking #{$booking->id} cannot be completed from status '{$booking->status}'."
                    );
                }

                // Save return / proof-after photo (mirrors return_photo in VehicleBooking)
                $booking->return_photo = ImageHelper::storeBase64AsWebp(
                    $this->donePhotoData,
                    'vehicle_evidences',
                    'priority_return_' . $booking->id
                );

                $booking->status = PriorityVehicleBooking::STATUS_COMPLETED;
                $booking->save();
            });

            $this->closeDone();
            $this->resetPage();
            $this->dispatch('toast', type: 'success', title: 'Completed',
                message: 'Priority vehicle booking marked as completed with return photo evidence.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Complete',
                message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error',
                message: 'Failed to mark booking as done: ' . $e->getMessage());
        }
    }

    // ─── Late-return overdue duration helper (mirrors Vehiclestatus::overdueDuration) ─

    public function overdueDuration(PriorityVehicleBooking $booking): string
    {
        if (!$booking->end_at) {
            return '';
        }

        $end = Carbon::parse($booking->end_at, $this->tz);
        $now = Carbon::now($this->tz);

        if (!$now->greaterThan($end)) {
            return '';
        }

        $diff    = $now->diff($end);
        $days    = (int) $diff->days;
        $hours   = (int) $diff->h;
        $minutes = (int) $diff->i;

        if ($days >= 1) {
            return $days . 'd' . ($hours > 0 ? ' ' . $hours . 'h' : '');
        }
        if ($hours >= 1) {
            return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'm' : '');
        }
        return max(1, $minutes) . 'm';
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        // Re-run auto-transitions on every render (same pattern as Vehiclestatus::render)
        PriorityVehicleBooking::autoProgressToOnProgress($companyId);
        PriorityVehicleBooking::autoFlagLateReturns($companyId);

        $baseQuery = PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId);

        if ($this->q !== '') {
            $like = '%' . $this->q . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('purpose', 'like', $like)
                  ->orWhere('borrower_name', 'like', $like)
                  ->orWhere('destination', 'like', $like)
                  ->orWhereHas('manager', fn($qm) => $qm->where('full_name', 'like', $like)
                      ->orWhere('name', 'like', $like))
                  ->orWhereHas('vehicle', fn($qv) => $qv->where('name', 'like', $like)
                      ->orWhere('plate_number', 'like', $like));
            });
        }

        // Pending — awaiting approval decision
        $pendingBookings = (clone $baseQuery)
            ->whereIn('status', [
                PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
            ])
            ->orderByDesc('created_at')
            ->get();

        // Approved — waiting for start_at
        $approvedBookings = (clone $baseQuery)
            ->where('status', PriorityVehicleBooking::STATUS_APPROVED)
            ->orderBy('start_at')
            ->get();

        // On the Road — includes late_return (mirrors Vehiclestatus on_progress tab
        // which shows on_progress AND late_return)
        $onProgressBookings = (clone $baseQuery)
            ->whereIn('status', [
                PriorityVehicleBooking::STATUS_ON_PROGRESS,
                PriorityVehicleBooking::STATUS_LATE_RETURN,
            ])
            ->orderBy('start_at')
            ->get();

        // Completed bookings are NOT shown here — they live in PriorityVehicleBookingHistory.
        // This matches the ordinary Vehiclestatus page which only shows pending/approved/on_progress.

        return view('livewire.pages.manager.priority-vehicle-booking-status', [
            'pendingBookings'    => $pendingBookings,
            'approvedBookings'   => $approvedBookings,
            'onProgressBookings' => $onProgressBookings,
            'detailBooking'      => $this->detailBooking,
        ]);
    }
}
