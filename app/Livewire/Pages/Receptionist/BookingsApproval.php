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

#[Layout('layouts.receptionist')]
#[Title('Bookings Approval')]
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
            return BookingRoom::query()
                ->where('status', 'approved')
                ->whereNotNull('date')
                ->whereNotNull('end_time')
                ->whereRaw("$endExpr IS NOT NULL")
                ->whereRaw("$endExpr <= ?", [$now])
                ->update([
                    'status'     => 'completed',
                    'updated_at' => Carbon::now($this->tz)->toDateTimeString(),
                ]);
        });
    }

    /**
     * Auto-approve pending offline and online bookings whose start time has arrived.
     * Mirrors the AutoApproveBookings command so it also works without a scheduler.
     * Online bookings are approved directly without link creation (links are created
     * at manual approval time, or by the scheduler if configured).
     */
    private function autoApprovePending(): void
    {
        $now       = Carbon::now($this->tz)->toDateTimeString();
        $companyId = Auth::user()->company_id ?? null;

        $startExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} ' THEN date END,
            CONCAT(date, ' ', start_time)
        )";

        DB::table('booking_rooms')
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereRaw("$startExpr <= ?", [$now])
            ->update([
                'status'     => 'approved',
                'is_approve' => 1,
                'updated_at' => $now,
            ]);
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

        $invalidFirstExpr = "CASE
            WHEN (date IS NULL OR start_time IS NULL) THEN 1
            ELSE 0
        END";

        $dtExpr = "COALESCE(
            CASE WHEN start_time REGEXP '^[0-9]{4}-' THEN start_time END,
            CASE WHEN date       REGEXP '^[0-9]{4}-' THEN date END,
            CONCAT(date, ' ', start_time)
        )";

        return $query->orderByRaw("$invalidFirstExpr ASC")
            ->orderByRaw("$dtExpr $dir")
            ->orderByDesc('created_at');
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

    // ─────────────────── Detail Modal ────────────────────

    /**
     * Computed property to retrieve the selected BookingRoom model instance with requirements and room.
     */
    public function getBookingDetailProperty(): ?BookingRoom
    {
        if ($this->selectedBookingId) {
            return BookingRoom::with(['room', 'requirements', 'user.department', 'department'])
                ->find($this->selectedBookingId);
        }
        return null;
    }

    /**
     * Open the detail modal and fetch the selected booking detail.
     */
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


    /**
     * Close the detail modal.
     */
    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
        $this->selectedBookingDetail = null;
    }

    // ─────────────────── Reject ────────────────────

    public function openReject(int $id): void
    {
        $this->rejectId        = $id;
        $this->rejectReason    = '';
        $this->showRejectModal = true;
    }

    /**
     * FIX: Added the missing public method.
     */
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

                // Enforce 30-minute rejection threshold
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

    // ─────────────────── Approve ────────────────────

    public function approve(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                /** @var BookingRoom $b */
                $b = BookingRoom::lockForUpdate()->findOrFail($id);

                // OFFLINE checks
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

                // ONLINE: create link on approval if missing
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

                // Approve
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

    // ─────────────── Cancel / Reschedule ───────────────

    public function openReschedule(int $id): void
    {
        /** @var BookingRoom $b */
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
        $rules = [
            'rescheduleId'     => 'required|integer|exists:booking_rooms,bookingroom_id',
            'rescheduleDate'   => 'required|date',
            'rescheduleStart'  => 'required|date_format:H:i',
            'rescheduleEnd'    => 'required|date_format:H:i|after:rescheduleStart',
            'rescheduleReason' => 'required|string|min:3|max:500',
            // room opsional, tapi kalau diisi harus valid
            'rescheduleRoomId' => 'nullable|integer|exists:rooms,room_id',
        ];

        $this->validate($rules);

        try {
            DB::transaction(function () {
                /** @var BookingRoom $b */
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

                // Kalau user pilih room baru, pakai itu; kalau tidak, pakai room lama
                $roomId = $this->rescheduleRoomId ?: $b->room_id;

                // Cek bentrok hanya untuk booking offline
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

    // ─────────────── Data & render ───────────────

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

        // ROOM FILTER via relation room()
        if (!is_null($this->roomFilterId)) {
            $roomId = $this->roomFilterId;
            $query->whereHas('room', function ($qr) use ($roomId) {
                $qr->where('room_id', $roomId);
            });
        }

        // Type scope filter: online / offline / all
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
        $this->autoProgressToCompleted();

        $cols = [
            'bookingroom_id', 'meeting_title', 'booking_type', 'online_provider',
            'online_meeting_url', 'online_meeting_code', 'online_meeting_password',
            'status', 'date', 'start_time', 'end_time', 'room_id',
            'user_id', 'department_id', 'approved_by', 'book_reject', 'company_id',
            'created_at', 'updated_at', 'number_of_attendees', 'special_notes',
            'requestinformation',
        ];

        $companyId = Auth::user()->company_id ?? null;

        $pending = BookingRoom::query()
            ->with(['room', 'requirements', 'user.department', 'department'])
            ->where('status', 'pending')
            ->tap(fn($q) => $this->applyCommonFilters($q, $companyId))
            ->paginate($this->perPending, $cols, 'pendingPage');

        $ongoing = BookingRoom::query()
            ->with(['room', 'requirements', 'user.department', 'department'])
            ->where('status', 'approved')
            ->tap(fn($q) => $this->applyCommonFilters($q, $companyId))
            ->paginate($this->perOngoing, $cols, 'ongoingPage');

        // Recent activity: semua status kecuali pending, approved(ongoing), dan rejected
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
            'zoomConfigured'   => $this->zoomConfigured,
            'googleConnected'  => $this->googleConnected,
        ]);
    }
}
