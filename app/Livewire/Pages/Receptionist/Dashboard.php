<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
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

    public function render()
    {
        $companyId = optional(Auth::user())->company_id;

        $startOfRange = Carbon::now($this->tz)->subDays(6)->startOfDay();
        $endOfRange = Carbon::now($this->tz)->endOfDay();

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

        $latestBookingRooms = BookingRoom::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($br) => [
                'id' => $br->bookingroom_id,
                'title' => $br->meeting_title ?? '—',
                'room_id' => $br->room_id,
                'time' => $this->fmtTime($br->start_time) . ' - ' . $this->fmtTime($br->end_time),
                'date' => $this->fmtDate($br->date),
                'status' => ucfirst($br->status ?? '—'),
            ]);

        $latestVehicleBookings = VehicleBooking::query()
            ->with('vehicle')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn($vb) => [
                'id' => $vb->vehiclebooking_id,
                'borrower' => $vb->borrower_name ?? '—',
                'purpose' => $vb->purpose ?? '—',
                'destination' => $vb->destination ?? '—',
                'vehicle_name' => $vb->vehicle ? $vb->vehicle->name : 'Unknown',
                'date' => $this->fmtDate($vb->start_at),
                'time' => $this->fmtTime($vb->start_at) . ' - ' . $this->fmtTime($vb->end_at),
                'status' => ucfirst($vb->status ?? '—'),
            ]);

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
