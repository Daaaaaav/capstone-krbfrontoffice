<?php

namespace App\Livewire\Booking;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Room;
use App\Models\BookingRoom;
use App\Models\Requirement;

class QuickBookModal extends Component
{
    public bool $show = false;
    public string $mode = 'create'; // create|rebook

    // form fields
    public ?int    $room_id              = null;
    public string  $date                 = '';
    public string  $start_time           = '';
    public string  $end_time             = '';
    public string  $meeting_title        = '';
    public int     $number_of_attendees  = 1;
    public array   $requirements         = [];
    public string  $special_notes        = '';

    // display-only context from the AI (not submitted to DB)
    public ?string $ai_department     = null;  // e.g. "IT"
    public ?string $ai_historical_user = null; // e.g. "Davina Test"

    // dropdown
    public array $rooms = [];

    protected int $slotMinutes = 30;
    protected string $tz = 'Asia/Jakarta';

    public function mount(): void
    {
        $this->rooms = Room::orderBy('room_name')
            ->get(['room_id','room_name'])
            ->map(fn($r) => ['id'=>$r->room_id,'name'=>$r->room_name])
            ->values()->all();
    }

    #[On('open-quick-book')]
    public function open(array $payload = []): void
    {
        $this->resetForm();

        $roomId    = $payload['roomId']        ?? ($payload[0] ?? 0);
        $ymd       = $payload['ymd']           ?? ($payload[1] ?? '');
        $time      = $payload['time']          ?? ($payload[2] ?? '');
        $title     = $payload['title']         ?? ($payload[3] ?? '');
        $endTime   = $payload['endTime']       ?? '';
        $attendees = $payload['attendees']     ?? 1;
        $notes     = $payload['notes']         ?? '';
        $mode      = $payload['mode']          ?? 'create';
        $dept      = $payload['department']    ?? null;
        $histUser  = $payload['historicalUser'] ?? null;

        $this->mode = in_array($mode, ['create','rebook'], true) ? $mode : 'create';
        $now = Carbon::now($this->tz);

        $this->room_id    = $roomId ?: null;
        $this->date       = $ymd ?: $now->toDateString();
        $this->start_time = $time ?: $now->format('H:i');

        // Use explicit end time from payload; fall back to +30 min from start
        if ($endTime !== '') {
            $this->end_time = substr($endTime, 0, 5);
        } else {
            $this->end_time = Carbon::createFromFormat('H:i', $this->start_time)
                ->addMinutes($this->slotMinutes)
                ->format('H:i');
        }

        $this->meeting_title        = $title ?? '';
        $this->number_of_attendees  = max(1, (int) $attendees);
        $this->special_notes        = $notes ?? '';
        $this->ai_department        = $dept ?: null;
        $this->ai_historical_user   = $histUser ?: null;

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function submit(): void
    {
        $this->validate([
            'meeting_title'        => 'required|string|min:3',
            'room_id'              => 'required|integer|exists:rooms,room_id',
            'date'                 => 'required|date',
            'number_of_attendees'  => 'required|integer|min:1',
            'start_time'           => 'required|date_format:H:i',
            'end_time'             => 'required|date_format:H:i|after:start_time',
            'special_notes'        => 'nullable|string|max:1000',
            'requirements'         => 'array',
        ]);

        $now = Carbon::now($this->tz);
        if ($this->date < $now->toDateString()) {
            $this->dispatch('toast', ['type'=>'error','message'=>'Tidak bisa booking ke tanggal yang sudah lewat.']);
            return;
        }
        if ($this->date === $now->toDateString() && $this->start_time < $now->format('H:i')) {
            $this->dispatch('toast', ['type'=>'error','message'=>'Start time tidak boleh di masa lalu.']);
            return;
        }

        $startDt = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->start_time}", $this->tz);
        $endDt   = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->end_time}",   $this->tz);

        // block overlap with pending/approved bookings
        $overlap = BookingRoom::query()
            ->where('room_id', $this->room_id)
            ->where('date', $this->date)
            ->whereIn('status', ['pending','approved'])
            ->where('start_time', '<', $endDt)
            ->where('end_time',   '>', $startDt)
            ->exists();

        if ($overlap) {
            $this->dispatch('toast', ['type'=>'error','message'=>'Slot waktu sudah terpakai (pending/approved).']);
            return;
        }

        DB::transaction(function () use ($startDt, $endDt) {
            $booking = BookingRoom::create([
                'room_id'              => (int)$this->room_id,
                'company_id'           => Auth::user()->company_id ?? 1,
                'user_id'              => Auth::id() ?? 1,
                'department_id'        => Auth::user()->department_id ?? null,
                'meeting_title'        => $this->meeting_title,
                'date'                 => $this->date,
                'number_of_attendees'  => (int)$this->number_of_attendees,
                'start_time'           => $startDt,
                'end_time'             => $endDt,
                'special_notes'        => $this->special_notes,
                'is_approve'           => 0,
                'status'               => 'pending',
                'approved_by'          => null,
            ]);

            if (!empty($this->requirements)) {
                $ids = Requirement::upsertByName($this->requirements);
                $booking->requirements()->sync($ids);
            }
        });

        $msg = $this->mode === 'rebook' ? 'Rebook tersimpan (pending approval).' : 'Booking tersimpan (pending approval).';
        $this->dispatch('toast', ['type'=>'success','message'=>$msg]);
        $this->close();

        // notify parent / list to refresh
        $this->dispatch('booking-created');
    }

    protected function resetForm(): void
    {
        $this->room_id              = null;
        $this->date                 = '';
        $this->start_time           = '';
        $this->end_time             = '';
        $this->meeting_title        = '';
        $this->number_of_attendees  = 1;
        $this->requirements         = [];
        $this->special_notes        = '';
        $this->mode                 = 'create';
        $this->ai_department        = null;
        $this->ai_historical_user   = null;
    }

    public function render()
    {
        $now      = Carbon::now($this->tz);
        $minStart = $now->toDateString() === $this->date
            ? $now->format('H:i')   // today: can't pick a past time
            : '00:00';              // future date: any time is fine

        $roomName = '';
        if ($this->room_id) {
            $room = collect($this->rooms)->firstWhere('id', $this->room_id);
            $roomName = $room['name'] ?? '';
        }

        return view('livewire.booking.quick-book-modal', [
            'minStart' => $minStart,
            'roomName' => $roomName,
        ]);
    }
}
