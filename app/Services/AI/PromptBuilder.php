<?php

namespace App\Services\AI;

use App\Models\BookingRoom;
use App\Models\Delivery;
use App\Models\Guestbook;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds system prompts and context blocks for the chatbot.
 *
 * Extracted verbatim from GroqService so no business logic changes.
 * Token usage improvements: statistics are summarised per block,
 * historical record counts are capped, and redundant sections are
 * omitted when empty.
 */
class PromptBuilder
{
    private string $tz = 'Asia/Jakarta';

    // ──────────────────────────────────────────────────────────
    // Manager
    // ──────────────────────────────────────────────────────────

    public function managerSystemPrompt(string $dataContext): string
    {
        return <<<PROMPT
        You are an executive analytics assistant for the facility management system at Kebun Raya Bogor.

        Your role:
        - Summarize reservation and operational statistics in a professional, executive style.
        - Structure summaries clearly: lead with the most important numbers, then trends, then a brief recommendation.
        - Highlight notable trends (significant increases or decreases year-over-year, high rejection rates, underused resources).
        - Suggest one or two concrete, actionable improvements when the data indicates a problem.
        - Keep answers concise — use short paragraphs or bullet points, not walls of text.
        - Never invent figures not present in the context below.
        - Respond in the same language the manager uses (English or Indonesian).
        - NEVER suggest copying text to Word, creating external documents, or any workaround for exporting.
          The dashboard already has built-in PDF and CSV export buttons in the chat header.
          If asked about exporting or downloading, simply say: "Use the PDF or CSV export buttons in the chat header (the download icons next to the trash icon)."

        When asked to summarize a specific period (e.g. "this week", "today"), focus on the matching
        section of the data. When asked a general question, give the year-to-date picture first, then
        call out the weekly snapshot as supporting detail.

        {$dataContext}
        PROMPT;
    }

