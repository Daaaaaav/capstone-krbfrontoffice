<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\AI\AIService;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ContextRouter;
use App\Services\AI\ItOfficerQuickSubmitService;
use App\Services\AI\ItOfficerToolDispatcher;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;

/**
 * ItOfficerChatModal
 *
 * Dedicated chatbot component for the IT Officer role. Mirrors the UX of
 * the existing ChatModal (history, sessions, loading states, error handling,
 * provider fallback) but adds:
 *
 * - IT Officer system prompt (analytics + Quick Submit guidance)
 * - Quick Submit state tracking via ItOfficerQuickSubmitService
 * - Four management tools via ItOfficerToolDispatcher
 * - Analytics context via ContextRouter with 'it-officer' role
 * - Quick Submit shortcut buttons in the UI
 *
 * The existing ChatModal (Manager/Receptionist) is NOT modified.
 */
class ItOfficerChatModal extends Component
{
    public bool   $isOpen    = false;
    public string $message   = '';
    public bool   $isLoading = false;
    public string $panel     = 'chat';

    public array $messages             = [];
    public ?int  $activeSessionId      = null;
    public array $conversationHistory  = [];

    // Quick Submit structured state
    public array $quickSubmitState = [];

    // Chat history panel
    public array  $historySessions     = [];
    public ?int   $viewingSessionId    = null;
    public array  $viewingMessages     = [];
    public string $viewingSessionTitle = '';
    public string $viewingSessionDate  = '';

