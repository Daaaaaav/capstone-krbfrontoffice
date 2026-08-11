<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Vehicle;
use App\Models\Department;
use App\Models\User;
use App\Models\VehicleBooking;
use App\Models\PriorityVehicleBooking as PriorityVehicleBookingModel;
use App\Models\ManagerNotification;
use App\Livewire\Traits\HasManagerValidation;

#[Layout('layouts.manager')]
#[Title('Priority Vehicle Booking')]
class PriorityVehicleBooking extends Component
{
    use WithPagination, HasManagerValidation;
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    public string $activeTab = 'form';
    public ?int $vehicle_id = null;
    public ?int $department_id = null;
    public string $borrower_name = '';
    public string $date_from = '';
    public string $date_to = '';
    public string $start_time = '';
    public string $end_time = '';
    public string $purpose = '';
    public string $destination = '';
    public string $purpose_type = 'dinas';
    public string $special_notes = '';
    public ?int $conflicting_vehicle_booking_id = null;
    public bool $showConflictModal = false;
    public bool $requestCancellation = false;
    public string $statusFilter = 'all';
    public int $perPage = 8;
    public bool $showCancelModal = false;
    public ?int $cancelTargetId = null;
    public array $vehicles = [];
    public array $departments = [];
    public array $users = [];
    public array $usersForCombobox = [];

