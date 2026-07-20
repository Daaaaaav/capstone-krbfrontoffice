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
use App\Services\GoogleMeetService;
use App\Services\ZoomService;

class QuickBookModal extends Component
{
    public bool $show = false;
    public string $mode = 'create'; // create|rebook

    // booking type
    public string $booking_type    = 'meeting';     // meeting | online_meeting
    public string $online_provider = 'google_meet'; // google_meet | zoom

    // form fields (shared)
    public ?int    $room_id              = null;
    public string  $date                 = '';
    public string  $start_time           = '';
    public string  $end_time             = '';
    public string  $meeting_title        = '';
    public int     $number_of_attendees  = 1;
    public array   $requirements         = [];
    public string  $special_notes        = '';

    // display-only context from the AI (not submitted to DB)
    public ?string $ai_department      = null;
    public ?string $ai_historical_user = null;

    // dropdown
    public array $rooms = [];

    protected int $slotMinutes = 30;
    protected string $tz = 'Asia/Jakarta';

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $this->rooms = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('room_name')
            ->get(['room_id', 'room_name'])
            ->map(fn($r) => ['id' => $r->room_id, 'name' => $r->room_name])
            ->values()
            ->all();
    }

    #[On('open-quick-book')]
    public function open(array $payload = []): void
    {
        $this->resetForm();

        $roomId       = $payload['roomId']         ?? ($payload[0] ?? 0);
        $ymd          = $payload['ymd']            ?? ($payload[1] ?? '');
        $time         = $payload['time']           ?? ($payload[2] ?? '');
        $title        = $payload['title']          ?? ($payload[3] ?? '');
        $endTime      = $payload['endTime']        ?? '';
        $attendees    = $payload['attendees']      ?? 1;
        $notes        = $payload['notes']          ?? '';
        $mode         = $payload['mode']           ?? 'create';
        $dept         = $payload['department']     ?? null;
        $histUser     = $payload['historicalUser'] ?? null;
        $bookingType  = $payload['bookingType']    ?? 'meeting';
        $provider     = $payload['onlineProvider'] ?? 'google_meet';

        $this->mode           = in_array($mode, ['create','rebook'], true) ? $mode : 'create';
        $this->booking_type   = in_array($bookingType, ['meeting','online_meeting'], true) ? $bookingType : 'meeting';
        $this->online_provider = in_array($provider, ['google_meet','zoom'], true) ? $provider : 'google_meet';

        $now = Carbon::now($this->tz);

        $this->room_id    = $roomId ? (int) $roomId : null;
        $this->date       = $ymd ?: $now->toDateString();
        $this->start_time = $time ?: $now->format('H:i');

        if ($endTime !== '') {
            $this->end_time = substr($endTime, 0, 5);
        } else {
            $this->end_time = Carbon::createFromFormat('H:i', $this->start_time)
                ->addMinutes($this->slotMinutes)
                ->format('H:i');
        }

        $this->meeting_title       = $title ?? '';
        $this->number_of_attendees = max(1, (int) $attendees);
        $this->special_notes       = $notes ?? '';
        $this->ai_department       = $dept ?: null;
        $this->ai_historical_user  = $histUser ?: null;

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function submit(): void
    {
        $isOnline = $this->booking_type === 'online_meeting';

        // Dynamic validation: room required only for offline meetings
        $rules = [
            'meeting_title'       => 'required|string|min:3',
            'date'                => 'required|date',
            'number_of_attendees' => 'required|integer|min:1',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'special_notes'       => 'nullable|string|max:1000',
            'requirements'        => 'array',
        ];
        if (!$isOnline) {
            $rules['room_id']      = 'required|integer|exists:rooms,room_id';
            $rules['booking_type'] = 'in:meeting,online_meeting';
        } else {
            $rules['online_provider'] = 'required|in:google_meet,zoom';
        }

        \Illuminate\Support\Facades\Log::info('QuickBookModal: starting validation', [
            'stage'        => 'booking_validation',
            'source'       => 'QuickBookModal',
            'booking_type' => $this->booking_type,
            'room_id'      => $this->room_id,
            'date'         => $this->date,
            'start_time'   => $this->start_time,
            'end_time'     => $this->end_time,
            'title'        => $this->meeting_title,
        ]);

        $this->validate($rules);

        $now = Carbon::now($this->tz);
        if ($this->date < $now->toDateString()) {
            $this->dispatch('toast', type: 'error', message: 'Tidak bisa booking ke tanggal yang sudah lewat.');
            return;
        }
        if ($this->date === $now->toDateString() && $this->start_time < $now->format('H:i')) {
            $this->dispatch('toast', type: 'error', message: 'Start time tidak boleh di masa lalu.');
            return;
        }

        $startDt = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->start_time}", $this->tz);
        $endDt   = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->end_time}",   $this->tz);

        // Overlap check — only relevant for room-based bookings
        if (!$isOnline && $this->room_id) {
            $overlap = BookingRoom::query()
                ->where('room_id', $this->room_id)
                ->where('date', $this->date)
                ->whereIn('status', ['pending','approved'])
                ->where('start_time', '<', $endDt)
                ->where('end_time',   '>', $startDt)
                ->exists();

            if ($overlap) {
                \Illuminate\Support\Facades\Log::warning('QuickBookModal: room slot conflict', [
                    'stage'      => 'booking_validation',
                    'source'     => 'QuickBookModal',
                    'room_id'    => $this->room_id,
                    'date'       => $this->date,
                    'start_time' => $this->start_time,
                    'end_time'   => $this->end_time,
                ]);
                $this->dispatch('toast', type: 'error', message: 'Slot waktu sudah terpakai (pending/approved).');
                return;
            }
        }

        // For online meetings, call the provider API to create the link first
        $meetingUrl = $meetingCode = $meetingPassword = $meetingEventId = null;

        if ($isOnline) {
            try {
                $isGoogle = str_starts_with($this->online_provider, 'google');
                if ($isGoogle) {
                    $svc = app(GoogleMeetService::class);
                    if ($svc->isConnected()) {
                        $meet            = $svc->createMeet($this->meeting_title, $startDt, $endDt, 'Created via Quick Book');
                        $meetingUrl      = $meet['url']      ?? null;
                        $meetingCode     = $meet['code']     ?? null;
                        $meetingPassword = $meet['password'] ?? null;
                        $meetingEventId  = $meet['event_id'] ?? null;
                    } else {
                        $this->dispatch('toast', type: 'error', message: 'Google Meet service account is not connected.');
                        return;
                    }
                } else {
                    $zoomSvc = app(ZoomService::class);
                    if ($zoomSvc->isConfigured()) {
                        $meet            = $zoomSvc->createMeeting($this->meeting_title, $startDt, $endDt, 'Created via Quick Book');
                        $meetingUrl      = $meet['url']      ?? null;
                        $meetingCode     = $meet['code']     ?? null;
                        $meetingPassword = $meet['password'] ?? null;
                        $meetingEventId  = $meet['code']     ?? null;
                    } else {
                        $this->dispatch('toast', type: 'error', message: 'Zoom credentials are not configured.');
                        return;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('QuickBookModal: meeting link creation failed', ['error' => $e->getMessage()]);
                $this->dispatch('toast', type: 'error', message: 'Failed to create meeting link: ' . $e->getMessage());
                return;
            }
        }

        \Illuminate\Support\Facades\Log::info('QuickBookModal: creating room booking', [
            'stage'        => 'booking_create_started',
            'source'       => 'QuickBookModal',
            'room_id'      => $this->room_id,
            'date'         => $this->date,
            'start_time'   => $this->start_time,
            'end_time'     => $this->end_time,
            'title'        => $this->meeting_title,
            'booking_type' => $this->booking_type,
        ]);

        DB::transaction(function () use ($startDt, $endDt, $isOnline, $meetingUrl, $meetingCode, $meetingPassword, $meetingEventId) {
            $data = [
                'company_id'           => Auth::user()->company_id ?? 1,
                'user_id'              => Auth::id() ?? 1,
                'department_id'        => Auth::user()->department_id ?? null,
                'meeting_title'        => $this->meeting_title,
                'date'                 => $this->date,
                'number_of_attendees'  => (int) $this->number_of_attendees,
                'start_time'           => $startDt,
                'end_time'             => $endDt,
                'special_notes'        => $this->special_notes,
                'booking_type'         => $this->booking_type,
                'is_approve'           => 0,
                'status'               => 'pending',
                'approved_by'          => null,
            ];

            if (!$isOnline) {
                $data['room_id'] = (int) $this->room_id;
            } else {
                $data['online_provider']         = $this->online_provider;
                $data['online_meeting_url']      = $meetingUrl;
                $data['online_meeting_code']     = $meetingCode;
                $data['online_meeting_password'] = $meetingPassword;
                $data['online_meeting_event_id'] = $meetingEventId;
            }

            $booking = BookingRoom::create($data);

            \Illuminate\Support\Facades\Log::info('QuickBookModal: room booking created', [
                'stage'      => 'booking_created',
                'source'     => 'QuickBookModal',
                'booking_id' => $booking->bookingroom_id,
            ]);

            if (!$isOnline && !empty($this->requirements)) {
                $ids = Requirement::upsertByName($this->requirements);
                $booking->requirements()->sync($ids);
            }
        });

        $msg = match(true) {
            $this->mode === 'rebook' && $isOnline  => 'Online meeting rebook saved (pending approval).',
            $this->mode === 'rebook'                => 'Rebook tersimpan (pending approval).',
            $isOnline                               => 'Online meeting saved (pending approval).',
            default                                 => 'Booking tersimpan (pending approval).',
        };

        $this->dispatch('toast', type: 'success', message: $msg);
        $this->close();
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
        $this->booking_type         = 'meeting';
        $this->online_provider      = 'google_meet';
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
