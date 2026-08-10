<?php

namespace App\Livewire\Pages\Receptionist;

use App\Models\Requirement;
use App\Services\GoogleMeetService;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.receptionist')]
#[Title('Meeting Schedule')]
class MeetingSchedule extends Component
{
    private const INITIAL_STATUS = 'pending';
    private string $tz = 'Asia/Jakarta';
    public ?int $editingId = null;
    public bool $showOfflineForm = false;
    public bool $showOnlineForm = false;
    public bool $showScheduleModal = false;
    public ?int $selectedRoomForSchedule = null;
    public array $roomScheduleData = [];
    public bool $showBookingDetailModal = false;
    public array $selectedBookingDetail = [];
    public bool $showRoomRejectModal = false;
    public ?int $roomRejectId = null;
    public string $roomRejectReason = '';
    public array $form = [
        'meeting_title' => null,
        'room_id'       => null,
        'department_id' => null,
        'date'          => null,
        'time'          => null,
        'time_end'      => null,
        'participant'   => null,
        'notes'         => null,
        'requirements'  => [], 
    ];
    public ?int $offline_user_id = null;
    public array $usersByDeptOffline = [];
    public string $userQueryOffline = '';
    public string $online_meeting_title = '';
    public string $online_platform = 'google_meet';
    public ?string $online_date = null;
    public ?string $online_start_time = null;
    public ?string $online_end_time = null;
    public ?int $online_department_id = null;
    public ?int $online_user_id = null;
    public array $usersByDept = [];
    public string $userQueryOnline = '';
    public bool $googleConnected = false;
    public array $departments = [];
    public array $requirementOptions = [];
    public string $deptQueryOffline = '';
    public string $deptQueryOnline  = '';
    public ?int $otherRequirementId = null;


    public function mount(): void
    {
        if (Requirement::count() === 0 && method_exists(Requirement::class, 'upsertByName')) {
            Requirement::upsertByName(['Video Conference', 'Projector', 'Whiteboard', 'Catering', 'Other']);
        }

        $this->loadDepartments();
        $this->loadRequirements();
        $this->otherRequirementId = $this->getOtherRequirementId();

        $this->googleConnected    = $this->detectGoogleConnected();

        if ($this->online_department_id) {
            $this->loadUsersForDept((int) $this->online_department_id);
        }
        if (!empty($this->form['department_id'])) {
            $this->loadUsersForDeptOffline((int) $this->form['department_id']);
        }
    }

