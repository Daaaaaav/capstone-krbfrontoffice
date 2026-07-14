<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Guestbook;
use App\Models\Delivery;

#[Layout('layouts.receptionist')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    protected string $tz = 'Asia/Jakarta';

    // ── Detail Modal ──────────────────────────────────────────────────────
    public bool $showDetailModal = false;
    public ?int $selectedBookingId = null;
    public ?BookingRoom $selectedBookingDetail = null;

    // ── Reject Modal ─────────────────────────────────────────────────────
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    private function asCarbon(null|Carbon|\DateTimeInterface|string $v): ?Carbon
    {
        if ($v === null)
            return null;
        if ($v instanceof Carbon)
            return $v->timezone($this->tz);
        if ($v instanceof \DateTimeInterface)
            return Carbon::instance($v)->timezone($this->tz);

        try {
            return Carbon::parse($v)->timezone($this->tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fmtDate(null|Carbon|\DateTimeInterface|string $v, string $fmt = 'd M Y'): string
    {
        $c = $this->asCarbon($v);
        return $c ? $c->format($fmt) : '—';
    }

    private function fmtTime(null|Carbon|\DateTimeInterface|string $v, string $fmt = 'H.i'): string
    {
        $c = $this->asCarbon($v);
        return $c ? $c->format($fmt) : '—';
    }

    // ─────────────────── Detail Modal ────────────────────────────────────

    public function openDetailModal(int $id): void
    {
        $this->selectedBookingId     = $id;
        $this->selectedBookingDetail = BookingRoom::with(['room', 'requirements', 'user.department', 'department'])
            ->find($id);

        if ($this->selectedBookingDetail) {
            $this->showDetailModal = true;
        } else {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Booking #' . $id . ' not found.', duration: 4000);
        }
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal       = false;
        $this->selectedBookingId     = null;
        $this->selectedBookingDetail = null;
    }

    // ─────────────────── Reject ──────────────────────────────────────────

    public function openReject(int $id): void
    {
        $this->rejectId        = $id;
        $this->rejectReason    = '';
        $this->showRejectModal = true;
        $this->showDetailModal = false; // close detail modal when reject opens
    }

    public function closeReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId        = null;
        $this->rejectReason    = '';
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectId'     => 'required|integer|exists:booking_rooms,bookingroom_id',
            'rejectReason' => 'required|string|min:3|max:500',
        ]);

        try {
            DB::transaction(function () {
                /** @var BookingRoom $b */
                $b = BookingRoom::lockForUpdate()->findOrFail($this->rejectId);

                $b->status      = 'rejected';
                $b->is_approve  = 0;
                $b->approved_by = Auth::id();
                $b->book_reject = $this->rejectReason;
                $b->save();
            });

            $this->showRejectModal = false;
            $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Could not reject: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    public function render()
    {
        $companyId = optional(Auth::user())->company_id;

        // Range 7 hari terakhir (hari ini + 6 hari ke belakang)
        $startOfRange = Carbon::now($this->tz)->subDays(6)->startOfDay();
        $endOfRange = Carbon::now($this->tz)->endOfDay();

        /**
         * Weekly totals (7 hari terakhir) per modul
         */
        $weeklyRoomBookingsCount = BookingRoom::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$startOfRange, $endOfRange])
            ->count();

        $weeklyVehicleBookingsCount = VehicleBooking::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$startOfRange, $endOfRange])
            ->count();

        $weeklyGuestsCount = Guestbook::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$startOfRange, $endOfRange])
            ->count();

        $weeklyDocsCount = Delivery::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$startOfRange, $endOfRange])
            ->count();

        /**
         * Newest Booking Room (limit 5)
         */
        $latestBookingRooms = BookingRoom::query()
            ->with(['room', 'user', 'department'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($br) => [
                'id'           => $br->bookingroom_id,
                'title'        => $br->meeting_title ?? '—',
                'room_id'      => $br->room_id,
                'room_name'    => $br->room?->room_name ?? '—',
                'time'         => $this->fmtTime($br->start_time) . ' - ' . $this->fmtTime($br->end_time),
                'date'         => $this->fmtDate($br->date),
                'status'       => strtolower($br->status ?? 'unknown'),
                'status_label' => ucfirst($br->status ?? '—'),
            ]);

        /**
         * Newest Vehicle Bookings (limit 5)
         */
        $latestVehicleBookings = VehicleBooking::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($vb) => [
                'id' => $vb->vehiclebooking_id,
                'borrower' => $vb->borrower_name ?? '—',
                'purpose' => $vb->purpose ?? '—',
                'destination' => $vb->destination ?? '—',
                'time' => $this->fmtTime($vb->start_at) . ' - ' . $this->fmtTime($vb->end_at),
                'status' => ucfirst($vb->status ?? '—'),
            ]);

        /**
         * Newest Guestbook Entries (limit 5)
         */
        $latestGuests = Guestbook::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($g) => [
                'id' => $g->guestbook_id,
                'name' => $g->name ?? '—',
                'purpose' => $g->keperluan ?? '—',
                'time_in' => $this->fmtTime($g->jam_in),
                'date' => $this->fmtDate($g->date),
            ]);

        /**
         * Newest Document / Package Deliveries (limit 5)
         */
        $latestDocs = Delivery::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($d) => [
                'id' => $d->delivery_id,
                'item' => $d->item_name ?? '—',
                'type' => $d->type ? (__('app.' . $d->type) !== 'app.' . $d->type ? __('app.' . $d->type) : ucfirst($d->type)) : '—',
                'status' => $d->status ? (__('app.' . $d->status) !== 'app.' . $d->status ? __('app.' . $d->status) : ucfirst($d->status)) : '—',
                'direction' => $d->direction ? (__('app.' . $d->direction) !== 'app.' . $d->direction ? __('app.' . $d->direction) : ucfirst($d->direction)) : '—',
                'created' => $this->fmtDate($d->created_at),
            ]);

        return view('livewire.pages.receptionist.dashboard', [
            'latestBookingRooms' => $latestBookingRooms,
            'latestVehicleBookings' => $latestVehicleBookings,
            'latestGuests' => $latestGuests,
            'latestDocs' => $latestDocs,
            'weeklyRoomBookingsCount' => $weeklyRoomBookingsCount,
            'weeklyVehicleBookingsCount' => $weeklyVehicleBookingsCount,
            'weeklyGuestsCount' => $weeklyGuestsCount,
            'weeklyDocsCount' => $weeklyDocsCount,
        ]);
    }
}
