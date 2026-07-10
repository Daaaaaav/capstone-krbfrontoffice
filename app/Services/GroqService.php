<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
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
                    'model'            => $this->model,
                    'temperature'      => 0.3,
                    // Disable Qwen 3 chain-of-thought thinking — prevents <think>…</think>
                    // blocks from leaking into the response text.
                    'enable_thinking'  => false,
                    'messages'         => [
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

        // Recent bookings (last 7 days)
        $recentRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereBetween('date', [$now->copy()->subDays(6)->toDateString(), $today])
            ->orderByDesc('date')
            ->take(10)
            ->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room: %s | Date: %s | Status: %s',
                $b->bookingroom_id,
                $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                ucfirst($b->status ?? '—')
            ))
            ->join("\n");

        // Vehicle bookings today
        $todayVehicles = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereDate('start_at', $today)
            ->orderBy('start_at')
            ->take(5)
            ->get()
            ->map(fn($v) => sprintf(
                '  [ID:%d] %s | Purpose: %s | Dest: %s | Status: %s',
                $v->vehiclebooking_id,
                $v->borrower_name ?? '—',
                $v->purpose ?? '—',
                $v->destination ?? '—',
                ucfirst($v->status ?? '—')
            ))
            ->join("\n");

        $todayRoomsBlock   = $todayRooms   ?: '  (none)';
        $pendingBlock      = $pendingRooms ?: '  (none)';
        $recentRoomsBlock  = $recentRooms  ?: '  (none)';
        $todayVehicleBlock = $todayVehicles ?: '  (none)';

        return <<<CONTEXT
        === CURRENT BOOKING DATA (as of {$now->format('d M Y, H:i')} WIB) ===

        TODAY'S ROOM MEETINGS ({$today}):
        {$todayRoomsBlock}

        PENDING APPROVALS (up to 5):
        {$pendingBlock}

        RECENT BOOKINGS (last 7 days, up to 10):
        {$recentRoomsBlock}

        TODAY'S VEHICLE TRIPS:
        {$todayVehicleBlock}
        CONTEXT;
    }

    private function receptionistSystemPrompt(string $dataContext): string
    {
        return <<<'PROMPT'
        You are a friendly AI assistant for a receptionist at Kebun Raya Bogor's facility management system.

        Your role:
        - Help the receptionist look up booking information, check schedules, and understand statuses.
        - Only answer based on the booking data provided below. Never invent booking details (IDs, times, room names).
        - If you cannot find the requested information in the context, say so politely.
        - Keep answers short and practical — the receptionist is busy.
        - Respond in the same language the receptionist uses (English or Indonesian).

        RESPONSE FORMAT (mandatory — always follow this):
        You MUST always return your ENTIRE response as a single valid JSON object with NO markdown, NO code fences,
        and NO text outside the JSON. The object must always have exactly these two keys:

        {
          "reply": "<your conversational reply to the receptionist>",
          "booking_prefill": {
            "meeting_title":        "<string or null>",
            "room_id":              <integer or null>,
            "room_name":            "<string or null>",
            "department":           "<department name string or null>",
            "historical_user":      "<name of the person who previously booked, or null>",
            "date":                 "<YYYY-MM-DD or null>",
            "number_of_attendees":  <integer or null>,
            "start_time":           "<HH:MM 24h or null>",
            "end_time":             "<HH:MM 24h or null>",
            "special_notes":        "<room requirements / notes string or null>"
          }
        }

        Rules for booking_prefill:
        - Fill in ONLY what you can confidently determine from the conversation and the booking data below.
        - Leave fields as null if the information was not provided or cannot be inferred.
        - For rebook requests: copy all matching details from the historical booking and apply any changes the
          receptionist asked for (new date, different attendee count, etc.).
        - For general questions (e.g. "what rooms are free today?"): still include booking_prefill but leave
          all fields null — the receptionist may want to start a new booking from the answer.
        - room_id must come from the actual booking data below; never invent an ID.
        - date must be the TARGET date for the new booking (not the historical date).

        PROMPT
        . $dataContext;
    }

    // ──────────────────────────────────────────────────────────
    // Intent response parser
    // ──────────────────────────────────────────────────────────

    /**
     * Parse the raw Groq response.
     *
     * For receptionist responses the model now always returns a JSON envelope,
     * so this method decodes it, normalises types, and resolves room_id by name
     * when the model omitted the integer ID. A booking_prefill with all-null
     * fields is still returned — the view always shows the form panel.
     *
     * @return array{reply: string, booking_prefill: array|null}
     */
    private function parseIntentResponse(string $raw): array
    {
        $raw = trim($raw);

        // Strip Qwen 3 chain-of-thought <think>…</think> blocks that sometimes
        // appear even when enable_thinking is false (e.g. older cached responses).
        $raw = preg_replace('/<think>.*?<\/think>/si', '', $raw);

        // Strip markdown code fences the model sometimes adds despite instructions
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim($raw);

        // Only attempt JSON decode if it looks like an object
        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded) && isset($decoded['reply'])) {
                $reply   = (string) $decoded['reply'];
                $prefill = $decoded['booking_prefill'] ?? null;

                if (is_array($prefill)) {
                    // Resolve room_id from room_name when the model only gave a name
                    if (empty($prefill['room_id']) && !empty($prefill['room_name'])) {
                        $companyId = Auth::user()->company_id;
                        $room = \App\Models\Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->where('room_name', 'like', '%' . trim($prefill['room_name']) . '%')
                            ->first();
                        $prefill['room_id']   = $room?->room_id;
                        $prefill['room_name'] = $room?->room_name ?? $prefill['room_name'];
                    }

                    // Normalise field types
                    $prefill['room_id']             = isset($prefill['room_id']) ? (int) $prefill['room_id'] : null;
                    $prefill['number_of_attendees'] = isset($prefill['number_of_attendees']) ? (int) $prefill['number_of_attendees'] : null;
                    $prefill['special_notes']       = $prefill['special_notes']   ?? null;
                    $prefill['department']          = $prefill['department']      ?? null;
                    $prefill['historical_user']     = $prefill['historical_user'] ?? null;
                }

                // Always return prefill (even if all-null) so the view shows the form
                return ['reply' => $reply, 'booking_prefill' => $prefill ?? []];
            }
        }

        // Fallback: plain text reply, show empty form panel
        return ['reply' => $raw, 'booking_prefill' => []];
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
