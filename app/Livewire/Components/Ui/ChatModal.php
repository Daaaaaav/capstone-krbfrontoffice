<?php

namespace App\Livewire\Components\Ui;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use App\Services\GroqService;

class ChatModal extends Component
{
    public bool   $isOpen   = false;
    public string $message  = '';
    public bool   $isLoading = false;

    /**
     * Chat history: array of ['role' => 'user'|'assistant', 'text' => string]
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
                : "Hello! I'm your booking assistant. Ask me about today's schedule, pending approvals, or recent bookings.";

            $this->messages[] = ['role' => 'assistant', 'text' => $greeting];
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

        // Append the user bubble immediately
        $this->messages[] = ['role' => 'user', 'text' => $text];
        $this->message    = '';
        $this->isLoading  = true;

        // Call Groq based on the authenticated user's role
        try {
            $groq  = app(GroqService::class);
            $reply = $this->userRole() === 'manager'
                ? $groq->managerChat($text)
                : $groq->receptionistChat($text);
        } catch (\Throwable $e) {
            $reply = 'Sorry, something went wrong. Please try again.';
            \Illuminate\Support\Facades\Log::error('ChatModal: GroqService failed', ['error' => $e->getMessage()]);
        }

        $this->messages[] = ['role' => 'assistant', 'text' => $reply];
        $this->isLoading  = false;

        // Tell the browser to scroll the message list to the bottom
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

    /**
     * Returns 'manager' or 'receptionist' based on the authenticated user's role.
     */
    private function userRole(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'receptionist';
        }

        // Support role as a relation (role->name) or a direct column
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
