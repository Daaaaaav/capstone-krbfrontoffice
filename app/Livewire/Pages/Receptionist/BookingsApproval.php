<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\Requirement; // ADDED: Required for the temporary bug workaround in Blade
use App\Services\GoogleMeetService;
use App\Services\ZoomService;
use Carbon\Carbon;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;
use App\Models\PriorityRoomBooking;
use App\Models\ManagerNotification;

#[Layout('layouts.receptionist')]
#[Title('Room Booking Status')]
class BookingsApproval extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';

    // Filters
    public string $q = '';
    public ?string $selectedDate = null;   // YYYY-MM-DD
    public string $dateMode = 'semua';     // semua | terbaru | terlama

    // Online/Offline scope filter
    // all | offline | online
    public string $typeScope = 'all';

    // Pagination
    public int $perPending = 5;
    public int $perOngoing = 5;

    // Tabs
    public string $activeTab = 'pending';

    // Room filter (rooms.room_id)
    public ?int $roomFilterId = null;

    // Mobile filter modal
    public bool $showFilterModal = false;

    // Reject modal
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    // Reschedule modal
    public bool $showRescheduleModal = false;
    public ?int $rescheduleId = null;
    public string $rescheduleDate = '';
    public string $rescheduleStart = '';
    public string $rescheduleEnd = '';
    public string $rescheduleReason = '';

    // Room select in reschedule modal
    /** @var array<int,array{id:int,label:string}> */
    public array $roomsOptions = [];
    public bool $rescheduleRoomEnabled = false; // tidak dipakai di Blade, tapi boleh tetap ada
    public ?int $rescheduleRoomId = null;

    // Detail modal (NEW)
    public bool $showDetailModal = false;
    public ?int $selectedBookingId = null;
    public ?BookingRoom $selectedBookingDetail = null;

    private string $tz = 'Asia/Jakarta';

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        // sesuai tabel rooms: room_id + room_name
        $query = Room::query()
            ->selectRaw('room_id, room_name as label');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $this->roomsOptions = $query
            ->orderBy('room_name')
            ->get()
            ->map(function ($r) {
                return [
                    'id'    => (int) $r->room_id,
                    'label' => (string) ($r->label ?? ('Room ' . $r->room_id)),
                ];
            })
            ->values()
            ->toArray();
    }

    // ───────────── Helpers ─────────────

    private function buildDt(null|string $dateVal, null|string $timeVal): Carbon
    {
        if (!$dateVal && !$timeVal) {
            throw new \RuntimeException('Tanggal/waktu tidak lengkap.');
        }

        // If time looks like full datetime
        if (is_string($timeVal) && preg_match('/^\d{4}-\d{2}-\d{2}/', $timeVal)) {
            return Carbon::parse($timeVal, $this->tz);
        }

        // If date already datetime
        if (is_string($dateVal) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:/', $dateVal)) {
            $dt = Carbon::parse($dateVal, $this->tz);
            if (is_string($timeVal) && preg_match('/^\d{2}:\d{2}/', $timeVal)) {
                return $dt->setTimeFromTimeString($timeVal);
            }
            return $dt;
        }

        $dateStr = (string) $dateVal;
        $timeStr = (string) ($timeVal === '' ? '00:00:00' : $timeVal);

        return Carbon::parse(trim($dateStr . ' ' . $timeStr), $this->tz);
    }

    /**
     * Auto-progress approved → completed ketika end datetime lewat.
     */
    private function autoProgressToCompleted(): int
    {
        $now = Carbon::now($this->tz)->toDateTimeString();

        $endExpr = "COALESCE(
            CASE WHEN end_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN end_time END,
            CASE WHEN date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
            CONCAT(date, ' ', end_time)
        )";

        return DB::transaction(function () use ($now, $endExpr) {
            // 1. Approved bookings whose end time has passed → completed
            $completed = BookingRoom::query()
                ->where('status', 'approved')
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("$endExpr IS NOT NULL")
                ->whereRaw("$endExpr <= ?", [$now])
                ->update([
                    'status'     => 'completed',
                    'updated_at' => Carbon::now($this->tz)->toDateTimeString(),
                ]);

            // 2. Pending bookings whose end time has already passed will never be
            //    approved — reject them automatically so they leave the active view.
            BookingRoom::query()
                ->where('status', 'pending')
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("$endExpr IS NOT NULL")
                ->whereRaw("$endExpr <= ?", [$now])
                ->update([
                    'status'      => 'rejected',
                    'book_reject' => 'Auto-rejected: booking window expired without approval.',
                    'updated_at'  => Carbon::now($this->tz)->toDateTimeString(),
                ]);

            return $completed;
        });
    }

    /**
     * Auto-approve is intentionally disabled here.
     * Pending bookings should only be approved by a receptionist via the approve() action.
     * The scheduler command (bookings:auto-approve) is also disabled for room bookings
     * so that approved-but-not-yet-started bookings remain visible in the Pending tab
     * with an "Approved" badge until their start time arrives.
     */
    private function autoApprovePending(): void
    {
        // Intentionally left empty — no auto-approve for room bookings.
    }

    private function selectedDateValue(): ?string
    {
        return (is_string($this->selectedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->selectedDate))
            ? $this->selectedDate
            : null;
    }

    private function sortingDirection(): string
    {
        return $this->dateMode === 'terlama' ? 'ASC' : 'DESC';
    }

    private function applyDateTimeOrdering($query)
    {
        $dir = $this->sortingDirection();

        return $query->orderBy('created_at', $dir);
    }

    // ───────── Livewire: reset pagination saat filter berubah ─────────

    public function updatingQ(): void
    {
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function updatingSelectedDate(): void
    {
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function updatingDateMode(): void
    {
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function updatingRoomFilterId(): void
    {
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    // ───────── Tabs & Room filter & Mobile filter modal ─────────

    public function setTab(string $tab): void
    {
        if (!in_array($tab, ['pending', 'ongoing'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    /**
     * Scope online/offline/all.
     */
    public function setTypeScope(string $scope): void
    {
        if (!in_array($scope, ['all', 'offline', 'online'], true)) {
            return;
        }

        $this->typeScope = $scope;
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function selectRoom(?int $roomId = null): void
    {
        $this->roomFilterId = $roomId ?: null;

        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function clearRoomFilter(): void
    {
        $this->selectRoom(null);
    }

    public function clearDate(): void
    {
        $this->selectedDate = null;
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function openFilterModal(): void
    {
        $this->showFilterModal = true;
    }

    public function closeFilterModal(): void
    {
        $this->showFilterModal = false;
    }

    public function getGoogleConnectedProperty(): bool
    {
        return app(GoogleMeetService::class)->isConnected();
    }

    public function getZoomConfiguredProperty(): bool
    {
        return !empty(config('services.zoom.account_id', env('ZOOM_ACCOUNT_ID')))
            && !empty(config('services.zoom.client_id', env('ZOOM_CLIENT_ID')))
            && !empty(config('services.zoom.client_secret', env('ZOOM_CLIENT_SECRET')));
    }

    public function getBookingDetailProperty(): ?BookingRoom
    {
        if ($this->selectedBookingId) {
            return BookingRoom::with(['room', 'requirements', 'user.department', 'department'])
                ->find($this->selectedBookingId);
        }
        return null;
    }

    public function openDetailModal(int $id): void
    {
        $this->selectedBookingId = $id;
        $this->selectedBookingDetail = $this->getBookingDetailProperty();

        if ($this->selectedBookingDetail) {
            $this->showDetailModal = true;
        } else {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Error',
                message: 'Gagal memuat detail booking (ID: ' . $id . ' tidak ditemukan).',
                duration: 5000
            );
            $this->showDetailModal = false;
        }
    }


    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
        $this->selectedBookingDetail = null;
    }

    public function openReject(int $id): void
    {
        $this->rejectId        = $id;
        $this->rejectReason    = '';
        $this->showRejectModal = true;
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
                $b = BookingRoom::lockForUpdate()->findOrFail($this->rejectId);
                $now = Carbon::now($this->tz);
                $start = $this->buildDt($b->date, $b->start_time);
                $minutesLeft = $now->diffInMinutes($start, false);

                if ($minutesLeft < 30) {
                    throw new \RuntimeException('Cannot reject: less than 30 minutes before meeting starts.');
                }

                $b->status      = 'rejected';
                $b->is_approve  = 0;
                $b->approved_by = Auth::id();
                $b->book_reject = $this->rejectReason;
                $b->save();
            });

            $this->showRejectModal = false;
            $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Booking ditolak dan alasan disimpan.');
            $this->resetPage('pendingPage');
            $this->resetPage('ongoingPage');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Gagal menolak: ' . $e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        $this->openReject($id);
    }

    public function approve(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $b = BookingRoom::lockForUpdate()->findOrFail($id);
                if (!in_array($b->booking_type, ['online_meeting', 'onlinemeeting'])) {
                    if (!$b->room_id || !$b->date || !$b->start_time || !$b->end_time) {
                        throw new \RuntimeException('Data ruangan/tanggal/waktu tidak lengkap.');
                    }

                    $start = $this->buildDt($b->date, $b->start_time);
                    $end   = $this->buildDt($b->date, $b->end_time);
                    if ($end->lte($start)) {
                        throw new \RuntimeException('Waktu tidak valid (end <= start).');
                    }

                    $startExpr = "COALESCE(
                        CASE WHEN start_time REGEXP '^[0-9]{4}-' THEN start_time END,
                        CASE WHEN date       REGEXP '^[0-9]{4}-' THEN date END,
                        CONCAT(date, ' ', start_time)
                    )";
                    $endExpr = "COALESCE(
                        CASE WHEN end_time   REGEXP '^[0-9]{4}-' THEN end_time END,
                        CASE WHEN date       REGEXP '^[0-9]{4}-' THEN date END,
                        CONCAT(date, ' ', end_time)
                    )";

                    $overlapExists = BookingRoom::query()
                        ->where('bookingroom_id', '!=', $b->bookingroom_id)
                        ->where('status', 'approved')
                        ->whereNotIn('booking_type', ['online_meeting', 'onlinemeeting'])
                        ->where('room_id', $b->room_id)
                        ->whereDate('date', $b->date)
                        ->whereRaw("$startExpr < ?", [$end->toDateTimeString()])
                        ->whereRaw("$endExpr > ?", [$start->toDateTimeString()])
                        ->exists();

                    if ($overlapExists) {
                        throw new \RuntimeException('Jadwal bentrok dengan booking lain pada ruangan & tanggal yang sama.');
                    }
                }

                if (in_array($b->booking_type, ['online_meeting','onlinemeeting']) && empty($b->online_meeting_url)) {
                    $start = $this->buildDt($b->date, $b->start_time);
                    $end   = $this->buildDt($b->date, $b->end_time);

                    $provider = strtolower((string) $b->online_provider);
                    $provider = str_replace([' ', '-'], '_', $provider);
                    $isGoogle = str_starts_with($provider, 'google');

                    if ($isGoogle) {
                        if (!app(GoogleMeetService::class)->isConnected()) {
                            throw new \RuntimeException('Google belum terhubung untuk pengguna ini.');
                        }
                        $meet = app(GoogleMeetService::class)->createMeet(
                            $b->meeting_title,
                            $start,
                            $end,
                            'Auto-created from KRBS approval'
                        );
                        $b->online_provider = 'google_meet';
                    } else {
                        $meet = app(ZoomService::class)->createMeeting(
                            $b->meeting_title,
                            $start,
                            $end,
                            'Auto-created from KRBS approval'
                        );
                        $b->online_provider = 'zoom';
                    }
                    $b->online_meeting_url      = $meet['url'] ?? null;
                    $b->online_meeting_code     = $meet['code'] ?? null;
                    $b->online_meeting_password = $meet['password'] ?? null;
                }
                $b->status      = 'approved';
                $b->is_approve  = 1;
                $b->approved_by = Auth::id();
                $b->book_reject = null;
                $b->save();
            });
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Booking disetujui.');
            $this->resetPage('pendingPage');
            $this->resetPage('ongoingPage');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Tidak Bisa Disetujui', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Gagal menyetujui: ' . $e->getMessage());
        }
    }

    public function openReschedule(int $id): void
    {
        $b = BookingRoom::findOrFail($id);

        if ($b->status !== 'approved') {
            $this->dispatch('toast', type: 'warning', title: 'Tidak Bisa', message: 'Hanya booking yang sudah disetujui yang bisa di-reschedule.');
            return;
        }

        $start = $this->buildDt($b->date, $b->start_time);
        $end   = $this->buildDt($b->date, $b->end_time);

        $this->rescheduleId      = $b->bookingroom_id;
        $this->rescheduleDate    = $start->format('Y-m-d');
        $this->rescheduleStart   = $start->format('H:i');
        $this->rescheduleEnd     = $end->format('H:i');
        $this->rescheduleReason  = '';
        $this->rescheduleRoomEnabled = !in_array($b->booking_type, ['online_meeting', 'onlinemeeting']);
        $this->rescheduleRoomId      = $b->room_id ?: null;
        $this->showRescheduleModal = true;
    }

    public function closeReschedule(): void
    {
        $this->showRescheduleModal   = false;
        $this->rescheduleId          = null;
        $this->rescheduleDate        = '';
        $this->rescheduleStart       = '';
        $this->rescheduleEnd         = '';
        $this->rescheduleReason      = '';
        $this->rescheduleRoomId      = null;
        $this->rescheduleRoomEnabled = false;
    }

    public function submitReschedule(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $rules = [
            'rescheduleId'     => 'required|integer|exists:booking_rooms,bookingroom_id',
            'rescheduleDate'   => 'required|date',
            'rescheduleStart'  => 'required|date_format:H:i',
            'rescheduleEnd'    => 'required|date_format:H:i|after:rescheduleStart',
            'rescheduleReason' => 'required|string|min:3|max:500',
            'rescheduleRoomId' => 'nullable|integer|exists:rooms,room_id',
        ];

        $this->validate($rules);

        try {
            DB::transaction(function () {
                $b = BookingRoom::lockForUpdate()->findOrFail($this->rescheduleId);
                $start = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    "{$this->rescheduleDate} {$this->rescheduleStart}",
                    $this->tz
                );
                $end   = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    "{$this->rescheduleDate} {$this->rescheduleEnd}",
                    $this->tz
                );

                if ($end->lte($start)) {
                    throw new \RuntimeException('Waktu tidak valid (end <= start).');
                }
                $roomId = $this->rescheduleRoomId ?: $b->room_id;

                if (!in_array($b->booking_type, ['online_meeting', 'onlinemeeting']) && $roomId) {
                    $overlap = BookingRoom::query()
                        ->where('bookingroom_id', '!=', $b->bookingroom_id)
                        ->where('company_id', $b->company_id)
                        ->where('room_id', $roomId)
                        ->where('date', $this->rescheduleDate)
                        ->whereIn('status', ['pending', 'approved'])
                        ->where('start_time', '<', $end)
                        ->where('end_time', '>', $start)
                        ->exists();
                    if ($overlap) {
                        throw new \RuntimeException('Jadwal baru bentrok dengan booking lain di ruangan & tanggal yang sama.');
                    }
                }
                if ($roomId) {
                    $b->room_id = $roomId;
                }
                $b->date        = $this->rescheduleDate;
                $b->start_time  = $start;
                $b->end_time    = $end;
                $b->book_reject = $this->rescheduleReason;
                $b->updated_at  = Carbon::now($this->tz)->toDateTimeString();
                $b->save();
            });

            $this->showRescheduleModal = false;
            $this->dispatch('toast', type: 'success', title: 'Updated', message: 'Jadwal booking berhasil diubah.');
            $this->resetPage('pendingPage');
            $this->resetPage('ongoingPage');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Tidak Bisa Diubah', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Gagal mengubah jadwal: ' . $e->getMessage());
        }
    }

    private function applyCommonFilters($query, ?int $companyId = null): void
    {
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($this->q !== '') {
            $query->where('meeting_title', 'like', '%' . $this->q . '%');
        }

        $selected = $this->selectedDateValue();
        if ($selected) {
            $query->whereDate('date', $selected);
        }
        if (!is_null($this->roomFilterId)) {
            $roomId = $this->roomFilterId;
            $query->whereHas('room', function ($qr) use ($roomId) {
                $qr->where('room_id', $roomId);
            });
        }
        if ($this->typeScope === 'online') {
            $query->whereIn('booking_type', ['online_meeting', 'onlinemeeting']);
        } elseif ($this->typeScope === 'offline') {
            $query->where(function ($q) {
                $q->whereNull('booking_type')
                  ->orWhereNotIn('booking_type', ['online_meeting', 'onlinemeeting']);
            });
        }

        $this->applyDateTimeOrdering($query);
    }

    public function render()
    {
        $this->autoApprovePending();

        PriorityRoomBooking::autoCompleteApproved(Auth::user()->company_id ?? null);
        PriorityRoomBooking::autoApproveNonClashing(Auth::user()->company_id ?? null);

        $cols = [
            'bookingroom_id', 'meeting_title', 'booking_type', 'online_provider',
            'online_meeting_url', 'online_meeting_code', 'online_meeting_password',
            'status', 'date', 'start_time', 'end_time', 'room_id',
            'user_id', 'department_id', 'approved_by', 'book_reject', 'company_id',
            'created_at', 'updated_at', 'number_of_attendees', 'special_notes',
            'requestinformation',
        ];

        $companyId = Auth::user()->company_id ?? null;

        $now = Carbon::now($this->tz)->toDateTimeString();

        $startExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
            CONCAT(date, ' ', start_time)
        )";

        $endExpr = "COALESCE(
            CASE WHEN end_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN end_time END,
            CASE WHEN date     REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date     END,
            CONCAT(date, ' ', end_time)
        )";

        $pending = BookingRoom::query()
            ->with(['room', 'requirements', 'user.department', 'department'])
            ->where(function ($q) use ($now, $startExpr, $endExpr) {
                $q->where(function ($q1) use ($now, $endExpr) {
                      $q1->where('status', 'pending')
                         ->whereNotNull('date')
                         ->whereNotNull('end_time')
                         ->whereRaw("$endExpr > ?", [$now]);
                  })
                  ->orWhere(function ($q2) use ($now, $startExpr) {
                      $q2->where('status', 'approved')
                         ->whereNotNull('date')
                         ->whereNotNull('start_time')
                         ->whereRaw("$startExpr > ?", [$now]);
                  });
            })
            ->tap(fn($q) => $this->applyCommonFilters($q, $companyId))
            ->paginate($this->perPending, $cols, 'pendingPage');

        $ongoing = BookingRoom::query()
            ->with(['room', 'requirements', 'user.department', 'department'])
            ->where('status', 'approved')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("$startExpr <= ?", [$now])
            ->tap(fn($q) => $this->applyCommonFilters($q, $companyId))
            ->paginate($this->perOngoing, $cols, 'ongoingPage');

        $recentCompletedQuery = BookingRoom::query()
            ->with('room')
            ->whereNotIn('status', ['pending', 'approved', 'rejected']);

        if ($companyId) {
            $recentCompletedQuery->where('company_id', $companyId);
        }

        if (!is_null($this->roomFilterId)) {
            $roomId = $this->roomFilterId;
            $recentCompletedQuery->whereHas('room', function ($qr) use ($roomId) {
                $qr->where('room_id', $roomId);
            });
        }

        $recentCompleted = $recentCompletedQuery
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get($cols);

        return view('livewire.pages.receptionist.bookings-approval', compact(
            'pending',
            'ongoing',
            'recentCompleted'
        ) + [
            'zoomConfigured'              => $this->zoomConfigured,
            'googleConnected'             => $this->googleConnected,
            'roomNotifCount'              => $this->roomNotifCount,
            'roomNotifs'                  => $this->roomNotifs,
        
            'priorityRoomPending'         => PriorityRoomBooking::with(['room', 'manager'])
                ->forCompany($companyId)
                ->where(function ($q) {
                    $today     = now()->toDateString();
                    $timeNow   = now()->format('H:i:s');
                    $q->whereIn('status', [
                        PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                        PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                    ])->orWhere(function ($q2) use ($today, $timeNow) {
                        $q2->where('status', PriorityRoomBooking::STATUS_APPROVED)
                           ->where(function ($q3) use ($today, $timeNow) {
                               $q3->where('date', '>', $today)
                                  ->orWhere(function ($q4) use ($today, $timeNow) {
                                      $q4->where('date', $today)
                                         ->where('start_time', '>', $timeNow);
                                  });
                           });
                    });
                })
                ->orderByDesc('created_at')
                ->get(),
            'priorityRoomApproved'        => PriorityRoomBooking::with(['room', 'manager'])
                ->forCompany($companyId)
                ->where('status', PriorityRoomBooking::STATUS_APPROVED)
                ->where(function ($q) {
                    $today   = now()->toDateString();
                    $timeNow = now()->format('H:i:s');
                    $q->where('date', '<', $today)
                      ->orWhere(function ($q2) use ($today, $timeNow) {
                          $q2->where('date', $today)
                             ->where('start_time', '<=', $timeNow);
                      });
                })
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'priorityRoomDetailBooking'   => $this->priorityRoomDetailBooking,
        ]);
    }

    public bool $showRoomNotifPanel          = false;
    public bool $showRoomPriorityApprovalModal = false;
    public ?int $roomPriorityNotifId          = null;
    public ?int $roomPriorityBookingId        = null;
    public bool $showPriorityRoomDetailModal = false;
    public ?int $priorityRoomDetailId        = null;

    public function openPriorityRoomDetail(int $id): void
    {
        $booking = \App\Models\PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking'])
            ->find($id);

        if (!$booking) {
            $this->dispatch('toast', type: 'error', title: 'Not Found', message: 'Priority booking #' . $id . ' not found.');
            return;
        }

        $this->priorityRoomDetailId        = $id;
        $this->showPriorityRoomDetailModal = true;
    }

    public function closePriorityRoomDetail(): void
    {
        $this->showPriorityRoomDetailModal = false;
        $this->priorityRoomDetailId        = null;
    }

    public function takeActionFromDetail(int $priorityBookingId): void
    {
        $this->closePriorityRoomDetail();
        $this->openRoomPriorityApprovalByBookingId($priorityBookingId);
    }

    public function getPriorityRoomDetailBookingProperty(): ?\App\Models\PriorityRoomBooking
    {
        if (!$this->priorityRoomDetailId) return null;
        return \App\Models\PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking'])
            ->find($this->priorityRoomDetailId);
    }

    public function toggleRoomNotifPanel(): void
    {
        $this->showRoomNotifPanel = !$this->showRoomNotifPanel;
    }

    public function closeRoomNotifPanel(): void
    {
        $this->showRoomNotifPanel = false;
    }

    public function openRoomPriorityApprovalModal(int $notifId): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $notif = ManagerNotification::where('id', $notifId)
            ->where('company_id', $companyId)
            ->where('action_required', true)
            ->whereNull('action_taken')
            ->first();

        if (!$notif) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found', message: 'Notification not found or already actioned.');
            return;
        }

        $this->roomPriorityNotifId          = $notifId;
        $this->roomPriorityBookingId        = $notif->notifiable_id;
        $this->showRoomPriorityApprovalModal = true;
        $this->showRoomNotifPanel           = false;
        $notif->markRead();
    }

    public function openRoomPriorityApprovalByBookingId(int $priorityBookingId): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $priority = PriorityRoomBooking::where('id', $priorityBookingId)
            ->where('company_id', $companyId)
            ->whereIn('status', [
                PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
            ])
            ->first();

        if (!$priority) {
            $this->dispatch('toast', type: 'warning', title: 'Not Found', message: 'Priority booking not found or already handled.');
            return;
        }

        if ($priority->status === PriorityRoomBooking::STATUS_PENDING_RECEIPT) {
            $this->roomPriorityNotifId   = null;
            $this->roomPriorityBookingId = $priorityBookingId;
            $this->approveRoomPriority();
            return;
        }

        $this->roomPriorityNotifId          = null; 
        $this->roomPriorityBookingId        = $priorityBookingId;
        $this->showRoomPriorityApprovalModal = true;
        $this->showRoomNotifPanel           = false;
    }

    public function closeRoomPriorityApprovalModal(): void
    {
        $this->showRoomPriorityApprovalModal = false;
        $this->roomPriorityNotifId           = null;
        $this->roomPriorityBookingId         = null;
    }

    public function approveRoomPriority(): void
    {
        if (!$this->roomPriorityBookingId) return;

        $companyId = Auth::user()->company_id ?? null;
        $hadConflict = false;

        try {
            DB::transaction(function () use ($companyId, &$hadConflict) {
                $priority = PriorityRoomBooking::where('id', $this->roomPriorityBookingId)
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                if ($priority->cancels_booking_id) {
                    $hadConflict = true;
                    BookingRoom::where('bookingroom_id', $priority->cancels_booking_id)
                        ->whereIn('status', ['pending', 'approved', 'completed', 'done', '1', '3'])
                        ->whereNotIn('booking_type', ['online_meeting', 'onlinemeeting'])
                        ->update([
                            'status'      => 'rejected',
                            'book_reject' => 'Cancelled — superseded by manager priority booking #' . $this->roomPriorityBookingId . '.',
                            'approved_by' => Auth::user()->user_id,
                        ]);
                }

                $priority->update([
                    'status'     => PriorityRoomBooking::STATUS_APPROVED,
                    'handled_by' => Auth::user()->user_id,
                ]);

                if ($this->roomPriorityNotifId) {
                    ManagerNotification::where('id', $this->roomPriorityNotifId)
                        ->update(['action_taken' => 'approved', 'is_read' => true]);
                }

                ManagerNotification::where('notifiable_id', $this->roomPriorityBookingId)
                    ->whereIn('type', [
                        ManagerNotification::TYPE_ROOM_CANCEL_REQUEST,
                        ManagerNotification::TYPE_PRIORITY_ROOM_DIRECT,
                    ])
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'approved', 'is_read' => true]);
            });

            $message = $hadConflict
                ? 'Priority booking approved and conflicting booking cancelled.'
                : 'Priority booking approved.';

            $this->dispatch('toast', type: 'success', title: 'Approved', message: $message);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed: ' . $e->getMessage());
        }

        $this->closeRoomPriorityApprovalModal();
        $this->resetPage('pendingPage');
        $this->resetPage('ongoingPage');
    }

    public function denyRoomPriority(): void
    {
        if (!$this->roomPriorityBookingId) return;

        $companyId = Auth::user()->company_id ?? null;

        try {
            DB::transaction(function () use ($companyId) {
                PriorityRoomBooking::where('id', $this->roomPriorityBookingId)
                    ->where('company_id', $companyId)
                    ->update([
                        'status'           => PriorityRoomBooking::STATUS_CONFLICT_DENIED,
                        'handled_by'       => Auth::user()->user_id,
                        'rejection_reason' => 'Cancellation request denied by receptionist.',
                    ]);

                if ($this->roomPriorityNotifId) {
                    ManagerNotification::where('id', $this->roomPriorityNotifId)
                        ->update(['action_taken' => 'denied', 'is_read' => true]);
                }

                ManagerNotification::where('notifiable_id', $this->roomPriorityBookingId)
                    ->whereIn('type', [
                        ManagerNotification::TYPE_ROOM_CANCEL_REQUEST,
                        ManagerNotification::TYPE_PRIORITY_ROOM_DIRECT,
                    ])
                    ->whereNull('action_taken')
                    ->update(['action_taken' => 'denied', 'is_read' => true]);
            });

            $this->dispatch('toast', type: 'info', title: 'Denied', message: 'Cancellation request denied. Original booking kept.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed: ' . $e->getMessage());
        }

        $this->closeRoomPriorityApprovalModal();
    }

    public function getRoomNotifCountProperty(): int
    {
        $user = Auth::user();
        if (!$user) return 0;
        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id ?? 0)
            ->where('type', ManagerNotification::TYPE_ROOM_CANCEL_REQUEST)
            ->where('is_read', false)
            ->count();
    }

    public function getRoomNotifsProperty()
    {
        $user = Auth::user();
        if (!$user) return collect();
        return ManagerNotification::where('company_id', $user->company_id ?? 0)
            ->where('recipient_id', $user->user_id ?? 0)
            ->whereIn('type', [
                ManagerNotification::TYPE_ROOM_CANCEL_REQUEST,
                ManagerNotification::TYPE_PRIORITY_ROOM_DIRECT,
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }
}
