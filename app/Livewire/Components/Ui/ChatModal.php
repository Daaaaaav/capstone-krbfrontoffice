<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIService;
use App\Services\AI\PromptBuilder;
use App\Services\AI\BookingDraftService;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\Room;
use App\Models\Vehicle;

class ChatModal extends Component
{
    // ── UI state ──────────────────────────────────────────────
    public bool   $isOpen    = false;
    public string $message   = '';
    public bool   $isLoading = false;
    public string $userRole  = 'receptionist';

    /** 'chat' | 'history' | 'session' */
    public string $panel = 'chat';

    // ── Active conversation ───────────────────────────────────
    /**
     * Each entry: [
     *   'role'            => 'user'|'assistant',
     *   'text'            => string,
     *   'booking_prefill' => array|null,
     *   'vehicle_prefill' => array|null,
     *   'sent_at'         => string,
     * ]
     */
    public array $messages = [];

    /** DB id of the current open session (null until first message sent) */
    public ?int $activeSessionId = null;

    // ── Multi-turn conversation history for the AI ────────────
    /**
     * Compact history passed to the AI provider on each call.
     * Each entry: ['role' => 'user'|'assistant', 'content' => string]
     * Capped at config('ai.max_draft_turns') entries.
     */
    public array $conversationHistory = [];

    // ── Booking draft (conversational booking state) ──────────
    /**
     * Persisted across turns while a booking conversation is in progress.
     * Shape defined by BookingDraftService::emptyDraft().
     */
    public array $bookingDraft = [];

    // ── History panel ─────────────────────────────────────────
    public array  $historySessions     = [];
    public ?int   $viewingSessionId    = null;
    public array  $viewingMessages     = [];
    public string $viewingSessionTitle = '';
    public string $viewingSessionDate  = '';

    // ─────────────────────────────────────────────────────────
    // Mount
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->userRole    = $this->resolveUserRole();
        $this->bookingDraft = app(BookingDraftService::class)->emptyDraft();

        // Auto-archive any session left open (navigated away mid-conversation)
        AiChatSession::where('user_id', Auth::id())
            ->whereNull('ended_at')
            ->whereNotNull('title')
            ->update(['ended_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────
    // Modal lifecycle
    // ─────────────────────────────────────────────────────────

    #[On('openChatModal')]
    public function openModal(): void
    {
        $this->isOpen = true;
        $this->panel  = 'chat';

        if (empty($this->messages)) {
            $this->seedGreeting();
        }
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    // ─────────────────────────────────────────────────────────
    // Panels
    // ─────────────────────────────────────────────────────────

    public function showHistory(): void
    {
        $this->loadHistorySessions();
        $this->panel = 'history';
    }

    public function showChat(): void
    {
        $this->panel = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    public function viewSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())
            ->find($sessionId);

        if (! $session) {
            return;
        }

        $this->viewingSessionId    = $sessionId;
        $this->viewingSessionTitle = $session->title ?? 'Untitled session';
        $this->viewingSessionDate  = $session->started_at->format('d M Y, H:i');

        $this->viewingMessages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['room']    ?? $m->booking_prefill) : null,
            'vehicle_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['vehicle'] ?? null) : null,
            'sent_at'         => $m->sent_at->format('H:i'),
        ])->values()->toArray();