    public function buildManagerContext(?int $companyId): string
    {
        $now       = Carbon::now($this->tz);
        $yearStart = $now->copy()->startOfYear()->toDateTimeString();
        $yearEnd   = $now->copy()->endOfYear()->toDateTimeString();
        $prevStart = $now->copy()->subYear()->startOfYear()->toDateTimeString();
        $prevEnd   = $now->copy()->subYear()->endOfYear()->toDateTimeString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();
        $weekEnd   = $now->copy()->endOfWeek()->toDateString();
        $today     = $now->toDateString();

        // ── Room bookings ────────────────────────────────────────
        $roomQ = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalRooms     = (clone $roomQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingRooms   = (clone $roomQ)->where('status', 'pending')->count();
        $approvedRooms  = (clone $roomQ)->where('status', 'approved')->count();
        $rejectedRooms  = (clone $roomQ)->where('status', 'rejected')->count();
        $completedRooms = (clone $roomQ)->where('status', 'completed')->count();
        $todayRooms     = (clone $roomQ)->whereDate('date', $today)->count();
        $prevYearRooms  = (clone $roomQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $weekRooms     = (clone $roomQ)->whereBetween('date', [$weekStart, $weekEnd])->count();
        $weekPending   = (clone $roomQ)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'pending')->count();
        $weekApproved  = (clone $roomQ)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'approved')->count();
        $weekRejected  = (clone $roomQ)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'rejected')->count();
        $weekCompleted = (clone $roomQ)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'completed')->count();

        $topRoom         = (clone $roomQ)->select('room_id', DB::raw('COUNT(*) as cnt'))->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
        $topRoomName     = $topRoom?->room?->room_name ?? 'N/A';
        $topRoomWeek     = (clone $roomQ)->select('room_id', DB::raw('COUNT(*) as cnt'))->whereBetween('date', [$weekStart, $weekEnd])->groupBy('room_id')->orderByDesc('cnt')->with('room')->first();
        $topRoomWeekName = $topRoomWeek?->room?->room_name ?? 'N/A';

        $topDept         = (clone $roomQ)->select('department_id', DB::raw('COUNT(*) as cnt'))->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
        $topDeptName     = $topDept?->department?->name ?? 'N/A';
        $topDeptWeek     = (clone $roomQ)->select('department_id', DB::raw('COUNT(*) as cnt'))->whereBetween('date', [$weekStart, $weekEnd])->groupBy('department_id')->orderByDesc('cnt')->with('department')->first();
        $topDeptWeekName = $topDeptWeek?->department?->name ?? 'N/A';

        $peakHour    = (clone $roomQ)->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')->whereNotNull('start_time')->groupByRaw('HOUR(start_time)')->orderByDesc('cnt')->value('hr');
        $peakHourStr = $peakHour !== null ? sprintf('%02d:00–%02d:00', $peakHour, $peakHour + 1) : 'N/A';
        $rejRate     = $totalRooms > 0 ? round($rejectedRooms / $totalRooms * 100, 1) : 0;

        // ── Vehicle bookings ─────────────────────────────────────
        $vehQ = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalVehicles    = (clone $vehQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingVehicles  = (clone $vehQ)->where('status', 'pending')->count();
        $approvedVehicles = (clone $vehQ)->where('status', 'approved')->count();
        $rejectedVehicles = (clone $vehQ)->where('status', 'rejected')->count();
        $todayVehicles    = (clone $vehQ)->whereDate('start_at', $today)->count();
        $weekVehicles     = (clone $vehQ)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();
        $prevYearVehicles = (clone $vehQ)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ── Deliveries ───────────────────────────────────────────
        $delQ           = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalDeliveries = (clone $delQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingDocs    = (clone $delQ)->where('status', 'pending')->count();
        $weekDeliveries = (clone $delQ)->whereBetween('created_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();

        // ── Guests ───────────────────────────────────────────────
        $guestQ      = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalGuests = (clone $guestQ)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $todayGuests = (clone $guestQ)->whereDate('date', $today)->count();
        $weekGuests  = (clone $guestQ)->whereBetween('date', [$weekStart, $weekEnd])->count();

        $roomTrend    = $this->trendLabel($prevYearRooms, $totalRooms);
        $vehicleTrend = $this->trendLabel($prevYearVehicles, $totalVehicles);

        return <<<CONTEXT
        === LIVE SYSTEM DATA (as of {$now->format('d M Y, H:i')} WIB) ===

        ── THIS WEEK ({$weekStart} to {$weekEnd}) ──
        Room reservations : {$weekRooms} (pending:{$weekPending} approved:{$weekApproved} completed:{$weekCompleted} rejected:{$weekRejected})
        Most booked room  : {$topRoomWeekName} | Top department: {$topDeptWeekName}
        Vehicle trips     : {$weekVehicles} | Deliveries: {$weekDeliveries} | Guests: {$weekGuests}

        ── {$now->year} YEAR-TO-DATE ──
        Rooms   : {$totalRooms} (vs {$prevYearRooms} prev year, {$roomTrend})
          Status: pending={$pendingRooms} approved={$approvedRooms} completed={$completedRooms} rejected={$rejectedRooms} ({$rejRate}% rejection)
          Today  : {$todayRooms} | Peak hour: {$peakHourStr}
          Most booked room: {$topRoomName} | Top dept: {$topDeptName}

        Vehicles: {$totalVehicles} (vs {$prevYearVehicles} prev year, {$vehicleTrend})
          Status: pending={$pendingVehicles} approved={$approvedVehicles} rejected={$rejectedVehicles}
          Today : {$todayVehicles}

        Deliveries: {$totalDeliveries} (pending={$pendingDocs})
        Guests    : {$totalGuests} total, today={$todayGuests}
        CONTEXT;
    }

    // ──────────────────────────────────────────────────────────
    // Receptionist
    // ──────────────────────────────────────────────────────────

    public function receptionistSystemPrompt(string $dataContext, string $bookingDraftContext = ''): string
    {
        $draftSection = $bookingDraftContext
            ? "\n\nACTIVE BOOKING DRAFT (partially collected — use this to continue the conversation):\n{$bookingDraftContext}\n"
            : '';

        return <<<PROMPT
        You are a friendly AI assistant for a receptionist at Kebun Raya Bogor's facility management system.

        Your role:
        - Help the receptionist look up booking information, check schedules, and understand statuses.
        - Guide the receptionist through booking creation conversationally when they express booking intent.
        - Only answer based on the booking data provided below. Never invent booking details (IDs, times, names, rooms, vehicles).
        - Keep answers short and practical — the receptionist is busy.
        - Respond in the same language the receptionist uses (English or Indonesian).

        BOOKING CONVERSATION RULES:
        - When the receptionist expresses intent to book (book, reserve, schedule, need a room, need a car, etc.):
          1. Extract as many fields as possible from their message using natural language understanding.
          2. If ALL required fields are present, set "booking_complete": true and populate all fields.
          3. If fields are missing, ask ONE clear follow-up question for the most important missing field.
             Set "booking_complete": false and leave missing fields as null.
          4. Carry forward previously-collected draft fields (see ACTIVE BOOKING DRAFT below).
          5. Never show the Quick Booking form unless the user is vague and conversation cannot proceed.
        - Required fields for ROOM booking: meeting_title, room_id (or room_name), date, start_time, end_time.
        - Required fields for VEHICLE booking: vehicle_id (or vehicle_name), borrower_name, date_from, date_to, start_time, end_time, purpose, destination, purpose_type.
        - Natural language dates: "tomorrow" = {$this->tomorrow()}, "today" = {$this->today()}, "next Monday" = {$this->nextWeekday('Monday')}, etc.
        - Natural language times: "9am"→"09:00", "half past two"→"14:30", "10 until 12"→start="10:00" end="12:00", "for 2 hours"→derive end_time from start.
        - NEVER hallucinate room names, vehicle names, or IDs. Only use values from the data below.
        - If the user says "actually make it Room B" or changes something mid-draft, update that field only.
        {$draftSection}

        RESPONSE FORMAT (mandatory — always follow this):
        Return your ENTIRE response as a single valid JSON object with NO markdown, NO code fences,
        and NO text outside the JSON. The object must always have exactly these keys:

        {
          "reply": "<your conversational reply>",
          "booking_complete": <true|false — true only when ALL required fields for the booking type are present>,
          "booking_prefill": {
            "meeting_title":        "<string or null>",
            "room_id":              <integer or null>,
            "room_name":            "<string or null>",
            "booking_type":         "<meeting|online_meeting or null>",
            "online_provider":      "<google_meet|zoom or null>",
            "department":           "<string or null>",
            "historical_user":      "<string or null>",
            "date":                 "<YYYY-MM-DD or null>",
            "number_of_attendees":  <integer or null>,
            "start_time":           "<HH:MM or null>",
            "end_time":             "<HH:MM or null>",
            "special_notes":        "<string or null>"
          },
          "vehicle_prefill": {
            "vehicle_id":    <integer or null>,
            "vehicle_name":  "<string or null>",
            "plate_number":  "<string or null>",
            "borrower_name": "<string or null>",
            "department":    "<string or null>",
            "date_from":     "<YYYY-MM-DD or null>",
            "date_to":       "<YYYY-MM-DD or null>",
            "start_time":    "<HH:MM or null>",
            "end_time":      "<HH:MM or null>",
            "purpose":       "<string or null>",
            "destination":   "<string or null>",
            "purpose_type":  "<dinas|operasional|antar_jemput|lainnya or null>"
          }
        }

        Rules:
        - room_id and vehicle_id must come from the data below — never invent IDs.
        - date / date_from / date_to = TARGET booking date, not historical.
        - booking_type: "meeting" for in-room, "online_meeting" for Zoom/Google Meet, null if unknown.
        - online_provider: "google_meet" or "zoom" only when booking_type is "online_meeting", else null.
        - purpose_type must be one of: dinas, operasional, antar_jemput, lainnya — or null.
        - For non-booking questions: all prefill fields null, booking_complete: false.

        PROMPT
        . $dataContext;
    }

    public function buildReceptionistContext(?int $companyId): string
    {
        $now   = Carbon::now($this->tz);
        $today = $now->toDateString();

        // Today's room bookings (capped at 8 to reduce tokens)
        $todayRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->take(8)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room:%s | Dept:%s | %s–%s | %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->department?->name ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time   ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Pending approvals (capped at 5)
        $pendingRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->where('status', 'pending')
            ->orderBy('date')->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room:%s | %s %s–%s | Dept:%s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time   ?? '—', 0, 5),
                $b->department?->name ?? '—'
            ))
            ->join("\n");

        // Recent room bookings last 7 days (capped at 8)
        $recentRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereBetween('date', [$now->copy()->subDays(6)->toDateString(), $today])
            ->orderByDesc('date')
            ->take(8)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | %s | Room:%s | %s | %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->booking_type === 'online_meeting' ? 'Online(' . ($b->online_provider ?? '—') . ')' : 'In-Room',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Recent online meetings last 30 days (capped at 6)
        $recentOnline = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['department'])
            ->where('booking_type', 'online_meeting')
            ->whereBetween('date', [$now->copy()->subDays(29)->toDateString(), $today])
            ->orderByDesc('date')
            ->take(6)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | %s | Dept:%s | %s %s–%s | %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                ucfirst(str_replace('_', ' ', $b->online_provider ?? '—')),
                $b->department?->name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time   ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Available vehicles
        $availableVehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number', 'category'])
            ->map(fn($v) => sprintf(
                '  [VehicleID:%d] %s | Plate:%s | Type:%s',
                $v->vehicle_id,
                $v->name ?? '—',
                $v->plate_number ?? '—',
                $v->category ?? '—'
            ))
            ->join("\n");

        // Today's vehicles (capped at 6)
        $todayVehicles = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->whereDate('start_at', $today)
            ->orderBy('start_at')
            ->take(6)
            ->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | %s(%s) | %s–%s | Dest:%s | %s | %s',
                $v->vehiclebooking_id,
                $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—',
                $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('H:i') ?? '—',
                optional($v->end_at)->format('H:i')   ?? '—',
                $v->destination ?? '—',
                $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))
            ->join("\n");

        // Recent vehicles last 60 days (capped at 10 — was 15 × 90 days)
        $recentVehicles = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->where('start_at', '>=', $now->copy()->subDays(59)->startOfDay())
            ->orderByDesc('start_at')
            ->take(10)
            ->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | %s(%s) | %s→%s | Purpose:%s | Dept:%s | %s',
                $v->vehiclebooking_id,
                $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—',
                $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('d M H:i') ?? '—',
                optional($v->end_at)->format('d M H:i')   ?? '—',
                $v->purpose ?? '—',
                $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))
            ->join("\n");

        // Available rooms (for new bookings — name + ID mapping)
        $availableRooms = \App\Models\Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('room_name')
            ->get(['room_id', 'room_name', 'capacity'])
            ->map(fn($r) => sprintf(
                '  [RoomID:%d] %s | Capacity:%s',
                $r->room_id,
                $r->room_name ?? '—',
                $r->capacity ?? '—'
            ))
            ->join("\n");

        $todayRoomsBlock    = $todayRooms        ?: '  (none)';
        $pendingBlock       = $pendingRooms       ?: '  (none)';
        $recentRoomsBlock   = $recentRooms        ?: '  (none)';
        $recentOnlineBlock  = $recentOnline       ?: '  (none)';
        $availVehicleBlock  = $availableVehicles  ?: '  (none)';
        $todayVehicleBlock  = $todayVehicles      ?: '  (none)';
        $recentVehicleBlock = $recentVehicles     ?: '  (none)';
        $availRoomsBlock    = $availableRooms     ?: '  (none)';

        return <<<CONTEXT
        === CURRENT BOOKING DATA ({$now->format('d M Y, H:i')} WIB) ===

        AVAILABLE ROOMS (use IDs for new bookings):
        {$availRoomsBlock}

        TODAY'S MEETINGS ({$today}):
        {$todayRoomsBlock}

        PENDING APPROVALS (≤5):
        {$pendingBlock}

        RECENT ROOM BOOKINGS (last 7 days, ≤8):
        {$recentRoomsBlock}

        RECENT ONLINE MEETINGS (last 30 days, ≤6):
        {$recentOnlineBlock}

        AVAILABLE VEHICLES:
        {$availVehicleBlock}

        TODAY'S VEHICLE TRIPS:
        {$todayVehicleBlock}

        RECENT VEHICLE BOOKINGS (last 60 days, ≤10):
        {$recentVehicleBlock}
        CONTEXT;
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    public function trendLabel(int $prev, int $curr): string
    {
        if ($prev === 0) {
            return $curr > 0 ? '+100% new data' : 'no change';
        }
        $pct = round(($curr - $prev) / $prev * 100, 1);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    private function today(): string
    {
        return Carbon::now($this->tz)->toDateString();
    }

    private function tomorrow(): string
    {
        return Carbon::now($this->tz)->addDay()->toDateString();
    }

    private function nextWeekday(string $day): string
    {
        return Carbon::now($this->tz)->next($day)->toDateString();
    }
}