    protected function pickColumn(string $table, array $candidates, string $fallback): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }
        return $fallback;
    }
    
    protected function loadDepartments(): void
    {
        $nameCol = $this->pickColumn('departments', ['department_name', 'name'], 'department_name');
        $pkCol   = $this->pickColumn('departments', ['department_id', 'id'], 'department_id');

        $dept = DB::table('departments')
            ->selectRaw("$pkCol as id, $nameCol as label")
            ->when(Auth::user()?->company_id, fn($q, $cid) => $q->where('company_id', $cid))
            ->orderBy($nameCol)
            ->get();

        $this->departments = $dept->map(fn($d) => [
            'id'   => (int) $d->id,
            'name' => (string) $d->label,
        ])->all();
    }

    protected function loadRooms(): array
    {
        $pkCol    = $this->pickColumn('rooms', ['room_id', 'id'], 'room_id');
        $labelCol = $this->pickColumn('rooms', ['room_name', 'room_number', 'name'], 'room_name');
        $hasCapacity = Schema::hasColumn('rooms', 'capacity');
        $selectRaw   = $hasCapacity
            ? "$pkCol as id, $labelCol as label, capacity"
            : "$pkCol as id, $labelCol as label";

        $rooms = DB::table('rooms')
            ->selectRaw($selectRaw)
            ->whereNull('deleted_at')
            ->when(Auth::user()?->company_id, fn($q, $cid) => $q->where('company_id', $cid))
            ->orderBy($labelCol)
            ->get();

        return $rooms->map(fn($r) => [
            'id'       => (int) $r->id,
            'name'     => (string) $r->label,
            'capacity' => isset($r->capacity) && $r->capacity !== null ? (int) $r->capacity : null,
        ])->all();
    }

    protected function getSelectedRoomCapacity(array $rooms): ?int
    {
        $roomId = (int) ($this->form['room_id'] ?? 0);
        if ($roomId === 0) {
            return null;
        }

        foreach ($rooms as $room) {
            if ((int) $room['id'] === $roomId) {
                return $room['capacity'];
            }
        }

        return null;
    }

    protected function loadRequirements(): void
    {
        $idCol = $this->pickColumn('requirements', ['requirement_id', 'id'], 'requirement_id');
        $nmCol = $this->pickColumn('requirements', ['name', 'requirement_name'], 'name');

        $cid = Auth::user()?->company_id;

        $this->requirementOptions = DB::table('requirements')
            ->selectRaw("$idCol as id, $nmCol as name")
            ->when($cid, fn($q) => $q->where('company_id', $cid))
            ->orderBy($nmCol)
            ->get()
            ->map(fn($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])
            ->all();
    }
    
    protected function getOtherRequirementId(): ?int
    {
        $idCol = $this->pickColumn('requirements', ['requirement_id', 'id'], 'requirement_id');
        $nmCol = $this->pickColumn('requirements', ['name', 'requirement_name'], 'name');
        $cid = Auth::user()?->company_id;

        $row = DB::table('requirements')
            ->select($idCol . ' as id', $nmCol . ' as name')
            ->when($cid, fn($q) => $q->where('company_id', $cid))
            ->whereRaw('LOWER(' . $nmCol . ') = ?', ['other'])
            ->first();

        return $row ? (int) $row->id : null;
    }


    protected function loadUsersForDept(?int $deptId): void
    {
        $this->usersByDept = $this->queryUsersForDropdown($deptId, null);
    }

    protected function loadUsersForDeptOffline(?int $deptId): void
    {
        $this->usersByDeptOffline = $this->queryUsersForDropdown($deptId, null);
    }

    private function queryUsersForDropdown(?int $deptId, ?string $search, int $limit = 50): array
    {
        if (!$deptId) return [];

        $pk   = $this->pickColumn('users', ['user_id', 'id'], 'user_id');
        $name = $this->pickColumn('users', ['name', 'full_name', 'fullname'], 'name');

        $cid  = Auth::user()?->company_id;

        $q = DB::table('users')
            ->selectRaw("$pk as id, $name as label")
            ->where('department_id', $deptId);

        if ($cid && Schema::hasColumn('users', 'company_id')) {
            $q->where('company_id', $cid);
        }

        if ($search !== null && $search !== '') {
            $needle = trim($search);
            $q->where(function ($qq) use ($name, $needle) {
                $qq->where($name, 'like', "%{$needle}%");
            })
                ->orderByRaw("CASE WHEN $name LIKE ? THEN 0 ELSE 1 END", ["{$needle}%"])
                ->orderBy($name)
                ->limit($limit);
        } else {
            $q->orderBy($name)->limit($limit);
        }

        return $q->get()
            ->map(fn($u) => ['id' => (int)$u->id, 'name' => (string)$u->label])
            ->all();
    }
    
    public function updatedDeptQueryOffline(): void
    {
        // Re-render will filter $departmentsOffline via render() automatically.
    }

    public function updatedDeptQueryOnline(): void
    {
        // Re-render will filter $departmentsOnline via render() automatically.
    }

    public function updatedOnlineDepartmentId($val): void
    {
        $deptId = (int) ($val ?: 0);
        if (trim($this->userQueryOnline) !== '') {
            $this->usersByDept = $this->queryUsersForDropdown($deptId, $this->userQueryOnline);
        } else {
            $this->loadUsersForDept($deptId);
        }
        if ($this->online_user_id && !collect($this->usersByDept)->pluck('id')->contains((int)$this->online_user_id)) {
            $this->online_user_id = null;
        }
    }

    public function updatedFormDepartmentId($val): void
    {
        $deptId = (int) ($val ?: 0);
        if (trim($this->userQueryOffline) !== '') {
            $this->usersByDeptOffline = $this->queryUsersForDropdown($deptId, $this->userQueryOffline);
        } else {
            $this->loadUsersForDeptOffline($deptId);
        }
        if ($this->offline_user_id && !collect($this->usersByDeptOffline)->pluck('id')->contains((int)$this->offline_user_id)) {
            $this->offline_user_id = null;
        }
    }

    public function updatedUserQueryOffline($q): void
    {
        $deptId = (int) ($this->form['department_id'] ?: 0);
        if ($deptId === 0) {
            $this->usersByDeptOffline = [];
            return;
        }

        $this->usersByDeptOffline = $this->queryUsersForDropdown($deptId, $q);
        $first = $this->usersByDeptOffline[0] ?? null;
        if ($first && mb_strtolower($first['name']) === mb_strtolower(trim($q))) {
            $this->offline_user_id = $first['id'];
        }
    }

    public function updatedUserQueryOnline($q): void
    {
        $deptId = (int) ($this->online_department_id ?: 0);
        if ($deptId === 0) {
            $this->usersByDept = [];
            return;
        }

        $this->usersByDept = $this->queryUsersForDropdown($deptId, $q);

        $first = $this->usersByDept[0] ?? null;
        if ($first && mb_strtolower($first['name']) === mb_strtolower(trim($q))) {
            $this->online_user_id = $first['id'];
        }
    }

    public function updated($name): void
    {
        if ($name === 'online_department_id') {
            $this->updatedOnlineDepartmentId($this->online_department_id);
        }
        if ($name === 'form.department_id') {
            $this->updatedFormDepartmentId($this->form['department_id']);
        }
        if ($name === 'form.requirements') {
            $this->resetValidation('form.notes');
        }
    }

    protected function rules(): array
    {
        $deptPk = $this->pickColumn('departments', ['department_id', 'id'], 'department_id');
        $roomPk = $this->pickColumn('rooms', ['room_id', 'id'], 'room_id');

        return [
            'form.meeting_title' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]*$/'],
            'form.room_id'       => ['required', 'integer', "exists:rooms,{$roomPk}"],
            'form.department_id' => ['required', 'integer', "exists:departments,{$deptPk}"],
            'form.date'          => ['required', 'date_format:Y-m-d'],
            'form.time'          => ['required', 'date_format:H:i'],
            'form.time_end'      => ['required', 'date_format:H:i', 'after:form.time'],
            'form.participant'   => ['required', 'integer', 'min:1'],
            'form.notes'         => ['nullable', 'string', 'max:1000', 'regex:/^[a-zA-Z0-9\s]*$/'],
            'form.requirements'  => ['array'],
            'form.requirements.*' => ['nullable', 'sometimes', 'distinct', function ($attribute, $value, $fail) {
                if (!is_numeric($value) && $value !== 'Other') {
                    $fail('The selected requirement is invalid.');
                }
            }],
        ];
    }

    protected function hasRoomOverlap(int $roomId, string $ymd, string $startAt, string $endAt, ?int $excludeId = null): bool
    {
        $pendingApproved = ['pending', 'approved', 0, 1, '0', '1', 'PENDING', 'APPROVED'];
        $pkCol = $this->pickColumn('booking_rooms', ['bookingroom_id', 'id'], 'bookingroom_id'); 

        return DB::table('booking_rooms')
            ->where('room_id', $roomId)
            ->where('date', $ymd)
            ->whereIn('status', $pendingApproved)
            ->when($excludeId, fn($q) => $q->where($pkCol, '!=', $excludeId))
            ->where('start_time', '<', $endAt) 
            ->where('end_time',   '>', $startAt)
            ->exists();
    }

    private function toDateTime(string $ymd, string $hm): string
    {
        return Carbon::createFromFormat('Y-m-d H:i', "$ymd $hm", $this->tz)->format('Y-m-d H:i:s');
    }

    private function validateNotesIfOther(): void
    {
        if (in_array('Other', $this->form['requirements'] ?? [], true)) {
            $this->validate(['form.notes' => 'required|string|min:2|max:1000']);
        }
    }
    
    private function parseRequirementsForSave(array $ids, string $notes): array
    {
        $requirementIds = [];
        $specialNotes = trim((string) $notes); 

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $requirementIds[] = (int)$id;
            }
        }
        if (in_array('Other', $ids, true) && $this->otherRequirementId) {
            $requirementIds[] = $this->otherRequirementId;
        }
        $requirementIds = array_values(array_unique(array_filter($requirementIds, fn($v) => $v !== null)));
        return [$requirementIds, $specialNotes];
    }

    public function saveOffline(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $this->validate();
        $this->validateNotesIfOther();
        $roomsForValidation = $this->loadRooms();
        $roomCapacity       = $this->getSelectedRoomCapacity($roomsForValidation);

        if ($roomCapacity !== null && (int) $this->form['participant'] > $roomCapacity) {
            $this->addError(
                'form.participant',
                "The number of attendees exceeds the maximum occupancy limit for this room (Maximum: {$roomCapacity} people)."
            );
            return;
        }

        $cid = Auth::user()?->company_id;

        $targetUserId = $this->offline_user_id
            ? (int) $this->offline_user_id
            : (Auth::user()?->user_id ?? Auth::id());

        try {
            $startAt = $this->toDateTime((string)$this->form['date'], (string)$this->form['time']);
            $endAt   = $this->toDateTime((string)$this->form['date'], (string)$this->form['time_end']);
        } catch (\Throwable) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Invalid date or time format.', duration: 3000);
            return;
        }

        if ($endAt <= $startAt) {
            $this->dispatch('toast', type: 'error', title: 'Waktu salah', message: 'Jam selesai harus setelah jam mulai.', duration: 3000);
            return;
        }

        $startCarbon = Carbon::parse($startAt, $this->tz);
        $selectedRoom = \App\Models\Room::select('room_id', 'requires_early_approval')
            ->find((int) $this->form['room_id']);
        $requiresEarlyApproval = $selectedRoom?->requires_early_approval ?? true;

        if ($requiresEarlyApproval) {
            $minAdvanceDate = now($this->tz)->startOfMinute()->addHour();
            if ($startCarbon->lessThan($minAdvanceDate)) {
                $this->dispatch(
                    'toast',
                    type: 'error',
                    title: 'Minimum Booking Limit',
                    message: 'Bookings must be made at least 1 hour in advance.',
                    duration: 7000
                );
                return;
            }
        }

        $maxAdvanceDate = now($this->tz)->addMonths(1);
        if ($startCarbon->greaterThan($maxAdvanceDate)) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Booking Limit Exceeded',
                message: 'Booking can only be made up to 1 month in advance.',
                duration: 7000
            );
            return;
        }

        if ($this->hasRoomOverlap((int)$this->form['room_id'], (string)$this->form['date'], $startAt, $endAt, $this->editingId)) {
            $humanStart = Carbon::parse($startAt, $this->tz)->format('d M Y H:i');
            $humanEnd   = Carbon::parse($endAt,   $this->tz)->format('H:i');
            $this->dispatch('toast', type: 'error', title: 'Jadwal bentrok', message: "Ruangan sudah dibooking pada waktu {$humanStart} - {$humanEnd}. Silakan pilih waktu lain.", duration: 5000);
            return;
        }
        
        [$reqIds, $specialNotes] = $this->parseRequirementsForSave($this->form['requirements'], (string)($this->form['notes'] ?? ''));

        DB::beginTransaction();
        try {
            $bookingRoomPk = $this->pickColumn('booking_rooms', ['bookingroom_id', 'id'], 'bookingroom_id');

            $bookingId = DB::table('booking_rooms')->insertGetId([ 
                'room_id'              => (int)$this->form['room_id'],
                'company_id'           => $cid,
                'user_id'              => $targetUserId,
                'department_id'        => (int)$this->form['department_id'],
                'meeting_title'        => (string)$this->form['meeting_title'],
                'date'                 => (string)$this->form['date'],
                'start_time'           => $startAt,
                'end_time'             => $endAt,
                ...(Schema::hasColumn('booking_rooms', 'number_of_attendees')
                    ? ['number_of_attendees' => (int)$this->form['participant']]
                    : (Schema::hasColumn('booking_rooms', 'participant') ? ['participant' => (int)$this->form['participant']] : [])
                ),
                ...(Schema::hasColumn('booking_rooms', 'special_notes')
                    ? ['special_notes' => $specialNotes]
                    : (Schema::hasColumn('booking_rooms', 'notes') ? ['notes' => $specialNotes] : [])
                ),
                'booking_type'         => 'meeting',
                'status'               => self::INITIAL_STATUS,
                'requestinformation'   => null,
                ...(Schema::hasColumn('booking_rooms', 'is_approve') ? ['is_approve' => 0] : []),
                'created_at'           => now(),
                'updated_at'           => now(),
            ], $bookingRoomPk); 

            if (!empty($reqIds)) {
                $pivotData = collect($reqIds)->map(fn($reqId) => [
                    $bookingRoomPk => $bookingId, 
                    $this->pickColumn('requirements', ['requirement_id', 'id'], 'requirement_id') => $reqId,
                ])->all();
                DB::table('booking_requirements')->insert($pivotData); 
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Gagal menyimpan booking. ' . $e->getMessage(), duration: 5000);
            return;
        }
        $this->resetOfflineForm();
        $this->dispatch('toast', type: 'success', title: 'Sukses', message: 'Meeting offline disimpan.', duration: 3000);
    }

    public function saveOnline(): void
    {
        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename($this), method_exists($this, 'all') ? $this->all() : []);

        $data = $this->validate([
            'online_meeting_title' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]*$/'],
            'online_platform'      => ['required', Rule::in(['google_meet', 'zoom'])],
            'online_date'          => ['required', 'date_format:Y-m-d'],
            'online_start_time'    => ['required', 'date_format:H:i'],
            'online_end_time'      => ['required', 'date_format:H:i', 'after:online_start_time'],
            'online_department_id' => ['nullable', 'integer'],
            'online_user_id'       => ['nullable', 'integer'],
        ]);

        $cid = Auth::user()?->company_id;

        $targetUserId = $this->online_user_id
            ? (int) $this->online_user_id
            : (Auth::user()?->user_id ?? Auth::id());

        try {
            $startAt = $this->toDateTime($data['online_date'], $data['online_start_time']);
            $endAt   = $this->toDateTime($data['online_date'], $data['online_end_time']);
        } catch (\Throwable) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Invalid date or time format.', duration: 3000);
            return;
        }

        if ($endAt <= $startAt) {
            $this->dispatch('toast', type: 'error', title: 'Waktu salah', message: 'Jam selesai harus setelah jam mulai.', duration: 3000);
            return;
        }

        $startCarbon = Carbon::parse($startAt, $this->tz);
        $maxAdvanceDate = now($this->tz)->addMonths(1);
        if ($startCarbon->greaterThan($maxAdvanceDate)) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Booking Limit Exceeded',
                message: 'Booking can only be made up to 1 month in advance.',
                duration: 7000
            );
            return;
        }

        $meetingUrl = null;
        $meetingCode = null;
        $meetingPassword = null;
        $meetingEventId = null;
        $linkWarning = false;

        $startCarbon = Carbon::parse($startAt, $this->tz);
        $endCarbon   = Carbon::parse($endAt, $this->tz);
        $provider    = $data['online_platform'];
        $isGoogle    = str_starts_with($provider, 'google');

        try {
            if ($isGoogle) {
                $svc = app(GoogleMeetService::class);
                if ($svc->isConnected()) {
                    $meet = $svc->createMeet(
                        $data['online_meeting_title'],
                        $startCarbon,
                        $endCarbon,
                        'Created from KRBS booking'
                    );
                    $meetingUrl      = $meet['url'] ?? null;
                    $meetingCode     = $meet['code'] ?? null;
                    $meetingPassword = $meet['password'] ?? null;
                    $meetingEventId  = $meet['event_id'] ?? null;
                } else {
                    $linkWarning = true;
                    Log::warning('GoogleMeetService: Service account not connected, skipping link creation.');
                }
            } else {
                $zoomSvc = app(ZoomService::class);
                if ($zoomSvc->isConfigured()) {
                    $meet = $zoomSvc->createMeeting(
                        $data['online_meeting_title'],
                        $startCarbon,
                        $endCarbon,
                        'Created from KRBS booking'
                    );
                    $meetingUrl      = $meet['url'] ?? null;
                    $meetingCode     = $meet['code'] ?? null;
                    $meetingPassword = $meet['password'] ?? null;
                    $meetingEventId  = $meet['code'] ?? null;
                } else {
                    $linkWarning = true;
                    Log::warning('ZoomService: Credentials not configured, skipping link creation.');
                }
            }
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            if ($e instanceof \Google\Service\Exception) {
                $errDecoded = json_decode($e->getMessage(), true);
                if (isset($errDecoded['error']['message'])) {
                    $errorMsg = "Google Meet Error: " . $errDecoded['error']['message'];
                    if (str_contains($errorMsg, 'Invalid conference type')) {
                        $errorMsg .= " (Service Accounts need GOOGLE_IMPERSONATE_EMAIL with Domain-Wide Delegation enabled)";
                    }
                }
            }

            Log::error('Failed to create meeting link on submission: ' . $errorMsg);
            $this->dispatch('toast', type: 'error', title: 'Integration Error', message: $errorMsg, duration: 8000);
            return; 
        }

        $bookingRoomPk = $this->pickColumn('booking_rooms', ['bookingroom_id', 'id'], 'bookingroom_id');

        $bookingId = DB::table('booking_rooms')->insertGetId([
            'company_id'              => $cid,
            'user_id'                 => $targetUserId,
            'department_id'           => $this->online_department_id,
            'meeting_title'           => $data['online_meeting_title'],
            'booking_type'            => 'online_meeting',
            'status'                  => self::INITIAL_STATUS,
            ...(Schema::hasColumn('booking_rooms', 'is_approve') ? ['is_approve' => 0] : []),
            'date'                    => $data['online_date'],
            'start_time'              => $startAt,
            'end_time'                => $endAt,
            'online_provider'         => $provider,
            'online_meeting_url'      => $meetingUrl,
            'online_meeting_code'     => $meetingCode,
            'online_meeting_password' => $meetingPassword,
            'online_meeting_event_id' => $meetingEventId,
            'requestinformation'      => null,
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $bookingRoomPk);

        $this->resetOnlineForm();

        $this->dispatch('toast', type: 'success', title: 'Sukses', message: 'Meeting online disimpan dengan link meeting.', duration: 3000);
    }

    public function approveRoomBooking(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $b = \App\Models\BookingRoom::lockForUpdate()->findOrFail($id);
                if ($b->status !== 'pending') {
                    throw new \RuntimeException("Booking #{$b->bookingroom_id} is not pending.");
                }
                $b->status     = 'approved';
                $b->is_approve = 1;
                $b->approved_by = \Illuminate\Support\Facades\Auth::id();
                $b->save();
            });
            if ($this->selectedRoomForSchedule) {
                $this->openScheduleModal($this->selectedRoomForSchedule);
            }
            $this->showBookingDetailModal = false;
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Room booking has been approved.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    }

    public function openRoomReject(int $id): void
    {
        $this->roomRejectId        = $id;
        $this->roomRejectReason    = '';
        $this->showRoomRejectModal = true;
        $this->showBookingDetailModal = false;
    }

    public function closeRoomReject(): void
    {
        $this->showRoomRejectModal = false;
        $this->roomRejectId        = null;
        $this->roomRejectReason    = '';
    }

    public function confirmRoomReject(): void
    {
        $this->validate([
            'roomRejectId'     => 'required|integer|exists:booking_rooms,bookingroom_id',
            'roomRejectReason' => 'required|string|min:3|max:500',
        ]);

        try {
            DB::transaction(function () {
                $b = \App\Models\BookingRoom::lockForUpdate()->findOrFail($this->roomRejectId);
                $b->status      = 'rejected';
                $b->is_approve  = 0;
                $b->approved_by = \Illuminate\Support\Facades\Auth::id();
                $b->book_reject = $this->roomRejectReason;
                $b->save();
            });

            $this->showRoomRejectModal = false;
            if ($this->selectedRoomForSchedule) {
                $this->openScheduleModal($this->selectedRoomForSchedule);
            }
            $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Room booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Could not reject: ' . $e->getMessage());
        }
    }

    public function openScheduleModal($roomId): void
    {
        $this->selectedRoomForSchedule = (int) $roomId;
        $now = now($this->tz);
        $endDate = $now->copy()->addDays(30);
        $regularBookings = \App\Models\BookingRoom::with(['room', 'user', 'department'])
            ->where('room_id', $roomId)
            ->whereBetween('date', [$now->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', ['pending', 'approved', '0', '1', 'PENDING', 'APPROVED'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($b) {
                return [
                    'id'           => $b->bookingroom_id,
                    'title'        => $b->meeting_title ?? 'Meeting',
                    'room_name'    => $b->room->room_name ?? 'Room',
                    'borrower'     => $b->user->full_name ?? 'Unknown',
                    'department'   => $b->department->department_name ?? 'Unknown',
                    'purpose'      => $b->meeting_title ?? '-',
                    'destination'  => $b->room->room_name ?? '-',
                    'date'         => $b->date->format('Y-m-d'),
                    'start'        => Carbon::parse($b->start_time)->format('H:i'),
                    'end'          => Carbon::parse($b->end_time)->format('H:i'),
                    'start_date'   => $b->date->format('l, d M Y'),
                    'end_date'     => $b->date->format('l, d M Y'),
                    'start_time'   => Carbon::parse($b->start_time)->format('H:i'),
                    'end_time'     => Carbon::parse($b->end_time)->format('H:i'),
                    'start_at_full' => $b->date->format('d M Y') . ' ' . Carbon::parse($b->start_time)->format('H:i'),
                    'end_at_full'   => $b->date->format('d M Y') . ' ' . Carbon::parse($b->end_time)->format('H:i'),
                    'status'       => $b->status,
                    'is_priority'  => false,
                    'requested_by' => null,
                    'attendees'    => $b->number_of_attendees ?? null,
                ];
            });

        $priorityBookings = \App\Models\PriorityRoomBooking::with(['room', 'manager'])
            ->where('room_id', $roomId)
            ->whereBetween('date', [$now->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('status', [
                \App\Models\PriorityRoomBooking::STATUS_PENDING_RECEIPT,
                \App\Models\PriorityRoomBooking::STATUS_PENDING_CANCELLATION,
                \App\Models\PriorityRoomBooking::STATUS_APPROVED,
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($pb) {
                $date = $pb->date instanceof \Carbon\Carbon ? $pb->date : Carbon::parse($pb->date);
                return [
                    'id'           => 'priority-' . $pb->id,
                    'title'        => $pb->meeting_title ?? 'Priority Meeting',
                    'room_name'    => $pb->room->room_name ?? 'Room',
                    'borrower'     => $pb->manager?->full_name ?? '—',
                    'department'   => '—',
                    'purpose'      => $pb->meeting_title ?? '-',
                    'destination'  => $pb->room->room_name ?? '-',
                    'date'         => $date->format('Y-m-d'),
                    'start'        => Carbon::parse($pb->start_time)->format('H:i'),
                    'end'          => Carbon::parse($pb->end_time)->format('H:i'),
                    'start_date'   => $date->format('l, d M Y'),
                    'end_date'     => $date->format('l, d M Y'),
                    'start_time'   => Carbon::parse($pb->start_time)->format('H:i'),
                    'end_time'     => Carbon::parse($pb->end_time)->format('H:i'),
                    'start_at_full' => $date->format('d M Y') . ' ' . Carbon::parse($pb->start_time)->format('H:i'),
                    'end_at_full'   => $date->format('d M Y') . ' ' . Carbon::parse($pb->end_time)->format('H:i'),
                    'status'       => $pb->statusLabel(),
                    'is_priority'  => true,
                    'requested_by' => $pb->manager?->full_name ?? '—',
                    'attendees'    => $pb->number_of_attendees ?? null,
                ];
            });

        $this->roomScheduleData = $regularBookings->concat($priorityBookings)
            ->sortBy(fn($b) => $b['date'] . ' ' . $b['start_time'])
            ->values()
            ->toArray();

        $this->showScheduleModal = true;
    }

    public function openBookingDetail($bookingId): void
    {
        $booking = collect($this->roomScheduleData)->first(fn($b) => (string) $b['id'] === (string) $bookingId);
        if ($booking) {
            $this->selectedBookingDetail = $booking;
            $this->showBookingDetailModal = true;
        }
    }

    protected function detectGoogleConnected(): bool
    {
        try {
            return app(GoogleMeetService::class)->isConnected();
        } catch (\Throwable) {
            return false;
        }
    }

    private function resetOfflineForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'meeting_title' => null,
            'room_id'       => null,
            'department_id' => null,
            'date'          => null,
            'time'          => null,
            'time_end'      => null,
            'participant'   => null,
            'notes'         => null,
            'requirements'  => [],
        ];
        $this->offline_user_id    = null;
        $this->usersByDeptOffline = [];
        $this->userQueryOffline   = '';
        $this->resetValidation();
    }

    private function resetOnlineForm(): void
    {
        $this->online_meeting_title = '';
        $this->online_platform      = 'google_meet';
        $this->online_date          = null;
        $this->online_start_time    = null;
        $this->online_end_time      = null;
        $this->online_department_id = null;
        $this->online_user_id       = null;
        $this->usersByDept          = [];
        $this->userQueryOnline      = '';
        $this->resetValidation();
    }

    private function filterItemsByName(array $items, string $q, int $max = 50): array
    {
        $q = trim((string)$q);
        if ($q === '') {
            usort($items, fn($a, $b) => strnatcasecmp($a['name'] ?? '', $b['name'] ?? ''));
            return array_slice(array_values($items), 0, $max);
        }

        $qLower = mb_strtolower($q);
        $prefix = array_values(array_filter($items, fn($r) => isset($r['name']) && mb_stripos($r['name'], $qLower) === 0));
        $extra  = array_values(array_filter($items, fn($r) => isset($r['name']) && mb_stripos($r['name'], $qLower) !== false && mb_stripos($r['name'], $qLower) !== 0));

        usort($prefix, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        usort($extra,  fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        return array_slice(array_merge($prefix, $extra), 0, $max);
    }

    public function render()
    {
        $departmentsOffline = $this->filterItemsByName($this->departments, $this->deptQueryOffline, 50);
        $departmentsOnline  = $this->filterItemsByName($this->departments, $this->deptQueryOnline, 50);

        $usersOfflineFiltered = $this->usersByDeptOffline;
        $usersOnlineFiltered  = $this->usersByDept;

        return view('livewire.pages.receptionist.meetingschedule', [
            'departments'        => $this->departments,
            'departmentsOffline' => $departmentsOffline,
            'departmentsOnline'  => $departmentsOnline,
            'requirementOptions' => $this->requirementOptions,
            'rooms'              => $this->loadRooms(),
            'usersByDept'        => $usersOnlineFiltered,
            'usersByDeptOffline' => $usersOfflineFiltered,
            'googleConnected'    => $this->googleConnected,
            'editingId'          => $this->editingId,
            'form'               => $this->form,
            'otherRequirementId' => $this->otherRequirementId,
            'online_platform'    => $this->online_platform,
        ]);
    }
}