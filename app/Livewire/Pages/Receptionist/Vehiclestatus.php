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
use App\Services\ImageHelper;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Vehicle Status')]
class Vehiclestatus extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    public string $q = '';
    public ?int $vehicleFilter = null;
    public ?string $selectedDate = null;   // YYYY-MM-DD | null (default)
    public string $statusTab = 'pending';  // pending (default) | approved | on_progress
    public string $sortFilter = 'recent';  // recent (default) | oldest | nearest
    public int $perPage = 10;
    public $vehicles;
    public array $vehicleMap = [];
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectNote = '';
    public bool $showRejectResult = false;
    public string $rejectResultType = 'success'; // 'success' (default) | 'error'
    public string $rejectResultTitle = '';
    public string $rejectResultMessage = '';
    public ?int $rejectResultBookingId = null;
    public bool $showDetailModal = false;
    public ?int  $selectedBookingId = null;
    public array $selectedPhotos = ['before' => [], 'after' => []];
    public bool $showPriorityVehicleDetailModal = false;
    public ?int $priorityVehicleDetailId        = null;
    public bool   $showPriorityVehicleRejectModal  = false;
    public ?int   $priorityVehicleRejectId         = null;
    public string $priorityVehicleRejectReason     = '';
    public bool $showFilterModal = false;

    // REMOVED: showPriorityApprovalModal and priorityApprovalBookingId
    // These were for obsolete receptionist approval UI - receptionists now VIEW-ONLY

    // Notification panel state
    public bool $showNotifPanel = false;

    public bool $showApproveModal = false;
    public ?int $approveId = null;
    public bool $showDoneModal = false;
    public ?int $doneId = null;
    public ?string $photoData = null;
    protected $queryString = [
        'q' => ['except' => ''],
        'vehicleFilter' => ['except' => null],
        'selectedDate' => ['except' => null],
        'statusTab' => ['except' => 'pending'],
        'sortFilter' => ['except' => 'recent'],
        'page' => ['except' => 1],
    ];

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

    public function setStatusTab(string $tab): void
    {
        $allowed = ['pending', 'approved', 'on_progress'];
        $this->statusTab = in_array($tab, $allowed, true) ? $tab : 'pending';
        $this->resetPage();
    }
    public function updatedSortFilter()
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->vehicles = Vehicle::orderBy('name')
            ->get()
            ->unique(fn($v) => $v->name ?? $v->plate_number ?? $v->vehicle_id);

        $this->vehicleMap = $this->vehicles
            ->mapWithKeys(fn($v) => [(int) $v->vehicle_id => (string) ($v->name ?? $v->plate_number ?? ('#' . $v->vehicle_id))])
            ->toArray();
    }

    public function render()
    {
        // Auto-transition 'approved' regular bookings to 'on_progress' when start time arrives
        VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', now($this->tz))
            ->update(['status' => 'on_progress']);

        // Auto-transition priority bookings to 'on_progress' when start time arrives
        \App\Models\PriorityVehicleBooking::whereIn('status', [
                \App\Models\PriorityVehicleBooking::STATUS_APPROVED,
                \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT
            ])
            ->where('start_at', '<=', now($this->tz))
            ->update(['status' => \App\Models\PriorityVehicleBooking::STATUS_ON_PROGRESS]);

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

        // Eagerly fetch vehicle notifications to pass to view
        $vehicleNotifs = $this->getVehicleNotifsProperty();
        $vehicleNotifCount = $vehicleNotifs->where('is_read', false)->count();

        return view('livewire.pages.receptionist.vehiclestatus', [
            'bookings'               => $bookings,
            'priorityVehicleDetailBooking' => $this->priorityVehicleDetailBooking,
            'selectedBooking'        => $this->selectedBooking,
            'vehicleNotifs'          => $vehicleNotifs,
            'vehicleNotifCount'      => $vehicleNotifCount,
            'priorityVehicleBookings' => \App\Models\PriorityVehicleBooking::with(['vehicle', 'manager'])
                ->forCompany(optional(\Illuminate\Support\Facades\Auth::user())->company_id)
                ->when($this->statusTab === 'pending', fn($q) => $q->whereIn('status', [
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                ]))
                ->when($this->statusTab === 'approved', fn($q) => $q->where('status', \App\Models\PriorityVehicleBooking::STATUS_APPROVED))
                ->when($this->statusTab === 'on_progress', fn($q) => $q->where('status', \App\Models\PriorityVehicleBooking::STATUS_ON_PROGRESS))
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

    public function openApproveModal(int $id): void
    {
        $this->approveId = $id;
        $this->photoData = null;
        $this->showApproveModal = true;
    }

    public function closeApproveModal(): void
    {
        $this->showApproveModal = false;
        $this->approveId = null;
        $this->photoData = null;
    }

    public function submitApprove(): void
    {
        $this->validate([
            'approveId' => 'required|integer',
            'photoData' => 'required|string',
        ]);

        try {
            DB::transaction(function () {
                $b = VehicleBooking::lockForUpdate()->findOrFail($this->approveId);

                if ($b->status !== 'pending') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} is not in pending status.");
                }

                if (now() < $b->start_at) {
                    $b->status = 'approved';
                } else {
                    $b->status = 'on_progress';
                }

                // Save photo
                if ($this->photoData) {
                    $b->handover_photo = ImageHelper::storeBase64AsWebp(
                        $this->photoData,
                        'vehicle_evidences',
                        'handover_' . $b->vehiclebooking_id
                    );
                }

                $b->save();
            });

            $this->closeApproveModal();
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Booking has been approved with evidence.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    }
    public function confirmReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectNote = '';
        $this->showRejectModal = true;
    }

    public function cancelReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectNote = '';
    }

    public function submitReject(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'rejectNote' => 'required|string|min:5|max:2000',
            'rejectId'   => 'required|integer',
        ]);

        $bookingId = (int) $this->rejectId;
        $reason    = trim($this->rejectNote);
        $prefix    = '[Rejected] ';

        try {
            $fullNote = $prefix . $reason;
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

        try {
            DB::transaction(function () {
                $b = VehicleBooking::lockForUpdate()->findOrFail($this->doneId);
                if (!in_array($b->status, ['approved', 'on_progress', 'late_return'], true)) {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} cannot be completed from status '{$b->status}'.");
                }
                
                $b->status = 'completed';

                // Save photo
                if ($this->photoData) {
                    $b->return_photo = ImageHelper::storeBase64AsWebp(
                        $this->photoData,
                        'vehicle_evidences',
                        'return_' . $b->vehiclebooking_id
                    );
                }

                $b->save();
            });

            $this->closeDoneModal();
            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Booking marked as completed with evidence.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Update', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }
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

    public function showDetails(int $id): void
    {
        try {
            VehicleBooking::findOrFail($id);
            $this->selectedBookingId = $id;
            $this->showDetailModal   = true;
            $this->resetErrorBag();
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load details: ' . $e->getMessage());
        }
    }

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

    public function getPriorityVehicleDetailBookingProperty(): ?PriorityVehicleBooking
    {
        if (!$this->priorityVehicleDetailId) return null;
        return PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking'])
            ->find($this->priorityVehicleDetailId);
    }

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

    public function toggleNotifPanel(): void
    {
        $this->showNotifPanel = !$this->showNotifPanel;
    }

    public function closeNotifPanel(): void
    {
        $this->showNotifPanel = false;
    }

    // ─── Priority Vehicle Approval (notification-driven) ─────────────────────

    public function openPriorityApprovalModal(int $notifId): void
    {
        $notif = ManagerNotification::find($notifId);
        if (!$notif) {
            $this->dispatch('toast', type: 'error', title: 'Not Found', message: 'Notification not found.');
            return;
        }

        // Mark as read
        $notif->markRead();

        // Resolve the linked PriorityVehicleBooking
        $pvb = null;
        if ($notif->notifiable_type === \App\Models\PriorityVehicleBooking::class) {
            $pvb = \App\Models\PriorityVehicleBooking::find($notif->notifiable_id);
        }

        if (!$pvb) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found', message: 'Priority booking no longer exists.');
            return;
        }

        $this->priorityApprovalBookingId = $pvb->id;
        $this->showPriorityApprovalModal  = true;
        $this->showNotifPanel             = false;
    }

    public function closePriorityApprovalModal(): void
    {
        $this->showPriorityApprovalModal  = false;
        $this->priorityApprovalBookingId  = null;
    }

    /**
     * Approve a priority vehicle booking coming from the notification approval modal.
     * Grants the priority booking and, if it targets a conflicting pending booking,
     * cancels that booking.
     */
    public function approvePriorityVehicle(): void
    {
        if (!$this->priorityApprovalBookingId) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'No booking selected.');
            return;
        }

        $this->approvePriorityVehicleById($this->priorityApprovalBookingId);
        $this->closePriorityApprovalModal();
    }

    /**
     * Approve a priority vehicle booking by its ID (used from both the approval modal
     * and the detail modal's footer button).
     */
    public function approvePriorityVehicleById(int $pvbId): void
    {
        try {
            DB::transaction(function () use ($pvbId) {
                $pvb = \App\Models\PriorityVehicleBooking::lockForUpdate()->findOrFail($pvbId);

                $allowedStatuses = [
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                ];

                if (!in_array($pvb->status, $allowedStatuses, true)) {
                    throw new \RuntimeException("Priority booking #{$pvb->id} is not in a pending state (current: {$pvb->status}).");
                }

                // If this booking requests cancellation of a conflicting booking, cancel it now
                if ($pvb->cancels_booking_id) {
                    VehicleBooking::where('vehiclebooking_id', $pvb->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->update([
                            'status' => 'rejected',
                            'notes'  => DB::raw(
                                "TRIM(CONCAT(COALESCE(notes, ''), IF(COALESCE(notes, '') = '', '', '\n'), " .
                                DB::getPdo()->quote('[Cancelled by priority booking #' . $pvb->id . ']') . "))"
                            ),
                        ]);
                }

                $pvb->status     = now() >= $pvb->start_at
                    ? \App\Models\PriorityVehicleBooking::STATUS_ON_PROGRESS
                    : \App\Models\PriorityVehicleBooking::STATUS_APPROVED;
                $pvb->handled_by = \Illuminate\Support\Facades\Auth::id();
                $pvb->save();

                // Mark related notifications as actioned
                ManagerNotification::query()
                    ->where('notifiable_type', \App\Models\PriorityVehicleBooking::class)
                    ->where('notifiable_id', $pvb->id)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'approved', 'is_read' => true]);
            });

            $this->closePriorityVehicleDetail();
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Priority vehicle booking has been approved.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    }

    /**
     * Deny a priority vehicle booking from the notification approval modal.
     * Opens the reject-reason modal instead of immediately rejecting.
     */
    public function denyPriorityVehicle(): void
    {
        if (!$this->priorityApprovalBookingId) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'No booking selected.');
            return;
        }

        $this->priorityVehicleRejectId     = $this->priorityApprovalBookingId;
        $this->priorityVehicleRejectReason = '';
        $this->closePriorityApprovalModal();
        $this->showPriorityVehicleRejectModal = true;
    }

    public function closePriorityVehicleReject(): void
    {
        $this->showPriorityVehicleRejectModal = false;
        $this->priorityVehicleRejectId        = null;
        $this->priorityVehicleRejectReason    = '';
    }

    public function submitPriorityVehicleReject(): void
    {
        $this->validate([
            'priorityVehicleRejectReason' => 'required|string|min:5|max:2000',
            'priorityVehicleRejectId'     => 'required|integer',
        ]);

        try {
            DB::transaction(function () {
                $pvb = \App\Models\PriorityVehicleBooking::lockForUpdate()
                    ->findOrFail($this->priorityVehicleRejectId);

                $allowedStatuses = [
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                    \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                ];

                if (!in_array($pvb->status, $allowedStatuses, true)) {
                    throw new \RuntimeException("Priority booking #{$pvb->id} is not in a pending state.");
                }

                $pvb->status           = \App\Models\PriorityVehicleBooking::STATUS_REJECTED;
                $pvb->rejection_reason = trim($this->priorityVehicleRejectReason);
                $pvb->handled_by       = \Illuminate\Support\Facades\Auth::id();
                $pvb->save();

                // Mark related notifications as actioned
                ManagerNotification::query()
                    ->where('notifiable_type', \App\Models\PriorityVehicleBooking::class)
                    ->where('notifiable_id', $pvb->id)
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'rejected', 'is_read' => true]);
            });

            $this->closePriorityVehicleReject();
            $this->dispatch('toast', type: 'success', title: 'Rejected', message: 'Priority vehicle booking has been rejected.');
            $this->resetPage();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Reject', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to reject: ' . $e->getMessage());
        }
    }

    /**
     * Get unread priority vehicle notifications for the current receptionist.
     */
    public function getVehicleNotifsProperty()
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $companyId = optional(\Illuminate\Support\Facades\Auth::user())->company_id;

        if (!$userId || !$companyId) {
            return collect();
        }

        return ManagerNotification::query()
            ->forCompany($companyId)
            ->forRecipient($userId)
            ->whereIn('type', [
                ManagerNotification::TYPE_PRIORITY_VEHICLE_DIRECT,
                ManagerNotification::TYPE_VEHICLE_CANCEL_REQUEST,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get count of unread priority vehicle notifications.
     */
    public function getVehicleNotifCountProperty(): int
    {
        return $this->vehicleNotifs
            ->where('is_read', false)
            ->count();
    }

}