        $this->panel = 'session';
    }

    public function backToHistory(): void
    {
        $this->viewingSessionId = null;
        $this->viewingMessages  = [];
        $this->panel            = 'history';
    }

    public function deleteSession(int $sessionId): void
    {
        AiChatSession::where('user_id', Auth::id())->where('id', $sessionId)->delete();
        $this->loadHistorySessions();

        if ($this->viewingSessionId === $sessionId) {
            $this->backToHistory();
        }
    }

    public function restoreSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())
            ->find($sessionId);

        if (! $session) {
            return;
        }

        $this->archiveCurrentSession();

        $this->activeSessionId = $session->id;
        $this->messages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['room']    ?? $m->booking_prefill) : null,
            'vehicle_prefill' => is_array($m->booking_prefill) ? ($m->booking_prefill['vehicle'] ?? null) : null,
            'sent_at'         => $m->sent_at->format('H:i'),
        ])->values()->toArray();

        // Rebuild conversation history from the restored session
        $this->conversationHistory = [];
        foreach ($this->messages as $msg) {
            $this->appendToHistory($msg['role'] === 'user' ? 'user' : 'assistant', $msg['text']);
        }

        $this->panel = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    // ─────────────────────────────────────────────────────────
    // Chat
    // ─────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $text = trim($this->message);

        if ($text === '' || $this->isLoading) {
            return;
        }

        $this->ensureSession();

        $sentAt = now()->format('H:i');

        $this->messages[] = [
            'role'            => 'user',
            'text'            => $text,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => $sentAt,
        ];
        $this->message   = '';
        $this->isLoading = true;

        $this->persistMessage('user', $text, null, null);
        $this->appendToHistory('user', $text);

        $reply    = 'Sorry, something went wrong. Please try again.';
        $prefill  = null;
        $vprefill = null;

        try {
            if ($this->userRole() === 'manager') {
                [$reply] = $this->callManagerAI($text);
            } else {
                [$reply, $prefill, $vprefill] = $this->callReceptionistAI($text);
            }
        } catch (\Throwable $e) {
            Log::error('ChatModal: AI call failed', ['error' => $e->getMessage()]);
        }

        $replySentAt = now()->format('H:i');

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $reply,
            'booking_prefill' => $prefill,
            'vehicle_prefill' => $vprefill,
            'sent_at'         => $replySentAt,
        ];
        $this->isLoading = false;

        $this->persistMessage('assistant', $reply, $prefill, $vprefill);
        $this->appendToHistory('assistant', $reply);

        $this->dispatch('chat-scroll-bottom');
    }

    public function clearChat(): void
    {
        $this->archiveCurrentSession();

        $this->messages             = [];
        $this->message              = '';
        $this->isLoading            = false;
        $this->activeSessionId      = null;
        $this->conversationHistory  = [];
        $this->bookingDraft         = app(BookingDraftService::class)->emptyDraft();
        $this->panel                = 'chat';

        $this->seedGreeting();
    }

    // ─────────────────────────────────────────────────────────
    // Manager AI call
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{0: string}  [reply]
     */
    private function callManagerAI(string $userMessage): array
    {
        $companyId    = Auth::user()->company_id;
        $builder      = app(PromptBuilder::class);
        $context      = $builder->buildManagerContext($companyId);
        $systemPrompt = $builder->managerSystemPrompt($context);

        // Manager uses multi-turn history for follow-up questions
        $history = $this->getRecentHistory(exclude: 'last');
        $ai      = app(AIService::class);
        $raw     = $ai->chat($systemPrompt, $userMessage, $history);

        // Strip Qwen3 / DeepSeek think blocks before returning
        $reply = $this->stripThinkBlocks($raw);

        return [$reply];
    }

    // ─────────────────────────────────────────────────────────
    // Receptionist AI call (with conversational booking draft)
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: array|null, 2: array|null}  [reply, booking_prefill, vehicle_prefill]
     */
    private function callReceptionistAI(string $userMessage): array
    {
        $companyId    = Auth::user()->company_id;
        $builder      = app(PromptBuilder::class);
        $draftService = app(BookingDraftService::class);

        // Build the data context (live DB snapshot)
        $dataContext  = $builder->buildReceptionistContext($companyId);

        // Inject the current booking draft state so the AI knows what it collected
        $draftContext = $draftService->buildDraftContext($this->bookingDraft);

        $systemPrompt = $builder->receptionistSystemPrompt($dataContext, $draftContext);

        // Pass recent conversation history for multi-turn awareness
        $history = $this->getRecentHistory(exclude: 'last');
        $ai      = app(AIService::class);
        $raw     = $ai->chat($systemPrompt, $userMessage, $history);

        // Parse the structured JSON response
        $parsed = $this->parseIntentResponse($raw, $companyId);

        $reply        = $parsed['reply'];
        $prefill      = $parsed['booking_prefill']  ?? [];
        $vprefill     = $parsed['vehicle_prefill']  ?? [];
        $isComplete   = $parsed['booking_complete'] ?? false;

        // ── Update the booking draft ──────────────────────────
        $this->bookingDraft = $draftService->mergePrefill(
            $this->bookingDraft,
            $this->hasAnyValue($prefill)  ? $prefill  : null,
            $this->hasAnyValue($vprefill) ? $vprefill : null
        );

        // Resolve natural-language dates / times the AI may have left in the draft
        $this->bookingDraft = $draftService->resolveDraftDates($this->bookingDraft);

        // Resolve room/vehicle IDs from names if not already set
        $this->bookingDraft = $draftService->resolveRoomId($this->bookingDraft, $companyId);
        $this->bookingDraft = $draftService->resolveVehicleId($this->bookingDraft, $companyId);

        // ── Auto-submit when complete ─────────────────────────
        // Triggers when: AI signals booking_complete:true, OR the draft has all required fields.
        $roomComplete    = $isComplete && $this->bookingDraft['type'] === 'room';
        $vehicleComplete = $isComplete && $this->bookingDraft['type'] === 'vehicle';

        if ($roomComplete || $draftService->isRoomDraftComplete($this->bookingDraft)) {
            $payload = $draftService->buildRoomPayload($this->bookingDraft);
            $this->bookingDraft = $draftService->resetDraft();

            // Dispatch to the existing QuickBookModal (preserves all validation).
            // QuickBookModal::open() receives a single $payload array via the On listener.
            $this->dispatch('open-quick-book', $payload);

            // Return only the text reply — no prefill card (booking modal opens instead)
            return [$reply, null, null];
        }

        if ($vehicleComplete || $draftService->isVehicleDraftComplete($this->bookingDraft)) {
            $payload = $draftService->buildVehiclePayload($this->bookingDraft);
            $this->bookingDraft = $draftService->resetDraft();

            $this->dispatch('open-quick-vehicle-book', $payload);

            return [$reply, null, null];
        }

        // ── Not yet complete — return prefill card if AI returned one ──
        // (for rebook suggestions where the user should confirm before submitting)
        $outPrefill  = $this->hasAnyValue($prefill)  ? $prefill  : null;
        $outVprefill = $this->hasAnyValue($vprefill) ? $vprefill : null;

        return [$reply, $outPrefill, $outVprefill];
    }

    // ─────────────────────────────────────────────────────────
    // Response parser (moved from GroqService — unchanged logic)
    // ─────────────────────────────────────────────────────────

    /**
     * Parse the raw AI response for the receptionist role.
     *
     * @return array{reply: string, booking_prefill: array, vehicle_prefill: array, booking_complete: bool}
     */
    private function parseIntentResponse(string $raw, ?int $companyId): array
    {
        $empty = [
            'reply'            => $raw,
            'booking_prefill'  => [],
            'vehicle_prefill'  => [],
            'booking_complete' => false,
        ];

        $raw = trim($raw);
        $raw = $this->stripThinkBlocks($raw);

        // Strip markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '',            $raw);
        $raw = trim($raw);

        if (! str_starts_with($raw, '{')) {
            return $empty;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['reply'])) {
            return $empty;
        }

        $reply          = (string) ($decoded['reply']            ?? '');
        $bookingComplete = (bool)  ($decoded['booking_complete'] ?? false);

        // ── booking_prefill (room) ────────────────────────────
        $prefill = $decoded['booking_prefill'] ?? [];
        if (is_array($prefill)) {
            // Resolve room_id by name when model omitted the integer
            if (empty($prefill['room_id']) && ! empty($prefill['room_name'])) {
                $room = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
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

            $validBookingTypes = ['meeting', 'online_meeting'];
            $prefill['booking_type'] = in_array($prefill['booking_type'] ?? '', $validBookingTypes, true)
                ? $prefill['booking_type'] : null;

            $validProviders = ['google_meet', 'zoom'];
            $prefill['online_provider'] = in_array($prefill['online_provider'] ?? '', $validProviders, true)
                ? $prefill['online_provider'] : null;

            if (($prefill['booking_type'] ?? '') === 'online_meeting') {
                $prefill['room_id']   = null;
                $prefill['room_name'] = null;
            }
        }

        // ── vehicle_prefill ───────────────────────────────────
        $vprefill = $decoded['vehicle_prefill'] ?? [];
        if (is_array($vprefill)) {
            if (empty($vprefill['vehicle_id']) && (! empty($vprefill['vehicle_name']) || ! empty($vprefill['plate_number']))) {
                $vq = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId));
                if (! empty($vprefill['vehicle_name'])) {
                    $vq = $vq->where('name', 'like', '%' . trim($vprefill['vehicle_name']) . '%');
                } elseif (! empty($vprefill['plate_number'])) {
                    $vq = $vq->where('plate_number', 'like', '%' . trim($vprefill['plate_number']) . '%');
                }
                $vehicle = $vq->first();
                $vprefill['vehicle_id']   = $vehicle?->vehicle_id;
                $vprefill['vehicle_name'] = $vehicle?->name         ?? $vprefill['vehicle_name']  ?? null;
                $vprefill['plate_number'] = $vehicle?->plate_number ?? $vprefill['plate_number']  ?? null;
            }
            $vprefill['vehicle_id']    = isset($vprefill['vehicle_id'])    ? (int) $vprefill['vehicle_id'] : null;
            $vprefill['borrower_name'] = $vprefill['borrower_name'] ?? null;
            $vprefill['department']    = $vprefill['department']    ?? null;
            $vprefill['date_from']     = $vprefill['date_from']     ?? null;
            $vprefill['date_to']       = $vprefill['date_to']       ?? null;
            $vprefill['start_time']    = $vprefill['start_time']    ?? null;
            $vprefill['end_time']      = $vprefill['end_time']      ?? null;
            $vprefill['purpose']       = $vprefill['purpose']       ?? null;
            $vprefill['destination']   = $vprefill['destination']   ?? null;

            $validTypes = ['dinas', 'operasional', 'antar_jemput', 'lainnya'];
            $vprefill['purpose_type'] = in_array($vprefill['purpose_type'] ?? '', $validTypes, true)
                ? $vprefill['purpose_type'] : null;
        }

        return [
            'reply'            => $reply,
            'booking_prefill'  => $prefill  ?? [],
            'vehicle_prefill'  => $vprefill ?? [],
            'booking_complete' => $bookingComplete,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // DB helpers
    // ─────────────────────────────────────────────────────────

    private function ensureSession(): void
    {
        if ($this->activeSessionId) {
            return;
        }

        $session = AiChatSession::create([
            'user_id'    => Auth::id(),
            'role'       => $this->userRole(),
            'title'      => null,
            'started_at' => now(),
        ]);

        $this->activeSessionId = $session->id;
    }

    private function persistMessage(string $role, string $text, ?array $prefill, ?array $vprefill = null): void
    {
        if (! $this->activeSessionId) {
            return;
        }

        AiChatMessage::create([
            'session_id'      => $this->activeSessionId,
            'role'            => $role,
            'text'            => $text,
            'booking_prefill' => $prefill !== null || $vprefill !== null
                ? ['room' => $prefill, 'vehicle' => $vprefill]
                : null,
            'sent_at' => now(),
        ]);

        if ($role === 'user') {
            $session = AiChatSession::find($this->activeSessionId);
            if ($session && empty($session->title)) {
                $session->update(['title' => AiChatSession::titleFromMessage($text)]);
            }
        }
    }

    private function archiveCurrentSession(): void
    {
        if ($this->activeSessionId) {
            $session = AiChatSession::find($this->activeSessionId);
            $session?->close();
            $this->activeSessionId = null;
        }
    }

    private function loadHistorySessions(): void
    {
        $this->historySessions = AiChatSession::where('user_id', Auth::id())
            ->whereNotNull('ended_at')
            ->whereNotNull('title')
            ->withCount('messages')
            ->orderByDesc('started_at')
            ->limit(30)
            ->get()
            ->map(fn(AiChatSession $s) => [
                'id'            => $s->id,
                'title'         => $s->title,
                'role'          => $s->role,
                'started_at'    => $s->started_at->format('d M Y, H:i'),
                'message_count' => $s->messages_count,
            ])
            ->values()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────
    // Multi-turn history helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Append a turn to the in-memory conversation history,
     * keeping it capped at max_draft_turns.
     */
    private function appendToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = [
            'role'    => $role,
            'content' => $content,
        ];

        $max = (int) config('ai.max_draft_turns', 10);
        if (count($this->conversationHistory) > $max) {
            $this->conversationHistory = array_slice($this->conversationHistory, -$max);
        }
    }

    /**
     * Return recent history for the AI call, optionally excluding the last entry
     * (because the current user message is passed separately as $userPrompt).
     *
     * @param  string  $exclude  'last' to drop the last entry, '' to include all.
     */
    private function getRecentHistory(string $exclude = ''): array
    {
        $history = $this->conversationHistory;

        if ($exclude === 'last' && ! empty($history)) {
            array_pop($history);
        }

        return $history;
    }

    // ─────────────────────────────────────────────────────────
    // Export helpers (manager only — unchanged)
    // ─────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        if ($this->userRole !== 'manager') {
            return;
        }
        $this->js('window.open(' . json_encode(route('chat.export.pdf')) . ", '_blank')");
    }

    public function exportCsv(): void
    {
        if ($this->userRole !== 'manager') {
            return;
        }
        $this->js('window.open(' . json_encode(route('chat.export.csv')) . ", '_blank')");
    }

    // ─────────────────────────────────────────────────────────
    // Misc helpers
    // ─────────────────────────────────────────────────────────

    private function seedGreeting(): void
    {
        $role     = $this->userRole();
        $greeting = $role === 'manager'
            ? "Hello! I'm your analytics assistant. Ask me about bookings, occupancy trends, vehicle usage, or any statistics."
            : "Hello! I'm your booking assistant. Ask me about today's schedule, pending approvals, or say something like \"Book Meeting Room A tomorrow from 9 to 11 for the weekly sync\" and I'll create the booking for you.";

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $greeting,
            'booking_prefill' => null,
            'vehicle_prefill' => null,
            'sent_at'         => now()->format('H:i'),
        ];
    }

    private function userRole(): string
    {
        return $this->resolveUserRole();
    }

    private function resolveUserRole(): string
    {
        $user = Auth::user();
        if (! $user) {
            return 'receptionist';
        }

        $roleName = strtolower(
            $user->role?->name
            ?? $user->role_name
            ?? ''
        );

        return str_contains($roleName, 'manager') ? 'manager' : 'receptionist';
    }

    /**
     * Strip Qwen3 / DeepSeek <think>…</think> reasoning blocks from raw output.
     */
    private function stripThinkBlocks(string $raw): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/si', '', $raw));
    }

    /**
     * Return true if any value in the array is non-null and non-empty.
     */
    private function hasAnyValue(array $arr): bool
    {
        foreach ($arr as $v) {
            if ($v !== null && $v !== '') {
                return true;
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.components.ui.chat-modal');
    }
}
