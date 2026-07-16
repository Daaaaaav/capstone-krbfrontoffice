<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\BookingRoom;
use App\Models\PriorityRoomBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Room Approval')]
class RoomApproval extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';

    public int $perPending = 6;
    public int $perOngoing = 6;

    // Priority booking detail modal
    public bool  $showPriorityDetailModal = false;
    public ?int  $priorityDetailId        = null;

    /** Poller */
    public function tick(): void
    {
        // No action needed; Livewire will automatically re-render and re-query
    }

    public function openPriorityDetail(int $id): void
    {
        $this->priorityDetailId        = $id;
        $this->showPriorityDetailModal = true;
    }

    public function closePriorityDetail(): void
    {
        $this->showPriorityDetailModal = false;
        $this->priorityDetailId        = null;
    }

    /** Computed: load the PriorityRoomBooking being viewed */
    public function getPriorityDetailBookingProperty(): ?PriorityRoomBooking
    {
        if (!$this->priorityDetailId) return null;
        return PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking'])
            ->find($this->priorityDetailId);
    }

    private function uiMap(BookingRoom $r): array
    {
        return [
            'id' => $r->getKey(),
            'meeting_title' => $r->meeting_title,
            'room' => (string) ($r->room?->room_number ?? $r->room_id),
            'date' => $r->date ? Carbon::parse($r->date)->format('d M Y') : '—',
            'time' => $r->start_time ? Carbon::parse($r->start_time)->format('H:i') : '—',
            'time_end' => $r->end_time ? Carbon::parse($r->end_time)->format('H:i') : '—',
            'participants' => (int) ($r->number_of_attendees ?? 0),
            'status' => $r->status,
        ];
    }

    public function render()
    {
        $cid = Auth::user()?->company_id;
        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString();

        $startExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
            CONCAT(date, ' ', start_time)
        )";

        // Pending tab: truly pending PLUS approved bookings not yet started.
        $pending = BookingRoom::with('room')
            ->company($cid)
            ->where(function ($q) use ($now, $startExpr) {
                $q->pending()
                  ->orWhere(function ($q2) use ($now, $startExpr) {
                      $q2->approved()
                         ->whereNotNull('date')
                         ->whereNotNull('start_time')
                         ->whereRaw("$startExpr > ?", [$now]);
                  });
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($this->perPending, pageName: 'pendingPage')
            ->through(fn($r) => $this->uiMap($r));

        // Ongoing tab: approved bookings whose start time has arrived.
        $ongoing = BookingRoom::with('room')
            ->company($cid)
            ->approved()
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("$startExpr <= ?", [$now])
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($this->perOngoing, pageName: 'ongoingPage')
            ->through(fn($r) => $this->uiMap($r));

        // Priority room bookings — pending + approved (all active statuses)
        $priorityRoomBookings = PriorityRoomBooking::with(['room', 'manager'])
            ->forCompany($cid)
            ->whereIn('status', [
                PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                PriorityRoomBooking::STATUS_APPROVED,
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.pages.receptionist.room-approval', [
            'pending'              => $pending,
            'ongoing'              => $ongoing,
            'priorityRoomBookings' => $priorityRoomBookings,
            'priorityDetailBooking' => $this->priorityDetailBooking,
        ]);
    }
}
