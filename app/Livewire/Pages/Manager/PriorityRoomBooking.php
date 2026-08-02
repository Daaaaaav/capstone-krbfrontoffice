<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Room;
use App\Models\BookingRoom;
use App\Models\PriorityRoomBooking as PriorityRoomBookingModel;
use App\Models\ManagerNotification;

#[Layout('layouts.manager')]
#[Title('Priority Room Booking')]
class PriorityRoomBooking extends Component
{
    use WithPagination;
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';
    public string $activeTab = 'form'; // form (default) | status

    public ?int $room_id = null;
    public string $meeting_title = '';
    public string $date = '';
    public string $start_time = '';
    public string $end_time = '';
    public int $number_of_attendees = 1;
    public string $special_notes = '';

    public ?int $conflicting_booking_id = null;
    public bool $showConflictModal = false;
    public bool $requestCancellation = false; 

    public string $statusFilter = 'all'; // all (default) | pending | approved | rejected
    public int $perPage = 8;

    public bool $showCancelModal = false;
    public ?int $cancelTargetId = null;

    public array $rooms = [];

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $this->rooms = Room::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('room_name')
            ->get(['room_id', 'room_name', 'capacity'])
            ->map(fn($r) => [
                'id'       => $r->room_id,
                'name'     => $r->room_name,
                'capacity' => $r->capacity,
            ])
            ->toArray();