    public function mount(): void
    {
        $user      = Auth::user();
        $companyId = $user->company_id ?? null;

        $this->vehicles = Vehicle::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number'])
            ->map(fn($v) => [
                'id'    => $v->vehicle_id,
                'label' => ($v->name ?? 'Vehicle') . ($v->plate_number ? ' — ' . $v->plate_number : ''),
            ])
            ->toArray();

        $this->departments = Department::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('department_name')
            ->get(['department_id', 'department_name'])
            ->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])
            ->toArray();

        $today = now($this->tz)->toDateString();
        $this->date_from = $today;
        $this->date_to   = $today;
    }

    public function updatedDepartmentId(): void
    {
        $this->borrower_name   = '';
        $this->usersForCombobox = [];

        if (!$this->department_id) {
            return;
        }

        $companyId = Auth::user()->company_id ?? null;

        $this->usersForCombobox = User::where('company_id', $companyId)
            ->where('department_id', $this->department_id)
            ->orderBy('full_name')
            ->get(['user_id', 'full_name'])
            ->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name])
            ->toArray();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['form', 'status']) ? $tab : 'form';
        $this->resetPage();
    }

    public function detectConflict(): void
    {
        $this->conflicting_vehicle_booking_id = null;

        if (!$this->vehicle_id || !$this->date_from || !$this->start_time || !$this->date_to || !$this->end_time) {
            return;
        }

        try {
            $start = Carbon::parse($this->date_from . ' ' . $this->start_time, $this->tz);
            $end   = Carbon::parse($this->date_to . ' ' . $this->end_time, $this->tz);
        } catch (\Throwable) {
            return;
        }

        if ($end->lte($start)) {
            return;
        }

        $conflict = VehicleBooking::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_at', '<', $end->toDateTimeString())
            ->where('end_at', '>', $start->toDateTimeString())
            ->first(['vehiclebooking_id', 'borrower_name', 'start_at', 'end_at']);

        if ($conflict) {
            $this->conflicting_vehicle_booking_id = $conflict->vehiclebooking_id;
        }
    }

    public function save(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'vehicle_id'    => ['required', 'integer', 'exists:vehicles,vehicle_id'],
            'borrower_name' => $this->managerTextRules('Borrower name', required: true, maxLength: 255),
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date', 'after_or_equal:date_from'],
            'start_time'    => ['required', 'string'],
            'end_time'      => ['required', 'string'],
            'purpose'       => $this->managerTextRules('Purpose', required: true, maxLength: 255),
            'destination'   => $this->managerTextRules('Destination', required: false, maxLength: 255),
            'purpose_type'  => ['required', 'in:dinas,operasional,antar_jemput,lainnya'],
            'special_notes' => $this->managerNotesRules(required: false, maxLength: 1000),
        ]);

        $user      = Auth::user();
        $companyId = $user->company_id ?? null;

        $this->detectConflict();

        if ($this->conflicting_vehicle_booking_id && !$this->requestCancellation) {
            $this->showConflictModal = true;
            return;
        }

        $status = $this->conflicting_vehicle_booking_id && $this->requestCancellation
            ? PriorityVehicleBookingModel::STATUS_PENDING_CANCELLATION
            : PriorityVehicleBookingModel::STATUS_PENDING_RECEIPT;

        DB::transaction(function () use ($user, $companyId, $status) {
            $startAt = Carbon::parse($this->date_from . ' ' . $this->start_time, $this->tz);
            $endAt   = Carbon::parse($this->date_to . ' ' . $this->end_time, $this->tz);

            $vehicleLabel = collect($this->vehicles)->firstWhere('id', $this->vehicle_id)['label'] ?? '#' . $this->vehicle_id;

            $booking = PriorityVehicleBookingModel::create([
                'company_id'     => $companyId,
                'manager_id'     => $user->user_id,
                'vehicle_id'     => $this->vehicle_id,
                'department_id'  => $this->department_id ?: null,
                'borrower_name'  => $this->borrower_name,
                'start_at'       => $startAt,
                'end_at'         => $endAt,
                'purpose'        => $this->purpose,
                'destination'    => $this->destination ?: null,
                'purpose_type'   => $this->purpose_type,
                'special_notes'  => $this->special_notes ?: null,
                'status'         => $status,
                'cancels_booking_id' => ($this->requestCancellation && $this->conflicting_vehicle_booking_id)
                    ? $this->conflicting_vehicle_booking_id
                    : null,
            ]);

            if ($status === PriorityVehicleBookingModel::STATUS_PENDING_CANCELLATION) {
                ManagerNotification::notifyReceptionists(
                    $companyId,
                    ManagerNotification::TYPE_VEHICLE_CANCEL_REQUEST,
                    'Priority Vehicle Booking — Cancellation Request',
                    'Manager "' . ($user->full_name ?? $user->name) . '" requested priority use of vehicle "' . $vehicleLabel .
                        '" from ' . $startAt->format('d M Y H:i') . ' to ' . $endAt->format('d M Y H:i') .
                        '. Existing pending booking #' . $this->conflicting_vehicle_booking_id . ' needs to be cancelled.',
                    $booking,
                    actionRequired: true
                );
            } else {
                ManagerNotification::notifyReceptionists(
                    $companyId,
                    ManagerNotification::TYPE_PRIORITY_VEHICLE_DIRECT,
                    'Priority Vehicle Booking',
                    'Manager "' . ($user->full_name ?? $user->name) . '" submitted a priority vehicle booking for "' .
                        $vehicleLabel . '" on ' . $startAt->format('d M Y') . '.',
                    $booking,
                    actionRequired: false
                );
            }
        });

        $this->reset([
            'vehicle_id', 'department_id', 'borrower_name',
            'start_time', 'end_time', 'purpose', 'destination',
            'special_notes', 'conflicting_vehicle_booking_id', 'requestCancellation',
        ]);
        $this->purpose_type      = 'dinas';
        $this->usersForCombobox  = [];
        $today = now($this->tz)->toDateString();
        $this->date_from = $today;
        $this->date_to   = $today;
        $this->showConflictModal = false;
        $this->activeTab = 'status';

        $this->dispatch('toast', type: 'success', title: 'Submitted', message: 'Priority vehicle booking submitted.', duration: 3500);
    }

    public function confirmWithCancellation(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        if (!$this->conflicting_vehicle_booking_id) {
            $this->showConflictModal = false;
            return;
        }

        $user = Auth::user();
        $companyId = $user->company_id ?? null;

        $conflictingBooking = VehicleBooking::where('vehiclebooking_id', $this->conflicting_vehicle_booking_id)
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->first();

        if (!$conflictingBooking) {
            $this->showConflictModal = false;
            $this->conflicting_vehicle_booking_id = null;
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Conflicting vehicle booking no longer exists or cannot be cancelled.', duration: 4000);
            return;
        }

        try {
            $scheduledStart = Carbon::parse($conflictingBooking->start_at, $this->tz);
            $now = Carbon::now($this->tz);
            $hoursUntilStart = $now->diffInHours($scheduledStart, false);

            if ($hoursUntilStart < 3) {
                $this->showConflictModal = false;
                $this->conflicting_vehicle_booking_id = null;
                $this->dispatch('toast', type: 'error', title: 'Cannot Cancel', message: 'The conflicting vehicle booking starts in less than 3 hours and cannot be cancelled for a Priority Booking.', duration: 5000);
                return;
            }
        } catch (\Throwable $e) {
            $this->showConflictModal = false;
            $this->conflicting_vehicle_booking_id = null;
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Invalid booking time. Please try again.', duration: 3000);
            return;
        }

        $this->requestCancellation = true;
        $this->showConflictModal = false;

        DB::transaction(function () use ($conflictingBooking, $user, $companyId) {
            $startAt = Carbon::parse($this->date_from . ' ' . $this->start_time, $this->tz);
            $endAt = Carbon::parse($this->date_to . ' ' . $this->end_time, $this->tz);

            $conflictingBooking->update([
                'status' => 'rejected',
                'notes' => DB::raw(
                    "TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), '[Cancelled] Cancelled due to Manager Priority Booking override.'))"
                ),
            ]);

            $vehicleLabel = collect($this->vehicles)->firstWhere('id', $this->vehicle_id)['label'] ?? '#' . $this->vehicle_id;

            $booking = PriorityVehicleBookingModel::create([
                'company_id' => $companyId,
                'manager_id' => $user->user_id,
                'vehicle_id' => $this->vehicle_id,
                'department_id' => $this->department_id ?: null,
                'borrower_name' => $this->borrower_name,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => $this->purpose,
                'destination' => $this->destination ?: null,
                'purpose_type' => $this->purpose_type,
                'special_notes' => $this->special_notes ?: null,
                'status' => PriorityVehicleBookingModel::STATUS_APPROVED,
                'cancels_booking_id' => $this->conflicting_vehicle_booking_id,
            ]);

            ManagerNotification::notifyReceptionists(
                $companyId,
                ManagerNotification::TYPE_VEHICLE_CANCEL_REQUEST,
                'Regular Vehicle Booking Cancelled — Priority Override',
                'Manager "' . ($user->full_name ?? $user->name) . '" cancelled regular vehicle booking #' . $this->conflicting_vehicle_booking_id .
                    ' (' . $conflictingBooking->borrower_name . ') to create Priority Booking for vehicle "' . $vehicleLabel .
                    '" from ' . $startAt->format('d M Y H:i') . ' to ' . $endAt->format('d M Y H:i') . '.',
                $booking,
                actionRequired: false
            );

            ManagerNotification::notifyReceptionists(
                $companyId,
                ManagerNotification::TYPE_PRIORITY_VEHICLE_DIRECT,
                'Priority Vehicle Booking',
                'Manager "' . ($user->full_name ?? $user->name) . '" submitted a priority vehicle booking for "' .
                    $vehicleLabel . '" on ' . $startAt->format('d M Y') . '.',
                $booking,
                actionRequired: false
            );
        });

        $this->reset([
            'vehicle_id', 'department_id', 'borrower_name',
            'start_time', 'end_time', 'purpose', 'destination',
            'special_notes', 'conflicting_vehicle_booking_id', 'requestCancellation',
        ]);
        $this->purpose_type = 'dinas';
        $this->usersForCombobox = [];
        $today = now($this->tz)->toDateString();
        $this->date_from = $today;
        $this->date_to = $today;
        $this->activeTab = 'status';

        $this->dispatch('toast', type: 'success', title: 'Booking Created', message: 'Conflicting vehicle booking cancelled and priority booking created successfully.', duration: 4000);
    }

    public function confirmWithoutCancellation(): void
    {
        $this->conflicting_vehicle_booking_id = null;
        $this->requestCancellation             = false;
        $this->showConflictModal               = false;
        $this->save();
    }

    public function closeConflictModal(): void
    {
        $this->showConflictModal   = false;
        $this->requestCancellation = false;
    }

    public function openCancelModal(int $id): void
    {
        $this->cancelTargetId  = $id;
        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->cancelTargetId  = null;
        $this->showCancelModal = false;
    }

    public function cancelBooking(): void
    {
        if (!$this->cancelTargetId) {
            return;
        }

        $user = Auth::user();
        $companyId = $user->company_id ?? null;
        $booking = PriorityVehicleBookingModel::where('id', $this->cancelTargetId)
            ->where('company_id', $companyId)
            ->where('manager_id', $user->user_id)
            ->first();

        if (!$booking || !$booking->isActionable()) {
            $this->showCancelModal = false;
            $this->cancelTargetId  = null;
            return;
        }

        try {
            $scheduledStart = Carbon::parse($booking->start_at, $this->tz);
            $now = Carbon::now($this->tz);
            $hoursUntilStart = $now->diffInHours($scheduledStart, false);

            if ($hoursUntilStart < 3) {
                $this->showCancelModal = false;
                $this->cancelTargetId  = null;
                $this->dispatch('toast', type: 'error', title: __('app.error'), message: __('app.priority_booking_cancel_min_3_hours'), duration: 5000);
                return;
            }
        } catch (\Throwable) {
            $this->showCancelModal = false;
            $this->cancelTargetId  = null;
            $this->dispatch('toast', type: 'error', title: __('app.error'), message: __('app.invalid_booking_time'), duration: 3000);
            return;
        }

        DB::transaction(function () use ($booking, $user, $companyId) {
            $booking->update(['status' => 'rejected', 'rejection_reason' => 'Cancelled by manager.']);

            $vehicleLabel = $booking->vehicle 
                ? ($booking->vehicle->name ?? 'Vehicle') . ($booking->vehicle->plate_number ? ' — ' . $booking->vehicle->plate_number : '')
                : 'Vehicle #' . $booking->vehicle_id;

            ManagerNotification::notifyReceptionists(
                $companyId,
                ManagerNotification::TYPE_PRIORITY_VEHICLE_DIRECT,
                'Priority Vehicle Booking Cancelled',
                'Manager "' . ($user->full_name ?? $user->name) . '" has cancelled priority vehicle booking for "' .
                    $vehicleLabel . '" scheduled on ' . $booking->start_at->format('d M Y H:i') . '.',
                $booking,
                actionRequired: false
            );
        });

        $this->showCancelModal = false;
        $this->cancelTargetId  = null;
        $this->resetPage();
        $this->dispatch('toast', type: 'info', title: 'Cancelled', message: 'Priority booking cancelled.', duration: 3000);
    }

    public bool   $showVehicleSidebarDetail   = false;
    public ?int   $vehicleSidebarDetailId     = null;
    public bool   $showVehicleSidebarReject   = false;
    public string $vehicleSidebarRejectReason = '';

    // REMOVED: Mark Done modal (now handled by dedicated Priority Vehicle Status page)
    // Managers should use /manager-priority-vehicle-status for lifecycle management

    public function openVehicleSidebarDetail(int $vehicleBookingId): void
    {
        $this->vehicleSidebarDetailId   = $vehicleBookingId;
        $this->vehicleSidebarRejectReason = '';
        $this->showVehicleSidebarReject   = false;
        $this->showVehicleSidebarDetail   = true;
    }

    public function closeVehicleSidebarDetail(): void
    {
        $this->showVehicleSidebarDetail   = false;
        $this->showVehicleSidebarReject   = false;
        $this->vehicleSidebarDetailId     = null;
        $this->vehicleSidebarRejectReason = '';
    }

    public function openVehicleSidebarReject(): void
    {
        $this->showVehicleSidebarReject = true;
    }

    public function submitVehicleSidebarReject(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate([
            'vehicleSidebarRejectReason' => 'required|string|min:5|max:2000',
        ]);

        $companyId = Auth::user()->company_id ?? null;

        $booking = VehicleBooking::where('vehiclebooking_id', $this->vehicleSidebarDetailId)
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->firstOrFail();

        try {
            $scheduledStart = Carbon::parse($booking->start_at, $this->tz);
            $now = Carbon::now($this->tz);
            $hoursUntilStart = $now->diffInHours($scheduledStart, false);

            if ($hoursUntilStart < 3) {
                $this->closeVehicleSidebarDetail();
                $this->dispatch('toast', type: 'error', title: __('app.error'), message: __('app.priority_booking_reject_min_3_hours'), duration: 5000);
                return;
            }
        } catch (\Throwable) {
            $this->closeVehicleSidebarDetail();
            $this->dispatch('toast', type: 'error', title: __('app.error'), message: __('app.invalid_booking_time'), duration: 3000);
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => 'rejected',
                'notes'  => \Illuminate\Support\Facades\DB::raw(
                    "TRIM(CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='','','\n'), '[Rejected] " .
                    addslashes($this->vehicleSidebarRejectReason) . "'))"
                ),
            ]);
        });

        $this->closeVehicleSidebarDetail();
        $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Vehicle booking rejected.', duration: 3000);
    }

    public function getVehicleSidebarBookingProperty(): ?\App\Models\VehicleBooking
    {
        if (!$this->vehicleSidebarDetailId) return null;
        return VehicleBooking::with(['vehicle', 'user.department', 'department'])
            ->find($this->vehicleSidebarDetailId);
    }

    public function canRejectVehicleSidebarBooking(): bool
    {
        if (!$this->vehicleSidebarBooking) return false;

        try {
            $scheduledStart = Carbon::parse($this->vehicleSidebarBooking->start_at, $this->tz);
            $now = Carbon::now($this->tz);
            $hoursUntilStart = $now->diffInHours($scheduledStart, false);

            return $hoursUntilStart >= 3;
        } catch (\Throwable) {
            return false;
        }
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        $myBookings = PriorityVehicleBookingModel::with(['vehicle', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->where('manager_id', Auth::user()->user_id)
            ->when($this->statusFilter !== 'all', function ($q) {
                match ($this->statusFilter) {
                    'pending'  => $q->whereIn('status', [
                        PriorityVehicleBookingModel::STATUS_PENDING_RECEIPT,
                        PriorityVehicleBookingModel::STATUS_PENDING_CANCELLATION,
                    ]),
                    'approved' => $q->where('status', PriorityVehicleBookingModel::STATUS_APPROVED),
                    'on_road'  => $q->where('status', PriorityVehicleBookingModel::STATUS_ON_PROGRESS),
                    'completed' => $q->where('status', 'completed'),
                    'rejected' => $q->whereIn('status', [
                        PriorityVehicleBookingModel::STATUS_REJECTED,
                        PriorityVehicleBookingModel::STATUS_CONFLICT_DENIED,
                    ]),
                    default    => null,
                };
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $conflictingVehicleBooking = $this->conflicting_vehicle_booking_id
            ? VehicleBooking::with('vehicle')->find($this->conflicting_vehicle_booking_id)
            : null;

        $sidebarVehicles = VehicleBooking::with('vehicle')
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'approved', 'on_progress'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('livewire.pages.manager.priority-vehicle-booking', [
            'myBookings'               => $myBookings,
            'conflictingVehicleBooking' => $conflictingVehicleBooking,
            'sidebarVehicles'          => $sidebarVehicles,
            'vehicleSidebarBooking'    => $this->vehicleSidebarBooking,
        ]);
    }
}