    public function mount(): void
    {
        $this->quickSubmitState = app(ItOfficerQuickSubmitService::class)->emptyState();

        // Archive any dangling open sessions from previous page loads
        AiChatSession::where('user_id', Auth::id())
            ->where('role', 'it-officer')
            ->whereNull('ended_at')
            ->whereNotNull('title')
            ->update(['ended_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Modal open/close
    // ─────────────────────────────────────────────────────────────────────────

    #[On('openItOfficerChat')]
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

    // ─────────────────────────────────────────────────────────────────────────
    // Quick Submit shortcut buttons
    // ─────────────────────────────────────────────────────────────────────────

    public function quickSubmitUser(): void
    {
        $this->startQuickSubmitFlow('user', 'create');
        $this->message = '';
        $this->isOpen  = true;
        $this->panel   = 'chat';
    }

    public function quickSubmitRoom(): void
    {
        $this->startQuickSubmitFlow('room', 'create');
        $this->message = '';
        $this->isOpen  = true;
        $this->panel   = 'chat';
    }

    public function quickSubmitVehicle(): void
    {
        $this->startQuickSubmitFlow('vehicle', 'create');
        $this->message = '';
        $this->isOpen  = true;
        $this->panel   = 'chat';
    }

    public function quickSubmitStorage(): void
    {
        $this->startQuickSubmitFlow('storage', 'create');
        $this->message = '';
        $this->isOpen  = true;
        $this->panel   = 'chat';
    }

    private function startQuickSubmitFlow(string $entity, string $action): void
    {
        $service               = app(ItOfficerQuickSubmitService::class);
        $this->quickSubmitState = $service->startFlow($this->quickSubmitState, $entity, $action);

        $prompts = [
            'user'    => [
                'en' => "Sure! Let's add a new user. What is the user's full name?",
                'id' => 'Baik! Mari tambahkan user baru. Siapa nama lengkap user tersebut?',
            ],
            'room'    => [
                'en' => "Sure! Let's add a new room. What is the room name?",
                'id' => 'Baik! Mari tambahkan ruangan baru. Apa nama ruangannya?',
            ],
            'vehicle' => [
                'en' => "Sure! Let's add a new vehicle. What is the vehicle name (e.g. Toyota Innova)?",
                'id' => 'Baik! Mari tambahkan kendaraan baru. Apa nama kendaraannya (mis. Toyota Innova)?',
            ],
            'storage' => [
                'en' => "Sure! Let's add a new storage area. What is the storage code?",
                'id' => 'Baik! Mari tambahkan storage baru. Apa kode unik untuk storage ini?',
            ],
        ];

        $locale  = app()->getLocale();
        $lang    = $locale === 'id' ? 'id' : 'en';
        $text    = $prompts[$entity][$lang] ?? "Let's add a new {$entity}. What is the {$entity} name?";

        $this->ensureSession();
        $this->messages[] = [
            'role'    => 'assistant',
            'text'    => $text,
            'sent_at' => now()->format('H:i'),
        ];
        $this->persistMessage('assistant', $text, null);
        $this->appendToHistory('assistant', $text);
        $this->dispatch('chat-scroll-bottom');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Send message
    // ─────────────────────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $text = trim($this->message);
        if ($text === '' || $this->isLoading) return;

        $this->ensureSession();
        $sentAt = now()->format('H:i');

        $this->messages[] = [
            'role'    => 'user',
            'text'    => $text,
            'sent_at' => $sentAt,
        ];
        $this->message   = '';
        $this->isLoading = true;

        $this->persistMessage('user', $text, null);
        $this->appendToHistory('user', $text);

        $reply = 'Maaf, terjadi kesalahan. Silakan coba lagi. / Sorry, something went wrong. Please try again.';

        // Central ScopeGuard validation
        $guard = app(\App\Services\AI\ScopeGuard::class)->validate($text, Auth::user());
        if (! $guard['allowed']) {
            $reply = $guard['refusal'] ?? \App\Services\AI\ScopeGuard::REFUSAL_EN;
        } elseif ($guard['is_utility'] ?? false) {
            $reply = $guard['utility_response'];
        } else {
            try {
                $reply = $this->callItOfficerAI($text);
            } catch (\Throwable $e) {
                Log::error('ItOfficerChatModal: AI call failed', [
                    'class' => get_class($e),
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        $this->messages[] = [
            'role'    => 'assistant',
            'text'    => $reply,
            'sent_at' => now()->format('H:i'),
        ];
        $this->isLoading = false;

        $this->persistMessage('assistant', $reply, null);
        $this->appendToHistory('assistant', $reply);

        $this->dispatch('chat-scroll-bottom');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core AI call
    // ─────────────────────────────────────────────────────────────────────────

    private function callItOfficerAI(string $userMessage): string
    {
        $user            = Auth::user();
        $companyId       = $user?->company_id;
        $companyName     = $user?->company?->company_name ?? 'Kebun Raya Bogor';
        $service         = app(ItOfficerQuickSubmitService::class);
        $router          = app(ContextRouter::class);
        $builder         = app(PromptBuilder::class);
        $ai              = app(AIService::class);

        // ── 1. Handle awaiting-confirmation state ────────────────────────────
        if ($this->quickSubmitState['active'] && $this->quickSubmitState['awaiting_confirm']) {
            if ($service->isConfirmation($userMessage)) {
                $this->quickSubmitState = $service->confirm($this->quickSubmitState);
                return $this->executeQuickSubmit();
            }
            // Not a confirmation — check if they want to change something or cancel
            $lower = mb_strtolower($userMessage);
            if ($this->matchesCancel($lower)) {
                $this->quickSubmitState = $service->reset();
                return 'Operasi dibatalkan. / Operation cancelled.';
            }
            // Otherwise treat as field correction — fall through to normal AI processing
        }

        // ── 2. Check for cancel intent ───────────────────────────────────────
        if ($this->quickSubmitState['active'] && $this->matchesCancel(mb_strtolower($userMessage))) {
            $this->quickSubmitState = $service->reset();
            return 'Operasi dibatalkan. Apakah ada yang bisa saya bantu? / Operation cancelled. How can I help you?';
        }

        // ── 3. Detect new Quick Submit intent (may start a new flow) ─────────
        if (! $this->quickSubmitState['active']) {
            $intent = $service->detectIntent($userMessage);
            if (isset($intent['entity'], $intent['action'])) {
                $this->quickSubmitState = $service->startFlow($this->quickSubmitState, $intent['entity'], $intent['action']);
            }
        }

        // ── 4. Build context ─────────────────────────────────────────────────
        $history       = $this->getRecentHistory(exclude: 'last');
        $routingResult = $router->routeWithMetadata($userMessage, $companyId, 'it-officer', $history);
        $dataContext   = $routingResult->assembledContext;

        // ── 5. Build Quick Submit state context for prompt ───────────────────
        $quickSubmitContext = $service->buildStateContext($this->quickSubmitState);

        // ── 6. Build system prompt ────────────────────────────────────────────
        $systemPrompt = $builder->itOfficerSystemPrompt($dataContext, $quickSubmitContext, $companyName);

        // ── 7. Call AI ────────────────────────────────────────────────────────
        $raw   = $ai->chat($systemPrompt, $userMessage, $history);
        $reply = $this->stripThinkBlocks($raw);

        // ── 8. Parse any field extractions from the AI reply ─────────────────
        // The AI may include a JSON block with extracted fields; parse and merge
        $extracted = $this->extractFieldsFromReply($reply, $userMessage);
        if (! empty($extracted)) {
            $this->quickSubmitState = $service->mergeFields($this->quickSubmitState, $extracted);
        }

        // ── 9. If all fields collected and not yet asking for confirmation ────
        if ($this->quickSubmitState['active']
            && $service->isReadyForConfirmation($this->quickSubmitState)
            && ! $this->quickSubmitState['awaiting_confirm']
        ) {
            $this->quickSubmitState = $service->requestConfirmation($this->quickSubmitState);
        }

        return $reply;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Execute Quick Submit after confirmation
    // ─────────────────────────────────────────────────────────────────────────

    private function executeQuickSubmit(): string
    {
        $state     = $this->quickSubmitState;
        $entity    = $state['entity'];
        $action    = $state['action'];
        $collected = $state['collected'];

        $dispatcher = app(ItOfficerToolDispatcher::class);

        $toolName = match ($entity) {
            'user'    => 'manage_user',
            'room'    => 'manage_room',
            'vehicle' => 'manage_vehicle',
            'storage' => 'manage_storage',
            default   => null,
        };

        if (! $toolName) {
            $this->quickSubmitState = app(ItOfficerQuickSubmitService::class)->reset();
            return 'Unknown entity type. Please start over.';
        }

        // For update, include the target_id in the data
        if ($action === 'update' && $state['target_id']) {
            $collected[$entity . '_id'] = $state['target_id'];
        }

        $result = $dispatcher->dispatch($toolName, [
            'action' => $action,
            'data'   => $collected,
        ]);

        $this->quickSubmitState = app(ItOfficerQuickSubmitService::class)->reset();

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Field extraction helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attempt to extract structured fields from the user's raw message when the
     * AI hasn't returned a JSON block. This supplements the AI's own extraction.
     * Only called for active Quick Submit flows.
     */
    private function extractFieldsFromReply(string $reply, string $userMessage): array
    {
        if (! $this->quickSubmitState['active']) {
            return [];
        }

        // The AI is instructed to manage field collection itself via the system
        // prompt. Here we do a best-effort extraction from the raw user message
        // for a few common patterns to help fill in the state even if the AI
        // decides not to call the validate_* tool in this turn.

        $entity    = $this->quickSubmitState['entity'];
        $missing   = $this->quickSubmitState['missing'];
        $extracted = [];
        $msg       = trim($userMessage);

        if ($entity === 'user') {
            if (in_array('role', $missing, true)) {
                if (preg_match('/\b(manager|receptionist)\b/i', $msg, $m)) {
                    $extracted['role'] = ucfirst(strtolower($m[1]));
                }
            }
            if (in_array('email', $missing, true)) {
                if (preg_match('/[\w._%+\-]+@[\w.\-]+\.[a-z]{2,}/i', $msg, $m)) {
                    $extracted['email'] = strtolower($m[0]);
                }
            }
            if (in_array('full_name', $missing, true) && strlen($msg) <= 60 && ! str_contains($msg, '@')) {
                // Heuristic: short message that isn't an email is likely a name
                // Only use if no other pattern matched above
                if (empty($extracted)) {
                    $extracted['full_name'] = $msg;
                }
            }
        }

        if ($entity === 'room') {
            if (in_array('capacity', $missing, true)) {
                if (preg_match('/\b(\d+)\b/', $msg, $m)) {
                    $extracted['capacity'] = (int) $m[1];
                }
            }
            if (in_array('room_name', $missing, true) && ! is_numeric(trim($msg))) {
                $extracted['room_name'] = $msg;
            }
        }

        if ($entity === 'vehicle') {
            if (in_array('plate_number', $missing, true)) {
                if (preg_match('/\b[A-Z]{1,2}\s*\d{1,4}\s*[A-Z]{1,3}\b/i', $msg, $m)) {
                    $extracted['plate_number'] = strtoupper(preg_replace('/\s+/', ' ', $m[0]));
                }
            }
            if (in_array('year', $missing, true)) {
                if (preg_match('/\b(19|20)\d{2}\b/', $msg, $m)) {
                    $extracted['year'] = $m[0];
                }
            }
        }

        if ($entity === 'storage') {
            // Storage code is short (≤100 chars), typically alphanumeric
            if (in_array('code', $missing, true) && preg_match('/^[A-Za-z0-9_\-]+$/', trim($msg)) && strlen(trim($msg)) <= 100) {
                $extracted['code'] = trim($msg);
            } elseif (in_array('name', $missing, true)) {
                $extracted['name'] = $msg;
            }
        }

        return $extracted;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // History panel
    // ─────────────────────────────────────────────────────────────────────────

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
            ->where('role', 'it-officer')
            ->find($sessionId);

        if (! $session) return;

        $this->viewingSessionId    = $sessionId;
        $this->viewingSessionTitle = $session->title ?? 'Untitled session';
        $this->viewingSessionDate  = $session->started_at->format('d M Y, H:i');

        $this->viewingMessages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'    => $m->role,
            'text'    => $m->text,
            'sent_at' => $m->sent_at->format('H:i'),
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
        AiChatSession::where('user_id', Auth::id())
            ->where('role', 'it-officer')
            ->where('id', $sessionId)
            ->delete();
        $this->loadHistorySessions();
        if ($this->viewingSessionId === $sessionId) {
            $this->backToHistory();
        }
    }

    public function restoreSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())
            ->where('role', 'it-officer')
            ->find($sessionId);

        if (! $session) return;

        $this->archiveCurrentSession();
        $this->activeSessionId = $session->id;

        $this->messages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'    => $m->role,
            'text'    => $m->text,
            'sent_at' => $m->sent_at->format('H:i'),
        ])->values()->toArray();

        $this->conversationHistory = [];
        foreach ($this->messages as $msg) {
            $this->appendToHistory($msg['role'] === 'user' ? 'user' : 'assistant', $msg['text']);
        }

        $this->panel = 'chat';
        $this->dispatch('chat-scroll-bottom');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clear chat
    // ─────────────────────────────────────────────────────────────────────────

    public function clearChat(): void
    {
        $this->archiveCurrentSession();
        $this->messages            = [];
        $this->message             = '';
        $this->isLoading           = false;
        $this->activeSessionId     = null;
        $this->conversationHistory = [];
        $this->quickSubmitState    = app(ItOfficerQuickSubmitService::class)->emptyState();
        $this->panel               = 'chat';
        $this->seedGreeting();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function seedGreeting(): void
    {
        $locale = app()->getLocale();
        $text   = $locale === 'id'
            ? "Halo! Saya KRB IT Officer Assistant. Saya bisa membantu Anda:\n\n• **Tambah / ubah** User, Ruangan, Kendaraan, Storage\n• **Jawab pertanyaan** tentang booking, occupancy, visitor, dan statistik sistem\n\nCoba: \"Tambahkan ruang Rafflesia kapasitas 50\" atau \"Berapa booking bulan ini?\""
            : "Hello! I'm the KRB IT Officer Assistant. I can help you:\n\n• **Add / update** Users, Rooms, Vehicles, Storage areas\n• **Answer questions** about bookings, occupancy, visitors, and system stats\n\nTry: \"Add room Rafflesia capacity 50\" or \"How many bookings this month?\"";

        $this->messages[] = [
            'role'    => 'assistant',
            'text'    => $text,
            'sent_at' => now()->format('H:i'),
        ];
    }

    private function ensureSession(): void
    {
        if ($this->activeSessionId) return;
        $session = AiChatSession::create([
            'user_id'    => Auth::id(),
            'role'       => 'it-officer',
            'title'      => null,
            'started_at' => now(),
        ]);
        $this->activeSessionId = $session->id;
    }

    private function persistMessage(string $role, string $text, ?array $prefill = null): void
    {
        if (! $this->activeSessionId) return;

        AiChatMessage::create([
            'session_id'      => $this->activeSessionId,
            'role'            => $role,
            'text'            => $text,
            'booking_prefill' => null, // IT Officer doesn't use booking prefill cards
            'sent_at'         => now(),
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
            AiChatSession::find($this->activeSessionId)?->close();
            $this->activeSessionId = null;
        }
    }

    private function loadHistorySessions(): void
    {
        $this->historySessions = AiChatSession::where('user_id', Auth::id())
            ->where('role', 'it-officer')
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

    private function appendToHistory(string $role, string $content): void
    {
        $this->conversationHistory[] = ['role' => $role, 'content' => $content];
        $max = (int) config('ai.max_draft_turns', 10);
        if (count($this->conversationHistory) > $max) {
            $this->conversationHistory = array_slice($this->conversationHistory, -$max);
        }
    }

    private function getRecentHistory(string $exclude = ''): array
    {
        $history = $this->conversationHistory;
        if ($exclude === 'last' && ! empty($history)) {
            array_pop($history);
        }

        // Keep last 8 turns for IT Officer (Quick Submit needs more context)
        if (count($history) > 8) {
            $history = array_slice($history, -8);
        }

        return $history;
    }

    private function stripThinkBlocks(string $raw): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/si', '', $raw));
    }

    private function matchesCancel(string $lower): bool
    {
        $cancelWords = ['batal', 'cancel', 'stop', 'keluar', 'quit', 'exit', 'reset', 'mulai ulang'];
        foreach ($cancelWords as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }
        return false;
    }

    public function render()
    {
        return view('livewire.components.ui.it-officer-chat-modal');
    }
}
