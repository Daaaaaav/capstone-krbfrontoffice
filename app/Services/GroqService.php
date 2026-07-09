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
        $companyId  = Auth::user()->company_id;
        $context    = $this->buildReceptionistContext($companyId);
        $systemPrompt = $this->receptionistSystemPrompt($context);

        return $this->callGroq($systemPrompt, $userMessage);
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
        $today     = $now->toDateString();

        // ── Room bookings ────────────────────────────────────────
        $roomQuery = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalRooms      = (clone $roomQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingRooms    = (clone $roomQuery)->where('status', 'pending')->count();
        $approvedRooms   = (clone $roomQuery)->where('status', 'approved')->count();
        $rejectedRooms   = (clone $roomQuery)->where('status', 'rejected')->count();
        $todayRooms      = (clone $roomQuery)->whereDate('date', $today)->count();
        $prevYearRooms   = (clone $roomQuery)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $topRoom = (clone $roomQuery)
            ->select('room_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('room_id')
            ->orderByDesc('cnt')
            ->with('room')
            ->first();

        $topRoomName = $topRoom?->room?->room_name ?? 'N/A';

        $topDept = (clone $roomQuery)
            ->select('department_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('department_id')
            ->orderByDesc('cnt')
            ->with('department')
            ->first();

        $topDeptName = $topDept?->department?->name ?? 'N/A';

        // Peak hour
        $peakHour = (clone $roomQuery)
            ->selectRaw('HOUR(start_time) as hr, COUNT(*) as cnt')
            ->whereNotNull('start_time')
            ->groupByRaw('HOUR(start_time)')
            ->orderByDesc('cnt')
            ->value('hr');
        $peakHourStr = $peakHour !== null ? sprintf('%02d:00', $peakHour) : 'N/A';

        // ── Vehicle bookings ─────────────────────────────────────
        $vehQuery = VehicleBooking::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $totalVehicles    = (clone $vehQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingVehicles  = (clone $vehQuery)->where('status', 'pending')->count();
        $approvedVehicles = (clone $vehQuery)->where('status', 'approved')->count();
        $rejectedVehicles = (clone $vehQuery)->where('status', 'rejected')->count();
        $todayVehicles    = (clone $vehQuery)->whereDate('start_at', $today)->count();
        $prevYearVehicles = (clone $vehQuery)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ── Deliveries ───────────────────────────────────────────
        $deliveryQuery  = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalDeliveries = (clone $deliveryQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $pendingDocs     = (clone $deliveryQuery)->where('status', 'pending')->count();

        // ── Guests ───────────────────────────────────────────────
        $guestQuery   = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $totalGuests  = (clone $guestQuery)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
        $todayGuests  = (clone $guestQuery)->whereDate('date', $today)->count();

        // ── Year-over-year trends ────────────────────────────────
        $roomTrend    = $this->trendLabel($prevYearRooms, $totalRooms);
        $vehicleTrend = $this->trendLabel($prevYearVehicles, $totalVehicles);

        return <<<CONTEXT
        === LIVE SYSTEM DATA (as of {$now->format('d M Y, H:i')} WIB) ===

        ROOM BOOKINGS ({$now->year}):
        - Total this year: {$totalRooms} (vs last year: {$prevYearRooms}, {$roomTrend})
        - Pending: {$pendingRooms}
        - Approved/Ongoing: {$approvedRooms}
        - Rejected: {$rejectedRooms}
        - Today's meetings: {$todayRooms}
        - Most booked room: {$topRoomName}
        - Top department: {$topDeptName}
        - Peak hour: {$peakHourStr}

        VEHICLE BOOKINGS ({$now->year}):
        - Total this year: {$totalVehicles} (vs last year: {$prevYearVehicles}, {$vehicleTrend})
        - Pending: {$pendingVehicles}
        - Approved/Ongoing: {$approvedVehicles}
        - Rejected: {$rejectedVehicles}
        - Today's vehicle trips: {$todayVehicles}

        DELIVERIES ({$now->year}):
        - Total deliveries this year: {$totalDeliveries}
        - Pending documents: {$pendingDocs}

        GUEST VISITS ({$now->year}):
        - Total guests this year: {$totalGuests}
        - Today's guests: {$todayGuests}
        CONTEXT;
    }

    private function managerSystemPrompt(string $dataContext): string
    {
        return <<<PROMPT
        You are an AI analytics assistant for a facility management system at Kebun Raya Bogor.

        Your role:
        - Analyze and summarize the reservation and operational statistics provided below.
        - Answer the manager's questions with concise, professional, data-driven responses.
        - Highlight important trends, anomalies, or actionable insights when relevant.
        - Suggest improvements where the data indicates a problem (e.g. high rejection rate, underused rooms).
        - Keep answers brief unless the manager asks for a detailed report.
        - Do not invent data not present in the context.
        - Respond in the same language the manager uses (English or Indonesian).

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
        return <<<PROMPT
        You are a friendly AI assistant for a receptionist at Kebun Raya Bogor's facility management system.

        Your role:
        - Help the receptionist quickly look up booking information, check schedules, and understand statuses.
        - Only answer based on the data provided below. Never invent booking details (IDs, times, room names).
        - If you cannot find the requested information in the context, say so politely and suggest the receptionist check the bookings page directly.
        - Keep answers short and practical — the receptionist is busy.
        - When confirming an action (approve, reject, reschedule), remind the receptionist to use the bookings page to action it, since you cannot perform actions directly.
        - Respond in the same language the receptionist uses (English or Indonesian).

        {$dataContext}
        PROMPT;
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
