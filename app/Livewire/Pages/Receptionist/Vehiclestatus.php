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

    public function approvePriorityVehicleById(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $companyId = \Illuminate\Support\Facades\Auth::user()->company_id ?? null;
                $pvb = PriorityVehicleBooking::lockForUpdate()
                    ->where('id', $id)
                    ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->whereIn('status', [
                        PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                        PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();

                if ($pvb->status === PriorityVehicleBooking::STATUS_PENDING_CANCELLATION && $pvb->cancels_booking_id) {
                    VehicleBooking::where('vehiclebooking_id', $pvb->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->update(['status' => 'rejected', 'notes' => 'Cancelled to accommodate a manager priority booking.']);
                }

                $pvb->status     = PriorityVehicleBooking::STATUS_APPROVED;
                $pvb->handled_by = \Illuminate\Support\Facades\Auth::id();
                $pvb->save();
            });

            $this->showPriorityVehicleDetailModal = false;
            $this->priorityVehicleDetailId        = null;
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Priority vehicle booking approved.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    }

    public function openPriorityVehicleReject(int $id): void
    {
        $this->priorityVehicleRejectId     = $id;
        $this->priorityVehicleRejectReason = '';
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
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'priorityVehicleRejectReason' => 'required|string|min:5|max:1000',
        ]);

        try {
            DB::transaction(function () {
                $companyId = \Illuminate\Support\Facades\Auth::user()->company_id ?? null;
                $pvb = PriorityVehicleBooking::lockForUpdate()
                    ->where('id', $this->priorityVehicleRejectId)
                    ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->whereIn('status', [
                        PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                        PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                    ])
                    ->firstOrFail();

                $pvb->status           = PriorityVehicleBooking::STATUS_REJECTED;
                $pvb->rejection_reason = $this->priorityVehicleRejectReason;
                $pvb->handled_by       = \Illuminate\Support\Facades\Auth::id();
                $pvb->save();
            });

            $this->showPriorityVehicleRejectModal = false;
            $this->showPriorityVehicleDetailModal = false;
            $this->priorityVehicleRejectId        = null;
            $this->priorityVehicleDetailId        = null;
            $this->priorityVehicleRejectReason    = '';
            $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Priority vehicle booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to reject: ' . $e->getMessage());
        }
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

    public bool $showNotifPanel = false;
    public bool  $showPriorityApprovalModal = false;
    public ?int  $priorityApprovalNotifId   = null;
    public ?int  $priorityApprovalBookingId = null; 

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