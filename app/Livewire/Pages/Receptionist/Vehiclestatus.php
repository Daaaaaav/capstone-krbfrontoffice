<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use App\Models\PriorityVehicleBooking;
use App\Models\ManagerNotification;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Vehicle Status')]
class Vehiclestatus extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    // Filters/state
    public string $q = '';
    public ?int $vehicleFilter = null;
    public ?string $selectedDate = null;   // YYYY-MM-DD
    public string $statusTab = 'pending';  // pending | approved | on_progress
    public string $sortFilter = 'recent';  // recent | oldest | nearest
    public int $perPage = 10;

    /** cache */
    public $vehicles;
    /** @var array<int,string> */
    public array $vehicleMap = [];

    // Reject modal state
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectNote = '';

    // Reject result popup state
    public bool $showRejectResult = false;
    public string $rejectResultType = 'success'; // 'success' | 'error'
    public string $rejectResultTitle = '';
    public string $rejectResultMessage = '';
    public ?int $rejectResultBookingId = null;

    // *** BARU: Detail modal state ***
    public bool $showDetailModal = false;
    public ?int  $selectedBookingId = null;
    /** @var array{before: array, after: array} */
    public array $selectedPhotos = ['before' => [], 'after' => []];
    // *** END BARU ***

    // Priority vehicle booking detail modal
    public bool $showPriorityVehicleDetailModal = false;
    public ?int $priorityVehicleDetailId        = null;

    // Mobile filter modal
    public bool $showFilterModal = false;

    protected $queryString = [
        'q' => ['except' => ''],
        'vehicleFilter' => ['except' => null],
        'selectedDate' => ['except' => null],
        'statusTab' => ['except' => 'pending'],
        'sortFilter' => ['except' => 'recent'],
        'page' => ['except' => 1],
    ];

    // Reset page on filter change
    public function updatedQ()
    {
        $this->resetPage();
    }
    public function updatedVehicleFilter()
    {
        $this->resetPage();
    }
    public function updatedSelectedDate()
    {
        $this->resetPage();
    }
    public function updatedStatusTab()
    {
        $this->resetPage();
    }
    public function updatedSortFilter()
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        // Deduplicate by name: keep only the first vehicle per unique name
        $this->vehicles = Vehicle::orderBy('name')
            ->get()
            ->unique(fn($v) => $v->name ?? $v->plate_number ?? $v->vehicle_id);

        $this->vehicleMap = $this->vehicles
            ->mapWithKeys(fn($v) => [(int) $v->vehicle_id => (string) ($v->name ?? $v->plate_number ?? ('#' . $v->vehicle_id))])
            ->toArray();
    }

    public function render()
    {
        $bookings = VehicleBooking::query()
            ->when($this->vehicleFilter, fn(Builder $q) => $q->where('vehicle_id', $this->vehicleFilter))
            ->when($this->q !== '', function (Builder $q) {
                $like = '%' . $this->q . '%';
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('purpose', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhere('borrower_name', 'like', $like);
                });
            })
            ->when($this->selectedDate, fn(Builder $q) => $q->whereDate('start_at', $this->selectedDate))
            ->when($this->statusTab, function (Builder $q) {
                // Sanitise: only allow valid active-workflow tabs
                $tab = in_array($this->statusTab, ['pending', 'approved', 'on_progress'], true)
                    ? $this->statusTab
                    : 'pending';
                return $tab === 'on_progress'
                    ? $q->whereIn('status', ['on_progress', 'late_return'])
                    : $q->where('status', $tab);
            })
            ->when($this->sortFilter === 'recent', fn(Builder $q) => $q->orderByDesc('vehiclebooking_id'))
            ->when($this->sortFilter === 'oldest', fn(Builder $q) => $q->orderBy('vehiclebooking_id'))
            ->when($this->sortFilter === 'nearest', fn(Builder $q) => $q->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, NOW(), start_at))'))
            ->paginate($this->perPage);

        return view('livewire.pages.receptionist.vehiclestatus', [
            'bookings'               => $bookings,
            'vehicleNotifCount'      => $this->vehicleNotifCount,
            'vehicleNotifs'          => $this->vehicleNotifs,
            'priorityVehicleDetailBooking' => $this->priorityVehicleDetailBooking,
            'selectedBooking'        => $this->selectedBooking,
            // Manager priority vehicle bookings for the current status tab
            'priorityVehicleBookings' => \App\Models\PriorityVehicleBooking::with(['vehicle', 'manager'])
                ->forCompany(optional(\Illuminate\Support\Facades\Auth::user())->company_id)
                ->when($this->statusTab === 'pending', fn($q) => $q->whereIn('status', [
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                ]))
                ->when($this->statusTab === 'approved', fn($q) => $q->where('status', \App\Models\PriorityVehicleBooking::STATUS_APPROVED))
                ->when($this->statusTab === 'on_progress', fn($q) => $q->where('status', \App\Models\PriorityVehicleBooking::STATUS_APPROVED))
                ->when($this->q !== '', fn($q) => $q->where(function($qq) {
                    $like = '%' . $this->q . '%';
                    $qq->where('purpose', 'like', $like)
                       ->orWhere('borrower_name', 'like', $like)
                       ->orWhere('destination', 'like', $like);
                }))
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    /* =========================
     * Actions
     * ========================= */

    public function approve(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                /** @var VehicleBooking $b */
                $b = VehicleBooking::lockForUpdate()
                    ->findOrFail($id);

                if ($b->status !== 'pending') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} is not in pending status.");
                }

                if (now() < $b->start_at) {
                    $b->status = 'approved';
                } else {
                    $b->status = 'on_progress';
                }

                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Booking has been approved.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    } 

    /** Open modal to ask for reject reason */
    public function confirmReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectNote = '';
        $this->showRejectModal = true;
    }

    /** Close/cancel modal */
    public function cancelReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectNote = '';
    }

    /** Validate + perform rejection with required note */
    public function submitReject(): void
    {
        $this->validate([
            'rejectNote' => 'required|string|min:5|max:2000',
            'rejectId'   => 'required|integer',
        ]);

        $bookingId = (int) $this->rejectId;
        $reason    = trim($this->rejectNote);
        $prefix    = '[Rejected] ';

        try {
            $fullNote = $prefix . $reason;

            // Single atomic UPDATE — no SELECT needed, checks status in the WHERE clause.
            // Uses a parameterized expression to safely append the rejection note.
            $affected = DB::table('vehicle_bookings')
                ->where('vehiclebooking_id', $bookingId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'notes'  => DB::raw(
                        "TRIM(CONCAT(COALESCE(notes, ''), IF(COALESCE(notes, '') = '', '', '\n'), " .
                        DB::getPdo()->quote($fullNote) . "))"
                    ),
                ]);

            if ($affected === 0) {
                throw new \RuntimeException("Booking #{$bookingId} could not be rejected — it may no longer be in pending status.");
            }

            $this->showRejectModal   = false;
            $this->rejectResultType  = 'success';
            $this->rejectResultTitle = 'Booking Rejected';
            $this->rejectResultMessage   = "Booking #{$bookingId} has been successfully rejected with a reason.";
            $this->rejectResultBookingId = $bookingId;
            $this->showRejectResult  = true;

            // Optimistically remove the card from the current list without a full re-render
            $this->dispatch('booking-rejected', id: $bookingId);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->showRejectModal        = false;
            $this->rejectResultType       = 'error';
            $this->rejectResultTitle      = 'Cannot Reject';
            $this->rejectResultMessage    = $e->getMessage();
            $this->rejectResultBookingId  = $bookingId;
            $this->showRejectResult       = true;
        } catch (\Throwable $e) {
            report($e);
            $this->showRejectModal        = false;
            $this->rejectResultType       = 'error';
            $this->rejectResultTitle      = 'Error';
            $this->rejectResultMessage    = 'Failed to reject: ' . $e->getMessage();
            $this->rejectResultBookingId  = $bookingId;
            $this->showRejectResult       = true;
        }
    }

    public function closeRejectResult(): void
    {
        $this->showRejectResult = false;
        $this->rejectResultTitle = '';
        $this->rejectResultMessage = '';
        $this->rejectResultBookingId = null;
        $this->rejectId = null;
        $this->rejectNote = '';
    }

    public function markReturned(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $b = VehicleBooking::lockForUpdate()
                    ->findOrFail($id);
                if (!in_array($b->status, ['approved', 'on_progress', 'late_return'], true)) {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} cannot be completed from status '{$b->status}'.");
                }
                $b->status = 'completed';
                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Booking marked as completed.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Update', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Return a human-readable overdue duration string for a booking that is past its end_at.
     * e.g. "2d 3h", "45m". Returns '' if the booking is not overdue.
     */
    public function overdueDuration(VehicleBooking $booking): string
    {
        if (!$booking->end_at) {
            return '';
        }

        $end = \Carbon\Carbon::parse($booking->end_at, $this->tz);
        $now = \Carbon\Carbon::now($this->tz);

        if (!$now->greaterThan($end)) {
            return '';
        }

        $diff = $now->diff($end);

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

    public function markDone(int $id): void
    {
        // Kept for backward compatibility: any existing 'returned' records can still be completed.
        try {
            DB::transaction(function () use ($id) {
                $b = VehicleBooking::lockForUpdate()
                    ->findOrFail($id);
                if ($b->status !== 'returned') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} has not been returned yet.");
                }
                $b->status = 'completed';
                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Booking marked as completed.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Complete', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    // *** BARU: Metode untuk Detail Modal ***
    public function showDetails(int $id): void
    {
        try {
            // Verify it exists before opening
            VehicleBooking::findOrFail($id);
            $this->selectedBookingId = $id;
            $this->showDetailModal   = true;
            $this->resetErrorBag();
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load details: ' . $e->getMessage());
        }
    }

    /** Computed: load the VehicleBooking being viewed in the detail modal. */
    public function getSelectedBookingProperty(): ?VehicleBooking
    {
        if (!$this->selectedBookingId) return null;
        return VehicleBooking::find($this->selectedBookingId);
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal   = false;
        $this->selectedBookingId = null;
        $this->selectedPhotos    = ['before' => [], 'after' => []];
        $this->resetErrorBag();
    }
    // *** END BARU ***

    // ── Priority Vehicle Booking detail modal ──────────────────────────────

    public function openPriorityVehicleDetail(int $id): void
    {
        $this->priorityVehicleDetailId        = $id;
        $this->showPriorityVehicleDetailModal = true;
    }

    public function closePriorityVehicleDetail(): void
    {
        $this->showPriorityVehicleDetailModal = false;
        $this->priorityVehicleDetailId        = null;
    }

    /** Computed: load the PriorityVehicleBooking being viewed in the detail modal. */
    public function getPriorityVehicleDetailBookingProperty(): ?PriorityVehicleBooking
    {
        if (!$this->priorityVehicleDetailId) return null;
        return PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking'])
            ->find($this->priorityVehicleDetailId);
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

    public function selectVehicle(int $vehicleId): void
    {
        $this->vehicleFilter = $vehicleId;
        $this->resetPage();
        $this->showFilterModal = false;
    }

    public function clearVehicleFilter(): void
    {
        $this->vehicleFilter = null;
        $this->resetPage();
        $this->showFilterModal = false;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Priority Vehicle Booking — Notification handling
    // ═══════════════════════════════════════════════════════════════════════

    /** Notification bell panel */
    public bool $showNotifPanel = false;

    /** Approval modal for a cancellation request */
    public bool  $showPriorityApprovalModal = false;
    public ?int  $priorityApprovalNotifId   = null;
    public ?int  $priorityApprovalBookingId = null; // PriorityVehicleBooking id

    public function toggleNotifPanel(): void
    {
        $this->showNotifPanel = !$this->showNotifPanel;
    }

    public function closeNotifPanel(): void
    {
        $this->showNotifPanel = false;
    }

    public function openPriorityApprovalModal(int $notifId): void
    {
        $companyId = \Illuminate\Support\Facades\Auth::user()->company_id ?? null;

        $notif = ManagerNotification::where('id', $notifId)
            ->where('company_id', $companyId)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->first();

        if (!$notif) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found', message: 'Notification not found or already actioned.');
            return;
        }

        $this->priorityApprovalNotifId   = $notifId;
        $this->priorityApprovalBookingId = $notif->notifiable_id;
        $this->showPriorityApprovalModal = true;
        $this->showNotifPanel            = false;
        $notif->markRead();
    }

    public function closePriorityApprovalModal(): void
    {
        $this->showPriorityApprovalModal = false;
        $this->priorityApprovalNotifId   = null;
        $this->priorityApprovalBookingId = null;
    }

    /**
     * Receptionist approves the cancellation:
     * 1. Cancel the conflicting pending vehicle booking.
     * 2. Mark the priority booking as approved.
     * 3. Mark the notification as actioned.
     */
    public function approvePriorityVehicle(): void
    {
        if (!$this->priorityApprovalBookingId) {
            return;
        }

        $companyId = \Illuminate\Support\Facades\Auth::user()->company_id ?? null;

        try {
            DB::transaction(function () use ($companyId) {
                $priority = PriorityVehicleBooking::where('id', $this->priorityApprovalBookingId)
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                // Cancel the conflicting booking if it's still pending
                if ($priority->cancels_booking_id) {
                    VehicleBooking::where('vehiclebooking_id', $priority->cancels_booking_id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'rejected',
                            'notes'  => DB::raw("TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), '[Cancelled — superseded by manager priority booking #" . $this->priorityApprovalBookingId . "]'))"),
                        ]);
                }

                $priority->update([
                    'status'     => PriorityVehicleBooking::STATUS_APPROVED,
                    'handled_by' => \Illuminate\Support\Facades\Auth::user()->user_id,
                ]);

                // Update notification
                if ($this->priorityApprovalNotifId) {
                    ManagerNotification::where('id', $this->priorityApprovalNotifId)
                        ->update(['action_taken' => 'approved', 'is_read' => true]);
                }
            });

            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Priority vehicle booking approved and conflicting booking cancelled.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed: ' . $e->getMessage());
        }

        $this->closePriorityApprovalModal();
        $this->resetPage();
    }

    /**
     * Receptionist denies the cancellation request.
     * The priority booking is marked as conflict-denied.
     */
    public function denyPriorityVehicle(): void
    {
        if (!$this->priorityApprovalBookingId) {
            return;
        }

        $companyId = \Illuminate\Support\Facades\Auth::user()->company_id ?? null;

        try {
            DB::transaction(function () use ($companyId) {
                PriorityVehicleBooking::where('id', $this->priorityApprovalBookingId)
                    ->where('company_id', $companyId)
                    ->update([
                        'status'           => PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
                        'handled_by'       => \Illuminate\Support\Facades\Auth::user()->user_id,
                        'rejection_reason' => 'Cancellation request denied by receptionist.',
                    ]);

                if ($this->priorityApprovalNotifId) {
                    ManagerNotification::where('id', $this->priorityApprovalNotifId)
                        ->update(['action_taken' => 'denied', 'is_read' => true]);
                }
            });

            $this->dispatch('toast', type: 'info', title: 'Denied', message: 'Cancellation request denied. Original booking kept.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed: ' . $e->getMessage());
        }

        $this->closePriorityApprovalModal();
    }

    /** Count of unread vehicle-related notifications for the bell badge. */
    public function getVehicleNotifCountProperty(): int
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) return 0;
        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->where('type', ManagerNotification::TYPE_VEHICLE_CANCEL_REQUEST)
            ->where('is_read', false)
            ->count();
    }

    /** Recent vehicle notifications for the panel dropdown. */
    public function getVehicleNotifsProperty()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) return collect();
        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id)
            ->whereIn('type', [
                ManagerNotification::TYPE_VEHICLE_CANCEL_REQUEST,
                ManagerNotification::TYPE_PRIORITY_VEHICLE_DIRECT,
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }
}