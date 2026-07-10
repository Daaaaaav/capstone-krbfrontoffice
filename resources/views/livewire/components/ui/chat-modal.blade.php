<div
    x-data="{
        show: @entangle('isOpen'),
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messageList;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-show="show"
    x-on:chat-scroll-bottom.window="scrollToBottom()"
    x-transition:enter="ease-out duration-300 transform"
    x-transition:enter-start="opacity-0 translate-y-8 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="ease-in duration-200 transform"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-8 scale-95"
    class="fixed inset-0 z-[60]"
    aria-labelledby="chat-modal-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-on:click="show = false; $wire.closeModal()"
        class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300"
    ></div>

    {{-- Chat Drawer --}}
    <div class="fixed bottom-[5rem] right-6 w-full max-w-sm h-[70vh] flex flex-col z-[70] transition-all duration-300">
        <div class="relative transform overflow-hidden rounded-2xl border border-border bg-card shadow-2xl w-full h-full flex flex-col">

            {{-- ── HEADER ── --}}
            <div class="flex items-center justify-between px-4 py-3 bg-sidebar text-sidebar-foreground border-b border-sidebar-border shadow-sm shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30 shrink-0">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold tracking-tight text-white leading-tight" id="chat-modal-title">
                            AI Assistant
                        </h3>
                        <p class="text-[10px] text-emerald-400 font-semibold tracking-wide uppercase mt-0.5">
                            Powered by Qwen&nbsp;3
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    {{-- Clear chat --}}
                    <button
                        type="button"
                        wire:click="clearChat"
                        title="Clear conversation"
                        class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-white hover:bg-white/10 transition"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                    {{-- Close --}}
                    <button
                        type="button"
                        x-on:click="show = false; $wire.closeModal()"
                        class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/70 hover:text-white hover:bg-white/10 transition"
                    >
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── MESSAGE LIST ── --}}
            <div
                x-ref="messageList"
                class="flex-grow px-4 py-3 overflow-y-auto bg-muted/20 space-y-3"
                wire:ignore.self
            >
                @foreach ($messages as $msg)
                    @if ($msg['role'] === 'user')
                        {{-- ── User bubble ── --}}
                        <div class="flex justify-end">
                            <div class="bg-primary text-primary-foreground px-3.5 py-2.5 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm">
                                <p class="text-xs leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                            </div>
                        </div>

                    @else
                        {{-- ── Assistant bubble ── --}}
                        <div class="flex justify-start">
                            <div class="bg-card border border-border rounded-2xl rounded-tl-none max-w-[85%] shadow-sm overflow-hidden">

                                {{-- Text part --}}
                                <div class="px-3.5 py-2.5">
                                    <p class="text-xs text-foreground leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                </div>

                                {{-- Pre-fill button — only shown when the AI detected a rebook intent --}}
                                @if (!empty($msg['booking_prefill']) && is_array($msg['booking_prefill']))
                                    @php
                                        $prefill = $msg['booking_prefill'];
                                        // Build the payload the QuickBookModal open() method expects
                                        $payload = [
                                            'roomId'    => $prefill['room_id']             ?? null,
                                            'ymd'       => $prefill['date']                ?? '',
                                            'time'      => $prefill['start_time']          ?? '',
                                            'endTime'   => $prefill['end_time']            ?? '',
                                            'title'     => $prefill['meeting_title']       ?? '',
                                            'attendees' => $prefill['number_of_attendees'] ?? 1,
                                            'notes'     => $prefill['special_notes']       ?? '',
                                            'mode'      => 'rebook',
                                        ];
                                    @endphp
                                    <div class="border-t border-border/60 px-3.5 py-2.5 bg-primary/5">
                                        <button
                                            type="button"
                                            x-data
                                            x-on:click="
                                                $dispatch('open-quick-book', @js($payload));
                                                $wire.closeModal();
                                            "
                                            class="w-full flex items-center justify-center gap-2 h-8 px-3 rounded-lg
                                                   bg-primary text-primary-foreground text-xs font-semibold
                                                   hover:bg-primary/90 active:scale-95 transition-all shadow-sm"
                                        >
                                            {{-- Calendar plus icon --}}
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            Pre-fill Booking Form
                                        </button>

                                        {{-- Summary chip: room · date · time --}}
                                        @if (!empty($prefill['room_name']) || !empty($prefill['date']))
                                            <p class="mt-1.5 text-[10px] text-muted-foreground text-center leading-snug">
                                                @if (!empty($prefill['room_name']))
                                                    {{ $prefill['room_name'] }}
                                                @endif
                                                @if (!empty($prefill['date']))
                                                    &nbsp;·&nbsp;
                                                    {{ \Carbon\Carbon::parse($prefill['date'])->format('d M Y') }}
                                                @endif
                                                @if (!empty($prefill['start_time']) && !empty($prefill['end_time']))
                                                    &nbsp;·&nbsp;
                                                    {{ substr($prefill['start_time'], 0, 5) }}–{{ substr($prefill['end_time'], 0, 5) }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                @endif

                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- Typing indicator --}}
                @if ($isLoading)
                    <div class="flex justify-start">
                        <div class="bg-card border border-border px-4 py-3 rounded-2xl rounded-tl-none shadow-sm">
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:-0.3s]"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:-0.15s]"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-muted-foreground/60 animate-bounce"></span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ── INPUT AREA ── --}}
            <div class="px-4 py-3 border-t border-border bg-card shrink-0">
                <form wire:submit.prevent="sendMessage">
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            wire:model="message"
                            placeholder="Type a message…"
                            autocomplete="off"
                            @disabled($isLoading)
                            class="flex-grow h-9 px-3.5 border border-input rounded-xl bg-background text-xs text-foreground
                                   placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20
                                   focus:border-primary transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                        <button
                            type="submit"
                            @disabled($isLoading)
                            class="w-9 h-9 shrink-0 flex items-center justify-center bg-primary hover:bg-primary/90
                                   text-primary-foreground rounded-xl transition shadow-sm
                                   disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            @if ($isLoading)
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            @else
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            @endif
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
