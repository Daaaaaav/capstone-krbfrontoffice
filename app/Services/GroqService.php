<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use App\Models\Delivery;
use App\Models\Guestbook;
use Carbon\Carbon;

class GroqService
{
    private string $apiKey;
    private string $model;
    private string $tz = 'Asia/Jakarta';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY', '');
        $this->model  = env('GROQ_MODEL', 'qwen/qwen3-32b');
    }

    // ──────────────────────────────────────────────────────────
    // Public entry-points
    // ──────────────────────────────────────────────────────────

    /**
     * Send a message as the Manager chatbot.
     * Aggregated statistics are embedded into the system context.
     */
    public function managerChat(string $userMessage): string
    {
        $companyId  = Auth::user()->company_id;
        $context    = $this->buildManagerContext($companyId);
        $systemPrompt = $this->managerSystemPrompt($context);

        return $this->callGroq($systemPrompt, $userMessage);
    }

    /**
     * Send a message as the Receptionist chatbot.
     * Recent booking history is embedded into the system context.
     */
    public function receptionistChat(string $userMessage): string
    {
        return $this->receptionistChatWithIntent($userMessage)['reply'];
    }

    /**
     * Send a message as the Receptionist chatbot and return both the text reply
     * and optional structured prefill data for the booking form.
     *
     * @return array{reply: string, booking_prefill: array|null}
     */
    public function receptionistChatWithIntent(string $userMessage): array
    {
        $companyId    = Auth::user()->company_id;
        $context      = $this->buildReceptionistContext($companyId);
        $systemPrompt = $this->receptionistSystemPrompt($context);

        $raw = $this->callGroq($systemPrompt, $userMessage);

        return $this->parseIntentResponse($raw);
    }

    // ──────────────────────────────────────────────────────────
    // Groq API call
    // ──────────────────────────────────────────────────────────

    private function callGroq(string $systemPrompt, string $userMessage): string
    {
        if (empty($this->apiKey)) {
            return 'AI assistant is not configured. Please set GROQ_API_KEY in your environment.';
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => $this->model,
                    'temperature' => 0.3,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Groq API returned an error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return 'Sorry, the AI service returned an error. Please try again later.';
            }

            return $response->json('choices.0.message.content')
                ?? 'Sorry, I could not generate a response.';

        } catch (\Throwable $e) {
            Log::error('Groq API call failed', ['error' => $e->getMessage()]);
            return 'Sorry, I could not reach the AI service. Please check your connection and try again.';
        }
    }

    // ──────────────────────────────────────────────────────────
    // Manager: aggregated statistics context
    // ──────────────────────────────────────────────────────────

    private function buildManagerContext(?int $companyId): string
    {
        $now       = Carbon::now($this->tz);
        $yearStart = $now->copy()->startOfYear()->toDateTimeString();
        $yearEnd   = $now->copy()->endOfYear()->toDateTimeString();
        $prevStart = $now->copy()->subYear()->startOfYear()->toDateTimeString();
        $prevEnd   = $now->copy()->subYear()->endOfYear()->toDateTimeString();
        $weekStart = $now->copy()->startOfWeek()->toDateString();  // Monday
        $weekEnd   = $now->copy()->endOfWeek()->toDateString();    // Sunday
        $today     = $now->toDateString();

        // ── Room bookings ────────────────────────────────────────
        $roomQuery = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalRooms      = (clone $roomQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingRooms    = (clone $roomQuery)->where('status', 'pending')->count();
        $approvedRooms   = (clone $roomQuery)->where('status', 'approved')->count();
        $rejectedRooms   = (clone $roomQuery)->where('status', 'rejected')->count();
        $completedRooms  = (clone $roomQuery)->where('status', 'completed')->count();
        $todayRooms      = (clone $roomQuery)->whereDate('date', $today)->count();
        $prevYearRooms   = (clone $roomQuery)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // This week
        $weekRooms       = (clone $roomQuery)->whereBetween('date', [$weekStart, $weekEnd])->count();
        $weekPending     = (clone $roomQuery)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'pending')->count();
        $weekApproved    = (clone $roomQuery)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'approved')->count();
        $weekRejected    = (clone $roomQuery)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'rejected')->count();
        $weekCompleted   = (clone $roomQuery)->whereBetween('date', [$weekStart, $weekEnd])->where('status', 'completed')->count();

        $topRoom = (clone $roomQuery)
            ->select('room_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('room_id')
            ->orderByDesc('cnt')
            ->with('room')
            ->first();

        $topRoomName = $topRoom?->room?->room_name ?? 'N/A';

        // Most booked room this week
        $topRoomWeek = (clone $roomQuery)
            ->select('room_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->groupBy('room_id')
            ->orderByDesc('cnt')
            ->with('room')
            ->first();

        $topRoomWeekName = $topRoomWeek?->room?->room_name ?? 'N/A';

        $topDept = (clone $roomQuery)
            ->select('department_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('department_id')
            ->orderByDesc('cnt')
            ->with('department')
            ->first();

        $topDeptName = $topDept?->department?->name ?? 'N/A';

        // Top dept this week
        $topDeptWeek = (clone $roomQuery)
            ->select('department_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->groupBy('department_id')
            ->orderByDesc('cnt')
            ->with('department')
            ->first();

        $topDeptWeekName = $topDeptWeek?->department?->name ?? 'N/A';

        // Peak hour (all-time this year)
        $peakHour = (clone $roomQuery)
            ->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')
            ->whereNotNull('start_time')
            ->groupByRaw('HOUR(start_time)')
            ->orderByDesc('cnt')
            ->value('hr');
        $peakHourStr = $peakHour !== null ? sprintf('%02d:00–%02d:00', $peakHour, $peakHour + 1) : 'N/A';

        // Rejection rate this year
        $rejectionRate = $totalRooms > 0
            ? round($rejectedRooms / $totalRooms * 100, 1)
            : 0;

        // ── Vehicle bookings ─────────────────────────────────────
        $vehQuery = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalVehicles    = (clone $vehQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingVehicles  = (clone $vehQuery)->where('status', 'pending')->count();
        $approvedVehicles = (clone $vehQuery)->where('status', 'approved')->count();
        $rejectedVehicles = (clone $vehQuery)->where('status', 'rejected')->count();
        $todayVehicles    = (clone $vehQuery)->whereDate('start_at', $today)->count();
        $weekVehicles     = (clone $vehQuery)->whereBetween('start_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();
        $prevYearVehicles = (clone $vehQuery)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ── Deliveries ───────────────────────────────────────────
        $deliveryQuery   = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalDeliveries = (clone $deliveryQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingDocs     = (clone $deliveryQuery)->where('status', 'pending')->count();
        $weekDeliveries  = (clone $deliveryQuery)->whereBetween('created_at', [$weekStart . ' 00:00:00', $weekEnd . ' 23:59:59'])->count();

        // ── Guests ───────────────────────────────────────────────
        $guestQuery   = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalGuests  = (clone $guestQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $todayGuests  = (clone $guestQuery)->whereDate('date', $today)->count();
        $weekGuests   = (clone $guestQuery)->whereBetween('date', [$weekStart, $weekEnd])->count();

        // ── Year-over-year trends ────────────────────────────────
        $roomTrend    = $this->trendLabel($prevYearRooms, $totalRooms);
        $vehicleTrend = $this->trendLabel($prevYearVehicles, $totalVehicles);

        return <<<CONTEXT
        === LIVE SYSTEM DATA (as of {$now->format('d M Y, H:i')} WIB) ===

        ── THIS WEEK ({$weekStart} to {$weekEnd}) ──
        Room reservations : {$weekRooms}
          Pending          : {$weekPending}
          Approved/Ongoing : {$weekApproved}
          Completed        : {$weekCompleted}
          Rejected         : {$weekRejected}
        Most booked room   : {$topRoomWeekName}
        Top department     : {$topDeptWeekName}
        Vehicle trips      : {$weekVehicles}
        Deliveries         : {$weekDeliveries}
        Guest visits       : {$weekGuests}

        ── {$now->year} YEAR-TO-DATE ──
        Room bookings total : {$totalRooms} (vs {$prevYearRooms} last year, {$roomTrend})
          Pending           : {$pendingRooms}
          Approved/Ongoing  : {$approvedRooms}
          Completed         : {$completedRooms}
          Rejected          : {$rejectedRooms} ({$rejectionRate}% rejection rate)
          Today's meetings  : {$todayRooms}
          Most booked room  : {$topRoomName}
          Top department    : {$topDeptName}
          Peak booking hour : {$peakHourStr}

        Vehicle bookings total : {$totalVehicles} (vs {$prevYearVehicles} last year, {$vehicleTrend})
          Pending              : {$pendingVehicles}
          Approved/Ongoing     : {$approvedVehicles}
          Rejected             : {$rejectedVehicles}
          Today's trips        : {$todayVehicles}

        Deliveries total  : {$totalDeliveries}
          Pending docs    : {$pendingDocs}

        Guest visits total : {$totalGuests}
          Today's guests  : {$todayGuests}
        CONTEXT;
    }

    private function managerSystemPrompt(string $dataContext): string
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

    // ──────────────────────────────────────────────────────────
    // Receptionist: recent booking history context
    // ──────────────────────────────────────────────────────────

    private function buildReceptionistContext(?int $companyId): string
    {
        $now   = Carbon::now($this->tz);
        $today = $now->toDateString();

        // Today's room bookings
        $todayRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->take(10)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room: %s | Dept: %s | %s–%s | Status: %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->department?->name ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Pending approvals
        $pendingRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->where('status', 'pending')
            ->orderBy('date')
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room: %s | Date: %s %s–%s | Dept: %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time ?? '—', 0, 5),
                $b->department?->name ?? '—'
            ))
            ->join("\n");

        // Recent bookings (last 7 days) — room + online, with type annotation
        $recentRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereBetween('date', [$now->copy()->subDays(6)->toDateString(), $today])
            ->orderByDesc('date')
            ->take(10)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Type: %s | Room: %s | Date: %s | Status: %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->booking_type === 'online_meeting'
                    ? 'Online (' . ($b->online_provider ?? '—') . ')'
                    : 'In-Room',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Recent online meetings (last 30 days) — dedicated block for rebook context
        $recentOnline = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['department'])
            ->where('booking_type', 'online_meeting')
            ->whereBetween('date', [$now->copy()->subDays(29)->toDateString(), $today])
            ->orderByDesc('date')
            ->take(8)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Provider: %s | Dept: %s | Date: %s %s–%s | Status: %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                ucfirst(str_replace('_', ' ', $b->online_provider ?? '—')),
                $b->department?->name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5),
                substr($b->end_time ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Available vehicles (for new bookings)
        $availableVehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number', 'category'])
            ->map(fn($v) => sprintf(
                '  [VehicleID:%d] %s | Plate: %s | Type: %s',
                $v->vehicle_id,
                $v->name ?? '—',
                $v->plate_number ?? '—',
                $v->category ?? '—'
            ))
            ->join("\n");

        // Vehicle bookings today
        $todayVehicles = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->whereDate('start_at', $today)
            ->orderBy('start_at')
            ->take(8)
            ->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | Vehicle: %s (%s) | %s–%s | Dest: %s | Dept: %s | Status: %s',
                $v->vehiclebooking_id,
                $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—',
                $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('H:i') ?? '—',
                optional($v->end_at)->format('H:i') ?? '—',
                $v->destination ?? '—',
                $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))
            ->join("\n");

        // Recent vehicle bookings (last 90 days)
        $recentVehicles = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['vehicle', 'department'])
            ->where('start_at', '>=', $now->copy()->subDays(89)->startOfDay())
            ->orderByDesc('start_at')
            ->take(15)
            ->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | Vehicle: %s (%s) | %s → %s | Purpose: %s | Dept: %s | Status: %s',
                $v->vehiclebooking_id,
                $v->borrower_name ?? '—',
                $v->vehicle?->name ?? '—',
                $v->vehicle?->plate_number ?? '—',
                optional($v->start_at)->format('d M H:i') ?? '—',
                optional($v->end_at)->format('d M H:i') ?? '—',
                $v->purpose ?? '—',
                $v->department?->name ?? '—',
                ucfirst($v->status ?? '—')
            ))
            ->join("\n");

        $todayRoomsBlock    = $todayRooms    ?: '  (none)';
        $pendingBlock       = $pendingRooms  ?: '  (none)';
        $recentRoomsBlock   = $recentRooms   ?: '  (none)';
        $recentOnlineBlock  = $recentOnline  ?: '  (none)';
        $availVehicleBlock  = $availableVehicles ?: '  (none)';
        $todayVehicleBlock  = $todayVehicles ?: '  (none)';
        $recentVehicleBlock = $recentVehicles ?: '  (none)';

        return <<<CONTEXT
        === CURRENT BOOKING DATA (as of {$now->format('d M Y, H:i')} WIB) ===

        TODAY'S ROOM MEETINGS ({$today}):
        {$todayRoomsBlock}

        PENDING ROOM APPROVALS (up to 5):
        {$pendingBlock}

        RECENT ROOM BOOKINGS (last 7 days, up to 10 — includes both in-room and online):
        {$recentRoomsBlock}

        RECENT ONLINE MEETINGS (last 30 days, up to 8 — use for online rebook requests):
        {$recentOnlineBlock}

        AVAILABLE VEHICLES (active fleet):
        {$availVehicleBlock}

        TODAY'S VEHICLE TRIPS:
        {$todayVehicleBlock}

        RECENT VEHICLE BOOKINGS (last 90 days, up to 15):
        {$recentVehicleBlock}
        CONTEXT;
    }

    private function receptionistSystemPrompt(string $dataContext): string
    {
        return <<<'PROMPT'
        You are a friendly AI assistant for a receptionist at Kebun Raya Bogor's facility management system.

        Your role:
        - Help the receptionist look up booking information, check schedules, and understand statuses.
        - Only answer based on the booking data provided below. Never invent booking details (IDs, times, names).
        - If you cannot find the requested information in the context, say so politely.
        - Keep answers short and practical — the receptionist is busy.
        - Respond in the same language the receptionist uses (English or Indonesian).

        RESPONSE FORMAT (mandatory — always follow this):
        You MUST always return your ENTIRE response as a single valid JSON object with NO markdown, NO code fences,
        and NO text outside the JSON. The object must always have exactly these three keys:

        {
          "reply": "<your conversational reply to the receptionist>",
          "booking_prefill": {
            "meeting_title":        "<string or null>",
            "room_id":              <integer or null>,
            "room_name":            "<string or null>",
            "booking_type":         "<meeting|online_meeting or null>",
            "online_provider":      "<google_meet|zoom or null — only set when booking_type is online_meeting>",
            "department":           "<department name string or null>",
            "historical_user":      "<name of the person who previously booked, or null>",
            "date":                 "<YYYY-MM-DD or null>",
            "number_of_attendees":  <integer or null>,
            "start_time":           "<HH:MM 24h or null>",
            "end_time":             "<HH:MM 24h or null>",
            "special_notes":        "<room requirements / notes string or null>"
          },
          "vehicle_prefill": {
            "vehicle_id":     <integer or null>,
            "vehicle_name":   "<string or null>",
            "plate_number":   "<string or null>",
            "borrower_name":  "<string or null>",
            "department":     "<department name string or null>",
            "date_from":      "<YYYY-MM-DD or null>",
            "date_to":        "<YYYY-MM-DD or null>",
            "start_time":     "<HH:MM 24h or null>",
            "end_time":       "<HH:MM 24h or null>",
            "purpose":        "<string or null>",
            "destination":    "<string or null>",
            "purpose_type":   "<dinas|operasional|antar_jemput|lainnya or null>"
          }
        }

        Rules for booking_prefill (room booking):
        - Fill in ONLY what you can confidently determine from the conversation and the data below.
        - Leave fields as null if the information was not provided or cannot be inferred.
        - For rebook/repeat room requests: copy matching details and apply any changes (new date, attendees, etc.).
        - For non-room questions: include booking_prefill with all fields null.
        - room_id must come from the actual booking data below; never invent an ID.
        - date must be the TARGET date for the new booking (not the historical date).
        - booking_type: set to "meeting" for in-room meetings, "online_meeting" for Zoom/Google Meet, or null if unspecified.
        - online_provider: set to "google_meet" or "zoom" only when booking_type is "online_meeting". Use the RECENT ONLINE MEETINGS section to determine the historical provider. Null for in-room meetings.
        - For online meeting rebooks: room_id and room_name should be null (no room needed).

        Rules for vehicle_prefill (vehicle booking):
        - Fill in ONLY what you can confidently determine from the conversation and the data below.
        - Leave fields as null if the information was not provided or cannot be inferred.
        - For rebook/repeat vehicle trip requests: copy matching details from the RECENT VEHICLE BOOKINGS section and apply any changes.
        - For non-vehicle questions: include vehicle_prefill with all fields null.
        - vehicle_id must come from the AVAILABLE VEHICLES section in the data below; never invent an ID.
        - date_from and date_to must be the TARGET dates (not historical dates).
        - purpose_type must be one of: dinas, operasional, antar_jemput, lainnya — or null.

        PROMPT
        . $dataContext;
    }

    // ──────────────────────────────────────────────────────────
    // Intent response parser
    // ──────────────────────────────────────────────────────────

    /**
     * Parse the raw Groq response.
     *
     * The model always returns a JSON envelope with reply, booking_prefill,
     * and vehicle_prefill. This method decodes it, normalises types, and
     * resolves room_id / vehicle_id by name when the model omitted the integer.
     *
     * @return array{reply: string, booking_prefill: array, vehicle_prefill: array}
     */
    private function parseIntentResponse(string $raw): array
    {
        $empty = [
            'reply'           => $raw,
            'booking_prefill' => [],
            'vehicle_prefill' => [],
        ];

        $raw = trim($raw);

        // Strip Qwen 3 <think>…</think> reasoning blocks
        $raw = preg_replace('/<think>.*?<\/think>/si', '', $raw);

        // Strip markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim($raw);

        if (!str_starts_with($raw, '{')) {
            return $empty;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['reply'])) {
            return $empty;
        }

        $reply   = (string) $decoded['reply'];
        $companyId = Auth::user()->company_id;

        // ── booking_prefill (room) ────────────────────────────────
        $prefill = $decoded['booking_prefill'] ?? [];
        if (is_array($prefill)) {
            if (empty($prefill['room_id']) && !empty($prefill['room_name'])) {
                $room = \App\Models\Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->where('room_name', 'like', '%' . trim($prefill['room_name']) . '%')
                    ->first();
                $prefill['room_id']   = $room?->room_id;
                $prefill['room_name'] = $room?->room_name ?? $prefill['room_name'];
            }
            $prefill['room_id']             = isset($prefill['room_id'])             ? (int) $prefill['room_id']             : null;
            $prefill['number_of_attendees'] = isset($prefill['number_of_attendees']) ? (int) $prefill['number_of_attendees'] : null;
            $prefill['special_notes']       = $prefill['special_notes']   ?? null;
            $prefill['department']          = $prefill['department']      ?? null;
            $prefill['historical_user']     = $prefill['historical_user'] ?? null;

            // Normalise booking_type and online_provider
            $validBookingTypes = ['meeting', 'online_meeting'];
            $prefill['booking_type'] = in_array($prefill['booking_type'] ?? '', $validBookingTypes, true)
                ? $prefill['booking_type'] : null;

            $validProviders = ['google_meet', 'zoom'];
            $prefill['online_provider'] = in_array($prefill['online_provider'] ?? '', $validProviders, true)
                ? $prefill['online_provider'] : null;

            // If online_meeting, room is not needed
            if ($prefill['booking_type'] === 'online_meeting') {
                $prefill['room_id']   = null;
                $prefill['room_name'] = null;
            }
        }

        // ── vehicle_prefill ───────────────────────────────────────
        $vprefill = $decoded['vehicle_prefill'] ?? [];
        if (is_array($vprefill)) {
            // Resolve vehicle_id by name or plate when model gave text only
            if (empty($vprefill['vehicle_id']) && (!empty($vprefill['vehicle_name']) || !empty($vprefill['plate_number']))) {
                $vq = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId));
                if (!empty($vprefill['vehicle_name'])) {
                    $vq = $vq->where('name', 'like', '%' . trim($vprefill['vehicle_name']) . '%');
                } elseif (!empty($vprefill['plate_number'])) {
                    $vq = $vq->where('plate_number', 'like', '%' . trim($vprefill['plate_number']) . '%');
                }
                $vehicle = $vq->first();
                $vprefill['vehicle_id']   = $vehicle?->vehicle_id;
                $vprefill['vehicle_name'] = $vehicle?->name         ?? $vprefill['vehicle_name']  ?? null;
                $vprefill['plate_number'] = $vehicle?->plate_number ?? $vprefill['plate_number']  ?? null;
            }
            $vprefill['vehicle_id']   = isset($vprefill['vehicle_id'])   ? (int) $vprefill['vehicle_id'] : null;
            $vprefill['borrower_name']= $vprefill['borrower_name'] ?? null;
            $vprefill['department']   = $vprefill['department']    ?? null;
            $vprefill['date_from']    = $vprefill['date_from']     ?? null;
            $vprefill['date_to']      = $vprefill['date_to']       ?? null;
            $vprefill['start_time']   = $vprefill['start_time']    ?? null;
            $vprefill['end_time']     = $vprefill['end_time']      ?? null;
            $vprefill['purpose']      = $vprefill['purpose']       ?? null;
            $vprefill['destination']  = $vprefill['destination']   ?? null;

            $validTypes = ['dinas', 'operasional', 'antar_jemput', 'lainnya'];
            $vprefill['purpose_type'] = in_array($vprefill['purpose_type'] ?? '', $validTypes, true)
                ? $vprefill['purpose_type']
                : null;
        }

        return [
            'reply'           => $reply,
            'booking_prefill' => $prefill  ?? [],
            'vehicle_prefill' => $vprefill ?? [],
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function trendLabel(int $prev, int $curr): string
    {
        if ($prev === 0) {
            return $curr > 0 ? '+100% new data' : 'no change';
        }
        $pct = round(($curr - $prev) / $prev * 100, 1);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }
}
