<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\GroqService;

class ChatModal extends Component
{
    public bool   $isOpen    = false;
    public string $message   = '';
    public bool   $isLoading = false;

    /**
     * Chat history.
     *
     * Each entry: [
     *   'role'           => 'user'|'assistant',
     *   'text'           => string,
     *   'booking_prefill'=> array|null   // only on assistant messages with rebook intent
     * ]
     */
    public array $messages = [];

    // ──────────────────────────────────────────────────────────
    // Modal lifecycle
    // ──────────────────────────────────────────────────────────

    #[On('openChatModal')]
    public function openModal(): void
    {
        $this->isOpen = true;

        // Seed greeting on first open
        if (empty($this->messages)) {
            $role     = $this->userRole();
            $greeting = $role === 'manager'
                ? "Hello! I'm your analytics assistant. Ask me about bookings, occupancy trends, vehicle usage, or any statistics."
                : "Hello! I'm your booking assistant. Ask me about today's schedule, pending approvals, recent bookings, or say \"rebook [meeting name] for next Monday\" to pre-fill a new booking.";

            $this->messages[] = [
                'role'            => 'assistant',
                'text'            => $greeting,
                'booking_prefill' => null,
            ];
        }
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    // ──────────────────────────────────────────────────────────
    // Chat
    // ──────────────────────────────────────────────────────────

    public function sendMessage(): void
    {
        $text = trim($this->message);

        if ($text === '' || $this->isLoading) {
            return;
        }

        $this->messages[] = [
            'role'            => 'user',
            'text'            => $text,
            'booking_prefill' => null,
        ];
        $this->message   = '';
        $this->isLoading = true;

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

        $this->messages[] = [
            'role'            => 'assistant',
            'text'            => $reply,
            'booking_prefill' => $prefill,
        ];
        $this->isLoading = false;

        $this->dispatch('chat-scroll-bottom');
    }

    public function clearChat(): void
    {
        $this->messages  = [];
        $this->message   = '';
        $this->isLoading = false;
        $this->openModal(); // re-seed greeting
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.components.ui.chat-modal');
    }
}
