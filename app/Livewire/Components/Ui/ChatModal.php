<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\GroqService;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;

class ChatModal extends Component
{
    // ── UI state ─────────────────────────────────────────────
    public bool   $isOpen       = false;
    public string $message      = '';
    public bool   $isLoading    = false;

    // 'chat' | 'history' | 'session'
    public string $panel        = 'chat';

    // ── Active conversation ───────────────────────────────────
    /**
     * Each entry: [
     *   'role'            => 'user'|'assistant',
     *   'text'            => string,
     *   'booking_prefill' => array|null,
     *   'sent_at'         => string  (human-readable, e.g. "14:03")
     * ]
     */
    public array $messages      = [];

    /** DB id of the current open session (null until first message sent) */
    public ?int $activeSessionId = null;

    // ── History panel ─────────────────────────────────────────
    /**
     * List of past sessions for the sidebar.
     * Each entry: ['id', 'title', 'role', 'started_at', 'message_count']
     */
    public array $historySessions = [];

    /** The session being viewed in the 'session' panel */
    public ?int   $viewingSessionId    = null;
    public array  $viewingMessages     = [];
    public string $viewingSessionTitle = '';
    public string $viewingSessionDate  = '';

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

        if (!$session) {
            return;
        }

        $this->viewingSessionId    = $sessionId;
        $this->viewingSessionTitle = $session->title ?? 'Untitled session';
        $this->viewingSessionDate  = $session->started_at->format('d M Y, H:i');

        $this->viewingMessages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => $m->booking_prefill,
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

        // If viewing the just-deleted session, go back to list
        if ($this->viewingSessionId === $sessionId) {
            $this->backToHistory();
        }
    }

    /**
     * Restore a past session into the active chat, closing any open session.
     */
    public function restoreSession(int $sessionId): void
    {
        $session = AiChatSession::with('messages')
            ->where('user_id', Auth::id())
            ->find($sessionId);

        if (!$session) {
            return;
        }

        // Archive the current live session if it has messages
        $this->archiveCurrentSession();

        $this->activeSessionId = $session->id;
        $this->messages = $session->messages->map(fn(AiChatMessage $m) => [
            'role'            => $m->role,
            'text'            => $m->text,
            'booking_prefill' => $m->booking_prefill,
            'sent_at'         => $m->sent_at->format('H:i'),
        ])->values()->toArray();

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

        // Ensure a DB session exists
        $this->ensureSession();

        $sentAt = now()->format('H:i');

        $this->messages[] = [
            'role'            => 'user',
            'text'            => $text,
            'booking_prefill' => null,
            'sent_at'         => $sentAt,
        ];
        $this->message   = '';
        $this->isLoading = true;

        // Persist user message
        $this->persistMessage('user', $text, null);

        $reply   = 'Sorry, something went wrong. Please try again.';
        $prefill = null;

        try {
            $groq = app(GroqService::class);

            if ($this->userRole() === 'manager') {
                $reply = $groq->managerChat($text);
            } else {
                $result  = $groq->receptionistChatWithIntent($text);
                $reply   = $result['reply'];
                $prefill = $result['booking_prefill'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error('ChatModal: GroqService failed', ['error' => $e->getMessage()]);
        }

        $replySentAt = now()->format('H:i');

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $reply,
            'booking_prefill' => $prefill,
            'sent_at'         => $replySentAt,
        ];
        $this->isLoading = false;

        // Persist assistant message
        $this->persistMessage('assistant', $reply, $prefill);

        $this->dispatch('chat-scroll-bottom');
    }

    public function clearChat(): void
    {
        // Archive the finished conversation
        $this->archiveCurrentSession();

        // Reset to a fresh conversation
        $this->messages        = [];
        $this->message         = '';
        $this->isLoading       = false;
        $this->activeSessionId = null;
        $this->panel           = 'chat';

        $this->seedGreeting();
    }

    // ─────────────────────────────────────────────────────────
    // DB helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Create a new DB session on the first real user message.
     */
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

    /**
     * Save a single message to the DB and update the session title
     * from the first user message.
     */
    private function persistMessage(string $role, string $text, ?array $prefill): void
    {
        if (!$this->activeSessionId) {
            return;
        }

        AiChatMessage::create([
            'session_id'      => $this->activeSessionId,
            'role'            => $role,
            'text'            => $text,
            'booking_prefill' => $prefill,
            'sent_at'         => now(),
        ]);

        // Set title from the first user message
        if ($role === 'user') {
            $session = AiChatSession::find($this->activeSessionId);
            if ($session && empty($session->title)) {
                $session->update(['title' => AiChatSession::titleFromMessage($text)]);
            }
        }
    }

    /**
     * Close the current DB session (mark ended_at).
     */
    private function archiveCurrentSession(): void
    {
        if ($this->activeSessionId) {
            $session = AiChatSession::find($this->activeSessionId);
            $session?->close();
            $this->activeSessionId = null;
        }
    }

    /**
     * Load the 20 most-recent completed sessions for the history panel.
     */
    private function loadHistorySessions(): void
    {
        $this->historySessions = AiChatSession::where('user_id', Auth::id())
            ->whereNotNull('ended_at')
            ->withCount('messages')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn(AiChatSession $s) => [
                'id'            => $s->id,
                'title'         => $s->title ?? 'Untitled session',
                'role'          => $s->role,
                'started_at'    => $s->started_at->format('d M Y, H:i'),
                'message_count' => $s->messages_count,
            ])
            ->values()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────
    // Misc helpers
    // ─────────────────────────────────────────────────────────

    private function seedGreeting(): void
    {
        $role     = $this->userRole();
        $greeting = $role === 'manager'
            ? "Hello! I'm your analytics assistant. Ask me about bookings, occupancy trends, vehicle usage, or any statistics."
            : "Hello! I'm your booking assistant. Ask me about today's schedule, pending approvals, recent bookings, or say \"rebook [meeting name] for next Monday\" to pre-fill a new booking.";

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $greeting,
            'booking_prefill' => null,
            'sent_at'         => now()->format('H:i'),
        ];
    }

    private function userRole(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'receptionist';
        }

        $roleName = strtolower(
            $user->role?->name
            ?? $user->role_name
            ?? ''
        );

        return str_contains($roleName, 'manager') ? 'manager' : 'receptionist';
    }

    // ─────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.components.ui.chat-modal');
    }
}
