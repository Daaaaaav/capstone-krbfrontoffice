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
    aria-labelledby="it-officer-chat-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div x-on:click="show = false; $wire.closeModal()"
         class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300"></div>

    {{-- Drawer shell --}}
    <div class="fixed bottom-[5rem] right-6 w-full max-w-sm h-[72vh] flex flex-col z-[70]">
        <div class="relative overflow-hidden rounded-2xl border border-border bg-card shadow-2xl w-full h-full flex flex-col">

            {{-- ───────────────────────────── HEADER ───────────────────────────── --}}
            <div class="flex items-center justify-between px-4 py-3 bg-sidebar text-sidebar-foreground border-b border-sidebar-border shadow-sm shrink-0">

                @if ($panel === 'history' || $panel === 'session')
                    {{-- Back button --}}
                    <button type="button"
                            wire:click="{{ $panel === 'session' ? 'backToHistory' : 'showChat' }}"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/70 hover:text-white hover:bg-white/10 transition mr-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold tracking-tight text-white leading-tight truncate" id="it-officer-chat-title">
                            @if ($panel === 'session'){{ $viewingSessionTitle }}
                            @else Chat History
                            @endif
                        </h3>
                        <p class="text-[10px] text-violet-400 font-semibold tracking-wide uppercase mt-0.5">
                            @if ($panel === 'session'){{ $viewingSessionDate }}
                            @else{{ count($historySessions) }} session(s) saved
                            @endif
                        </p>
                    </div>
                @else
                    {{-- IT Officer identity header --}}
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-violet-500/20 flex items-center justify-center border border-violet-500/30 shrink-0">
                            <span class="w-2.5 h-2.5 rounded-full bg-violet-400 animate-pulse"></span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold tracking-tight text-white leading-tight" id="it-officer-chat-title">KRB IT Officer Assistant</h3>
                            <p class="text-[10px] text-violet-400 font-semibold tracking-wide uppercase mt-0.5">Quick Submit &amp; Analytics</p>
                        </div>
                    </div>
                @endif

                {{-- Right: action buttons --}}
                <div class="flex items-center gap-1 ml-2 shrink-0">
                    @if ($panel === 'chat')
                        {{-- History --}}
                        <button type="button" wire:click="showHistory" title="Chat history"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-white hover:bg-white/10 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </button>
                        {{-- Clear / New conversation --}}
                        <button type="button" wire:click="clearChat" title="New conversation"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-white hover:bg-white/10 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    @endif
                    {{-- Close --}}
                    <button type="button" x-on:click="show = false; $wire.closeModal()"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/70 hover:text-white hover:bg-white/10 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            {{-- /HEADER --}}

            {{-- ─────────────────────────── PANEL: CHAT ─────────────────────────── --}}
            @if ($panel === 'chat')

            {{-- Quick Submit shortcut bar --}}
            <div class="px-3 pt-2.5 pb-2 border-b border-border/60 bg-muted/10 shrink-0">
                <p class="text-[10px] text-muted-foreground/60 uppercase tracking-wider font-semibold mb-1.5">Quick Submit</p>
                <div class="flex flex-wrap gap-1.5">
                    {{-- Add User --}}
                    <button type="button" wire:click="quickSubmitUser"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-green-100 text-green-800 hover:bg-green-200 border border-green-200 transition active:scale-95 shrink-0"
                            title="Add User">
                        <span>🟢</span>
                        <span>Add User</span>
                    </button>
                    {{-- Add Room --}}
                    <button type="button" wire:click="quickSubmitRoom"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-orange-100 text-orange-800 hover:bg-orange-200 border border-orange-200 transition active:scale-95 shrink-0"
                            title="Add Room">
                        <span>🟠</span>
                        <span>Add Room</span>
                    </button>
                    {{-- Add Vehicle --}}
                    <button type="button" wire:click="quickSubmitVehicle"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-blue-100 text-blue-800 hover:bg-blue-200 border border-blue-200 transition active:scale-95 shrink-0"
                            title="Add Vehicle">
                        <span>🔵</span>
                        <span>Add Vehicle</span>
                    </button>
                    {{-- Add Storage --}}
                    <button type="button" wire:click="quickSubmitStorage"
                            class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-purple-100 text-purple-800 hover:bg-purple-200 border border-purple-200 transition active:scale-95 shrink-0"
                            title="Add Storage">
                        <span>🟣</span>
                        <span>Add Storage</span>
                    </button>
                </div>
            </div>

            {{-- Active Quick Submit state indicator --}}
            @if ($quickSubmitState['active'] ?? false)
                @php
                    $entityLabels = ['user' => 'User', 'room' => 'Room', 'vehicle' => 'Vehicle', 'storage' => 'Storage'];
                    $entityLabel  = $entityLabels[$quickSubmitState['entity'] ?? ''] ?? 'Entity';
                    $missing      = $quickSubmitState['missing'] ?? [];
                    $awaitingConf = $quickSubmitState['awaiting_confirm'] ?? false;
                @endphp
                <div class="px-3 py-1.5 bg-violet-50 border-b border-violet-200/60 shrink-0">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse shrink-0"></div>
                            <span class="text-[11px] font-semibold text-violet-700">
                                {{ ucfirst($quickSubmitState['action'] ?? 'Create') }} {{ $entityLabel }}
                                @if ($awaitingConf)
                                    — Waiting for confirmation
                                @elseif (! empty($missing))
                                    — Missing: {{ implode(', ', $missing) }}
                                @else
                                    — Ready to confirm
                                @endif
                            </span>
                        </div>
                        <button type="button" wire:click="clearChat"
                                class="text-[10px] text-violet-500 hover:text-violet-700 font-medium shrink-0">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            {{-- Message list --}}
            <div x-ref="messageList"
                 class="flex-grow min-h-0 px-4 py-3 overflow-y-auto bg-muted/20 space-y-3"
                 wire:ignore.self>

                @foreach ($messages as $msg)
                    @if ($msg['role'] === 'user')
                        {{-- User bubble --}}
                        <div class="flex justify-end">
                            <div class="bg-primary text-primary-foreground px-3.5 py-2.5 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm">
                                <p class="text-xs leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                @if (!empty($msg['sent_at']))
                                    <p class="text-[9px] text-primary-foreground/50 mt-1 text-right">{{ $msg['sent_at'] }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- Assistant bubble --}}
                        <div class="flex justify-start">
                            <div class="bg-card border border-border rounded-2xl rounded-tl-none max-w-[85%] shadow-sm overflow-hidden">
                                <div class="px-3.5 py-2.5">
                                    <p class="text-xs text-foreground leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                    @if (!empty($msg['sent_at']))
                                        <p class="text-[9px] text-muted-foreground/50 mt-1">{{ $msg['sent_at'] }}</p>
                                    @endif
                                </div>
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

            {{-- Input --}}
            <div class="px-4 py-3 border-t border-border bg-card shrink-0">
                <form wire:submit.prevent="sendMessage">
                    <div class="flex items-center gap-2">
                        <input type="text"
                               wire:model="message"
                               placeholder="{{ app()->getLocale() === 'id' ? 'Ketik pesan…' : 'Type a message…' }}"
                               autocomplete="off"
                               @disabled($isLoading)
                               class="flex-grow h-9 px-3.5 border border-input rounded-xl bg-background text-xs text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <button type="submit" @disabled($isLoading)
                                class="w-9 h-9 shrink-0 flex items-center justify-center bg-violet-600 hover:bg-violet-700 text-white rounded-xl transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            @if ($isLoading)
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            @else
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            @endif
                        </button>
                    </div>
                </form>
            </div>

            @endif {{-- /panel chat --}}

            {{-- ────────────────────────── PANEL: HISTORY ──────────────────────────── --}}
            @if ($panel === 'history')
            <div class="flex-grow min-h-0 overflow-y-auto bg-muted/10">
                @if (empty($historySessions))
                    <div class="flex flex-col items-center justify-center h-full gap-3 px-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center">
                            <svg class="w-6 h-6 text-muted-foreground/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-xs text-muted-foreground">No saved conversations yet.</p>
                        <p class="text-[10px] text-muted-foreground/60">Start a chat and use the clear button to archive it here.</p>
                    </div>
                @else
                    <div class="divide-y divide-border/50">
                        @foreach ($historySessions as $s)
                            <div class="flex items-start gap-3 px-4 py-3 hover:bg-muted/30 transition group cursor-pointer"
                                 wire:click="viewSession({{ $s['id'] }})">
                                <div class="w-7 h-7 rounded-lg bg-violet-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $s['title'] }}</p>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">{{ $s['started_at'] }} · {{ $s['message_count'] }} msg(s)</p>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition shrink-0">
                                    <button type="button"
                                            wire:click.stop="restoreSession({{ $s['id'] }})"
                                            class="w-6 h-6 flex items-center justify-center rounded text-violet-500 hover:bg-violet-100 transition"
                                            title="Restore">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </button>
                                    <button type="button"
                                            wire:click.stop="deleteSession({{ $s['id'] }})"
                                            wire:confirm="Delete this conversation?"
                                            class="w-6 h-6 flex items-center justify-center rounded text-red-400 hover:bg-red-100 transition"
                                            title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif {{-- /panel history --}}

            {{-- ─────────────────────────── PANEL: SESSION ─────────────────────────── --}}
            @if ($panel === 'session')
            <div class="flex-grow min-h-0 overflow-y-auto bg-muted/20 px-4 py-3 space-y-3">
                @foreach ($viewingMessages as $msg)
                    @if ($msg['role'] === 'user')
                        <div class="flex justify-end">
                            <div class="bg-primary text-primary-foreground px-3.5 py-2.5 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm">
                                <p class="text-xs leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                @if (!empty($msg['sent_at']))
                                    <p class="text-[9px] text-primary-foreground/50 mt-1 text-right">{{ $msg['sent_at'] }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start">
                            <div class="bg-card border border-border rounded-2xl rounded-tl-none max-w-[85%] shadow-sm px-3.5 py-2.5">
                                <p class="text-xs text-foreground leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                @if (!empty($msg['sent_at']))
                                    <p class="text-[9px] text-muted-foreground/50 mt-1">{{ $msg['sent_at'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            {{-- Session footer --}}
            <div class="px-4 py-3 border-t border-border bg-card shrink-0">
                <button type="button" wire:click="restoreSession({{ $viewingSessionId }})"
                        class="w-full flex items-center justify-center gap-2 h-8 px-3 rounded-lg bg-violet-600 text-white text-xs font-semibold hover:bg-violet-700 active:scale-95 transition-all shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Restore this conversation
                </button>
            </div>
            @endif {{-- /panel session --}}

        </div>
    </div>
</div>