        $this->date = now($this->tz)->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['form', 'status']) ? $tab : 'form';
        $this->resetPage();
    }

    public function detectConflict(): void
    {
        $this->conflicting_booking_id = null;

        if (!$this->room_id || !$this->date || !$this->start_time || !$this->end_time) {
            return;
        }

        try {
            $start = Carbon::parse($this->date . ' ' . $this->start_time, $this->tz);
            $end   = Carbon::parse($this->date . ' ' . $this->end_time, $this->tz);
        } catch (\Throwable) {
            return;
        }

        if ($end->lte($start)) {
            return;
        }

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
            ->where('room_id', $this->room_id)
            ->whereDate('date', $this->date)
            ->whereRaw("$startExpr < ?", [$end->toDateTimeString()])
            ->whereRaw("$endExpr > ?", [$start->toDateTimeString()])
            ->orderByRaw("FIELD(status, 'approved', 'pending', 'completed', 'done', '1', '3')")
            ->first(['bookingroom_id', 'meeting_title', 'start_time', 'end_time', 'status']);

        if ($conflict) {
            $this->conflicting_booking_id = $conflict->bookingroom_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'room_id'             => ['required', 'integer', 'exists:rooms,room_id'],
            'meeting_title'       => ['required', 'string', 'max:255'],
            'date'                => ['required', 'date'],
            'start_time'          => ['required', 'string'],
            'end_time'            => ['required', 'string'],
            'number_of_attendees' => ['required', 'integer', 'min:1'],
            'special_notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $user      = Auth::user();
        $companyId = $user->company_id ?? null;

        $this->detectConflict();

        if ($this->conflicting_booking_id && !$this->requestCancellation) {
            $this->showConflictModal = true;
            return;
        }

        $status = $this->conflicting_booking_id && $this->requestCancellation
            ? PriorityRoomBookingModel::STATUS_PENDING_CANCELLATION
            : PriorityRoomBookingModel::STATUS_PENDING_RECEIPT;

        DB::transaction(function () use ($user, $companyId, $status) {
            $booking = PriorityRoomBookingModel::create([
                'company_id'          => $companyId,
                'manager_id'          => $user->user_id,
                'room_id'             => $this->room_id,
                'meeting_title'       => $this->meeting_title,
                'date'                => $this->date,
                'start_time'          => $this->start_time,
                'end_time'            => $this->end_time,
                'number_of_attendees' => $this->number_of_attendees,
                'special_notes'       => $this->special_notes ?: null,
                'status'              => $status,
                'cancels_booking_id'  => ($this->requestCancellation && $this->conflicting_booking_id)
                    ? $this->conflicting_booking_id
                    : null,
            ]);

            if ($status === PriorityRoomBookingModel::STATUS_PENDING_CANCELLATION) {
                ManagerNotification::notifyReceptionists(
                    $companyId,
                    ManagerNotification::TYPE_ROOM_CANCEL_REQUEST,
                    'Priority Room Booking — Cancellation Request',
                    'Manager "' . ($user->full_name ?? $user->name) . '" has requested priority booking for room "' .
                        (Room::find($this->room_id)?->room_name ?? '#' . $this->room_id) .
                        '" on ' . $this->date . ' ' . $this->start_time . '–' . $this->end_time .
                        '. An existing approved booking (#' . $this->conflicting_booking_id . ') conflicts and needs to be cancelled.',
                    $booking,
                    actionRequired: true
                );
            } else {
                ManagerNotification::notifyReceptionists(
                    $companyId,
                    ManagerNotification::TYPE_PRIORITY_ROOM_DIRECT,
                    'Priority Room Booking',
                    'Manager "' . ($user->full_name ?? $user->name) . '" has submitted a priority room booking for "' .
                        $this->meeting_title . '" on ' . $this->date . '.',
                    $booking,
                    actionRequired: false
                );
            }
        });

        $this->reset([
            'room_id', 'meeting_title', 'start_time', 'end_time',
            'special_notes', 'conflicting_booking_id', 'requestCancellation',
        ]);
        $this->number_of_attendees = 1;
        $this->date = now($this->tz)->toDateString();
        $this->showConflictModal = false;
        $this->activeTab = 'status';

        $this->dispatch('toast', type: 'success', title: 'Submitted', message: 'Priority room booking submitted.', duration: 3500);
    }

    public function confirmWithCancellation(): void
    {
        $this->requestCancellation = true;
        $this->showConflictModal   = false;
        $this->save();
    }

    public function confirmWithoutCancellation(): void
    {
        $this->conflicting_booking_id = null;
        $this->requestCancellation    = false;
        $this->showConflictModal      = false;
        $this->save();
    }

    public function closeConflictModal(): void
    {
        $this->showConflictModal   = false;
        $this->requestCancellation = false;
    }

    public function openCancelModal(int $id): void
    {
        $this->cancelTargetId = $id;
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

        $companyId = Auth::user()->company_id ?? null;
        $booking = PriorityRoomBookingModel::where('id', $this->cancelTargetId)
            ->where('company_id', $companyId)
            ->where('manager_id', Auth::user()->user_id)
            ->first();

        if ($booking && $booking->isActionable()) {
            $booking->update(['status' => 'rejected', 'rejection_reason' => 'Cancelled by manager.']);
        }

        $this->showCancelModal = false;
        $this->cancelTargetId  = null;
        $this->resetPage();
        $this->dispatch('toast', type: 'info', title: 'Cancelled', message: 'Priority booking cancelled.', duration: 3000);
    }

    public bool   $showSidebarDetail  = false;
    public ?int   $sidebarDetailId    = null;
    public bool   $showSidebarReject  = false;
    public string $sidebarRejectReason = '';

    public function openSidebarDetail(int $bookingRoomId): void
    {
        $this->sidebarDetailId   = $bookingRoomId;
        $this->sidebarRejectReason = '';
        $this->showSidebarDetail = true;
    }

    public function closeSidebarDetail(): void
    {
        $this->showSidebarDetail  = false;
        $this->showSidebarReject  = false;
        $this->sidebarDetailId    = null;
        $this->sidebarRejectReason = '';
    }

    public function openSidebarReject(): void
    {
        $this->showSidebarReject = true;
    }

    public function submitSidebarReject(): void
    {
        $this->validate([
            'sidebarRejectReason' => 'required|string|min:3|max:500',
        ]);

        $companyId = Auth::user()->company_id ?? null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($companyId) {
            $b = BookingRoom::where('bookingroom_id', $this->sidebarDetailId)
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'approved'])
                ->firstOrFail();

            $b->update([
                'status'      => 'rejected',
                'book_reject' => $this->sidebarRejectReason,
                'approved_by' => Auth::user()->user_id,
            ]);
        });

        $this->closeSidebarDetail();
        $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Booking rejected successfully.', duration: 3000);
    }

    public function getSidebarBookingProperty(): ?BookingRoom
    {
        if (!$this->sidebarDetailId) return null;
        return BookingRoom::with(['room', 'user.department', 'department'])
            ->find($this->sidebarDetailId);
    }
    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;

        $myBookings = PriorityRoomBookingModel::with(['room', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->where('manager_id', Auth::user()->user_id)
            ->when($this->statusFilter !== 'all', function ($q) {
                match ($this->statusFilter) {
                    'pending'  => $q->whereIn('status', [
                        PriorityRoomBookingModel::STATUS_PENDING_RECEIPT,
                        PriorityRoomBookingModel::STATUS_PENDING_CANCELLATION,
                    ]),
                    'approved' => $q->where('status', PriorityRoomBookingModel::STATUS_APPROVED),
                    'rejected' => $q->whereIn('status', [
                        PriorityRoomBookingModel::STATUS_REJECTED,
                        PriorityRoomBookingModel::STATUS_CONFLICT_DENIED,
                    ]),
                    default    => null,
                };
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $conflictingBooking = $this->conflicting_booking_id
            ? BookingRoom::with('room')->find($this->conflicting_booking_id)
            : null;

        $sidebarOngoing = BookingRoom::with('room')
            ->where('company_id', $companyId)
            ->whereIn('status', ['approved', 'pending'])
            ->whereNotIn('booking_type', ['online_meeting', 'onlinemeeting'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('livewire.pages.manager.priority-room-booking', [
            'myBookings'         => $myBookings,
            'conflictingBooking' => $conflictingBooking,
            'sidebarOngoing'     => $sidebarOngoing,
            'sidebarBooking'     => $this->sidebarBooking,
        ]);
    }
}
