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
    <div x-on:click="show = false; $wire.closeModal()"
         class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300"></div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- Drawer shell                                        --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="fixed bottom-[5rem] right-6 w-full max-w-sm h-[70vh] flex flex-col z-[70]">
        <div class="relative rounded-2xl border border-border bg-card shadow-2xl w-full h-full flex flex-col">

            {{-- ─────────────────────────────────────────── --}}
            {{-- HEADER (shared across all panels)           --}}
            {{-- ─────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-4 py-3 bg-sidebar text-sidebar-foreground border-b border-sidebar-border shadow-sm shrink-0">

                {{-- Left: back button (history/session panels) or avatar --}}
                @if ($panel === 'history' || $panel === 'session')
                    <button type="button" wire:click="{{ $panel === 'session' ? 'backToHistory' : 'showChat' }}"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/70 hover:text-white hover:bg-white/10 transition mr-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold tracking-tight text-white leading-tight truncate" id="chat-modal-title">
                            @if ($panel === 'session')
                                {{ $viewingSessionTitle }}
                            @else
                                Chat History
                            @endif
                        </h3>
                        <p class="text-[10px] text-emerald-400 font-semibold tracking-wide uppercase mt-0.5">
                            @if ($panel === 'session')
                                {{ $viewingSessionDate }}
                            @else
                                {{ count($historySessions) }} session(s) saved
                            @endif
                        </p>
                    </div>
                @else
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30 shrink-0">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold tracking-tight text-white leading-tight" id="chat-modal-title">AI Assistant</h3>
                            <p class="text-[10px] text-emerald-400 font-semibold tracking-wide uppercase mt-0.5">Powered by Qwen&nbsp;3</p>
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
                        {{-- Clear --}}
                        <button type="button" wire:click="clearChat" title="New conversation"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-white hover:bg-white/10 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                        {{-- Export buttons (manager only) --}}
                        @if ($userRole === 'manager' && count($messages) > 1)
                            {{-- Export PDF --}}
                            <button type="button" wire:click="exportPdf" title="Export chat as PDF"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-red-400 hover:bg-white/10 transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </button>
                            {{-- Export CSV --}}
                            <button type="button" wire:click="exportCsv" title="Export chat as CSV (Excel)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-emerald-400 hover:bg-white/10 transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 10h18M3 14h18M10 3v18M14 3v18M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                </svg>
                            </button>
                        @endif
                    @endif
                    {{-- Export buttons for session viewer (manager only) --}}
                    @if ($panel === 'session' && $userRole === 'manager' && count($viewingMessages) > 0)
                        {{-- Export PDF --}}
                        <button type="button" wire:click="exportPdf" title="Export session as PDF"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-red-400 hover:bg-white/10 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </button>
                        {{-- Export CSV --}}
                        <button type="button" wire:click="exportCsv" title="Export session as CSV (Excel)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/50 hover:text-emerald-400 hover:bg-white/10 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 10h18M3 14h18M10 3v18M14 3v18M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </button>
                    @endif
                    {{-- Close (always) --}}
                    <button type="button" x-on:click="show = false; $wire.closeModal()"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-sidebar-foreground/70 hover:text-white hover:bg-white/10 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            {{-- /HEADER --}}

            {{-- ═══════════════════════════════════════════ --}}
            {{-- PANEL 1 — CHAT                             --}}
            {{-- ═══════════════════════════════════════════ --}}
            @if ($panel === 'chat')

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

                                {{-- ── Room booking panel ── --}}
                                @if (isset($msg['booking_prefill']) && is_array($msg['booking_prefill']))
                                    @php
                                        $prefill = $msg['booking_prefill'];
                                        $isOnline = ($prefill['booking_type'] ?? '') === 'online_meeting';
                                        $hasRoomData = collect($prefill)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
                                        $roomPayload = [
                                            'roomId'          => $prefill['room_id']             ?? null,
                                            'ymd'             => $prefill['date']                ?? '',
                                            'time'            => $prefill['start_time']          ?? '',
                                            'endTime'         => $prefill['end_time']            ?? '',
                                            'title'           => $prefill['meeting_title']       ?? '',
                                            'attendees'       => $prefill['number_of_attendees'] ?? 1,
                                            'notes'           => $prefill['special_notes']       ?? '',
                                            'department'      => $prefill['department']          ?? null,
                                            'historicalUser'  => $prefill['historical_user']     ?? null,
                                            'bookingType'     => $prefill['booking_type']        ?? 'meeting',
                                            'onlineProvider'  => $prefill['online_provider']     ?? 'google_meet',
                                            'mode'            => 'rebook',
                                        ];
                                        $providerLabel = match($prefill['online_provider'] ?? '') {
                                            'zoom'         => 'Zoom',
                                            'google_meet'  => 'Google Meet',
                                            default        => null,
                                        };
                                        $roomRows = [
                                            'Meeting Title'   => $prefill['meeting_title']       ?? null,
                                            'Type'            => $isOnline
                                                                   ? ('Online' . ($providerLabel ? ' · ' . $providerLabel : ''))
                                                                   : 'In-Room',
                                            'Room'            => !$isOnline ? ($prefill['room_name'] ?? null) : null,
                                            'Provider'        => $isOnline ? $providerLabel : null,
                                            'Department'      => $prefill['department']           ?? null,
                                            'Historical User' => $prefill['historical_user']      ?? null,
                                            'Date'            => !empty($prefill['date'])
                                                                   ? \Carbon\Carbon::parse($prefill['date'])->format('d M Y') : null,
                                            'Participants'    => $prefill['number_of_attendees']  ?? null,
                                            'Start'           => !empty($prefill['start_time'])
                                                                   ? substr($prefill['start_time'], 0, 5) : null,
                                            'End'             => !empty($prefill['end_time'])
                                                                   ? substr($prefill['end_time'], 0, 5) : null,
                                            'Requirements'    => !$isOnline ? ($prefill['special_notes'] ?? null) : null,
                                        ];
                                        // Remove null-only rows that are type-specific
                                        $roomRows = array_filter($roomRows, fn($v) => $v !== null);
                                    @endphp
                                    <div class="border-t border-border/60 {{ $isOnline ? 'bg-blue-500/5' : 'bg-primary/5' }}">
                                        <div class="px-3.5 pt-2.5 pb-1.5 space-y-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider mb-1.5
                                                       {{ $isOnline ? 'text-blue-500/70' : 'text-primary/70' }}">
                                                {{ $isOnline ? '🎥 Online Meeting' : '🏢 Room Booking' }}
                                            </p>
                                            @foreach ($roomRows as $label => $value)
                                                <div class="flex items-baseline gap-1.5">
                                                    <span class="text-[10px] text-muted-foreground shrink-0 w-[88px]">{{ $label }}:</span>
                                                    <span class="text-[10px] text-foreground font-medium break-words">{{ $value }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="px-3.5 pb-2.5 pt-1">
                                            <button type="button" x-data
                                                    x-on:click="$wire.dispatch('open-quick-book', @js($roomPayload)); $wire.closeModal()"
                                                    class="w-full flex items-center justify-center gap-2 h-8 px-3 rounded-lg text-xs font-semibold active:scale-95 transition-all shadow-sm
                                                           {{ $isOnline
                                                               ? 'bg-blue-600 text-white hover:bg-blue-700'
                                                               : 'bg-primary text-primary-foreground hover:bg-primary/90' }}">
                                                @if ($isOnline)
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                              d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                @endif
                                                @if ($isOnline)
                                                    {{ $hasRoomData ? 'Open Pre-filled Online Meeting Form' : 'Open Online Meeting Form' }}
                                                @else
                                                    {{ $hasRoomData ? 'Open Pre-filled Room Form' : 'Open Room Booking Form' }}
                                                @endif
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- ── Vehicle booking panel ── --}}
                                @if (isset($msg['vehicle_prefill']) && is_array($msg['vehicle_prefill']))
                                    @php
                                        $vp = $msg['vehicle_prefill'];
                                        $hasVehicleData = collect($vp)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
                                        $vehiclePayload = [
                                            'vehicleId'     => $vp['vehicle_id']    ?? null,
                                            'borrowerName'  => $vp['borrower_name'] ?? '',
                                            'dateFrom'      => $vp['date_from']     ?? '',
                                            'dateTo'        => $vp['date_to']       ?? '',
                                            'startTime'     => $vp['start_time']    ?? '',
                                            'endTime'       => $vp['end_time']      ?? '',
                                            'purpose'       => $vp['purpose']       ?? '',
                                            'destination'   => $vp['destination']   ?? '',
                                            'purposeType'   => $vp['purpose_type']  ?? null,
                                            'department'    => $vp['department']    ?? null,
                                            'historicalUser'=> $vp['borrower_name'] ?? null,
                                            'mode'          => 'rebook',
                                        ];
                                        $vehicleRows = [
                                            'Vehicle'       => !empty($vp['vehicle_name'])
                                                                 ? $vp['vehicle_name'] . (!empty($vp['plate_number']) ? ' (' . $vp['plate_number'] . ')' : '')
                                                                 : null,
                                            'Borrower'      => $vp['borrower_name']  ?? null,
                                            'Department'    => $vp['department']     ?? null,
                                            'Date From'     => !empty($vp['date_from'])
                                                                 ? \Carbon\Carbon::parse($vp['date_from'])->format('d M Y') : null,
                                            'Date To'       => !empty($vp['date_to'])
                                                                 ? \Carbon\Carbon::parse($vp['date_to'])->format('d M Y') : null,
                                            'Start'         => !empty($vp['start_time']) ? substr($vp['start_time'], 0, 5) : null,
                                            'End'           => !empty($vp['end_time'])   ? substr($vp['end_time'],   0, 5) : null,
                                            'Purpose'       => $vp['purpose']       ?? null,
                                            'Destination'   => $vp['destination']   ?? null,
                                            'Purpose Type'  => $vp['purpose_type']  ?? null,
                                        ];
                                    @endphp
                                    <div class="border-t border-border/60 bg-amber-500/5">
                                        <div class="px-3.5 pt-2.5 pb-1.5 space-y-1">
                                            <p class="text-[10px] font-semibold text-amber-600/80 uppercase tracking-wider mb-1.5">
                                                🚗 Vehicle Booking
                                            </p>
                                            @foreach ($vehicleRows as $label => $value)
                                                <div class="flex items-baseline gap-1.5">
                                                    <span class="text-[10px] text-muted-foreground shrink-0 w-[88px]">{{ $label }}:</span>
                                                    @if ($value !== null && $value !== '')
                                                        <span class="text-[10px] text-foreground font-medium break-words">{{ $value }}</span>
                                                    @else
                                                        <span class="text-[10px] text-muted-foreground/40 italic">—</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="px-3.5 pb-2.5 pt-1">
                                            <button type="button" x-data
                                                    x-on:click="$wire.dispatch('open-quick-vehicle-book', @js($vehiclePayload)); $wire.closeModal()"
                                                    class="w-full flex items-center justify-center gap-2 h-8 px-3 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 active:scale-95 transition-all shadow-sm">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                          d="M8 17h8M3 11l2-5h14l2 5M5 11v6a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-6"/>
                                                </svg>
                                                {{ $hasVehicleData ? 'Open Pre-filled Vehicle Form' : 'Open Vehicle Booking Form' }}
                                            </button>
                                        </div>
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

            {{-- Input --}}
            <div class="px-4 py-3 border-t border-border bg-card shrink-0">
                <form wire:submit.prevent="sendMessage">
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="message" placeholder="Type a message…" autocomplete="off"
                               @disabled($isLoading)
                               class="flex-grow h-9 px-3.5 border border-input rounded-xl bg-background text-xs text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <button type="submit" @disabled($isLoading)
                                class="w-9 h-9 shrink-0 flex items-center justify-center bg-primary hover:bg-primary/90 text-primary-foreground rounded-xl transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
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

            {{-- ═══════════════════════════════════════════ --}}
            {{-- PANEL 2 — HISTORY (session list)           --}}
            {{-- ═══════════════════════════════════════════ --}}
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
                        <p class="text-[10px] text-muted-foreground/60">Start a chat and click the new-conversation button to archive it here.</p>
                    </div>
                @else
                    <div class="divide-y divide-border/50">
                        @foreach ($historySessions as $s)
                            <div class="flex items-start gap-3 px-4 py-3 hover:bg-muted/30 transition group">
                                {{-- Role badge --}}
                                <div class="mt-0.5 shrink-0 w-7 h-7 rounded-lg flex items-center justify-center
                                            {{ $s['role'] === 'manager' ? 'bg-violet-500/15 text-violet-400' : 'bg-emerald-500/15 text-emerald-400' }}">
                                    @if ($s['role'] === 'manager')
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    @else
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    @endif
                                </div>

                                {{-- Session info (click to view) --}}
                                <button type="button" wire:click="viewSession({{ $s['id'] }})"
                                        class="flex-1 min-w-0 text-left">
                                    <p class="text-xs font-medium text-foreground truncate">{{ $s['title'] }}</p>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">
                                        {{ $s['started_at'] }}
                                        <span class="mx-1">·</span>
                                        {{ $s['message_count'] }} msg{{ $s['message_count'] !== 1 ? 's' : '' }}
                                    </p>
                                </button>

                                {{-- Actions: restore + delete --}}
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition shrink-0 mt-0.5">
                                    <button type="button" wire:click="restoreSession({{ $s['id'] }})"
                                            title="Restore to active chat"
                                            class="w-6 h-6 flex items-center justify-center rounded text-muted-foreground hover:text-primary hover:bg-primary/10 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="deleteSession({{ $s['id'] }})"
                                            wire:confirm="Delete this conversation?"
                                            title="Delete session"
                                            class="w-6 h-6 flex items-center justify-center rounded text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition">
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

            {{-- ═══════════════════════════════════════════ --}}
            {{-- PANEL 3 — SESSION VIEWER (read-only replay) --}}
            {{-- ═══════════════════════════════════════════ --}}
            @if ($panel === 'session')
            <div class="flex-grow min-h-0 overflow-y-auto bg-muted/20 px-4 py-3 space-y-3">
                @forelse ($viewingMessages as $msg)
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
                            <div class="bg-card border border-border rounded-2xl rounded-tl-none max-w-[85%] shadow-sm overflow-hidden">
                                <div class="px-3.5 py-2.5">
                                    <p class="text-xs text-foreground leading-relaxed whitespace-pre-wrap break-words">{{ $msg['text'] }}</p>
                                    @if (!empty($msg['sent_at']))
                                        <p class="text-[9px] text-muted-foreground/50 mt-1">{{ $msg['sent_at'] }}</p>
                                    @endif
                                </div>

                                {{-- Booking prefill summary (read-only in history) --}}
                                @if (!empty($msg['booking_prefill']) && is_array($msg['booking_prefill']))
                                    @php
                                        $vp = $msg['booking_prefill'];
                                        $vpRows = [
                                            'Meeting Title'   => $vp['meeting_title']       ?? null,
                                            'Room'            => $vp['room_name']            ?? null,
                                            'Department'      => $vp['department']           ?? null,
                                            'Historical User' => $vp['historical_user']      ?? null,
                                            'Date'            => !empty($vp['date'])
                                                                   ? \Carbon\Carbon::parse($vp['date'])->format('d M Y') : null,
                                            'Participants'    => $vp['number_of_attendees']  ?? null,
                                            'Start'           => !empty($vp['start_time'])
                                                                   ? substr($vp['start_time'], 0, 5) : null,
                                            'End'             => !empty($vp['end_time'])
                                                                   ? substr($vp['end_time'], 0, 5) : null,
                                            'Requirements'    => $vp['special_notes']        ?? null,
                                        ];
                                        $vpHasData = collect($vpRows)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
                                    @endphp
                                    @if ($vpHasData)
                                        <div class="border-t border-border/60 px-3.5 py-2 bg-primary/5">
                                            <p class="text-[10px] font-semibold text-primary/60 uppercase tracking-wider mb-1">Booking Details</p>
                                            @foreach ($vpRows as $label => $value)
                                                @if ($value !== null && $value !== '')
                                                    <div class="flex items-baseline gap-1.5">
                                                        <span class="text-[10px] text-muted-foreground shrink-0 w-[88px]">{{ $label }}:</span>
                                                        <span class="text-[10px] text-foreground font-medium break-words">{{ $value }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <p class="text-xs text-muted-foreground text-center py-8">No messages in this session.</p>
                @endforelse
            </div>

            {{-- Restore footer --}}
            <div class="px-4 py-3 border-t border-border bg-card shrink-0">
                <button type="button" wire:click="restoreSession({{ $viewingSessionId }})"
                        class="w-full flex items-center justify-center gap-2 h-9 rounded-xl bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 active:scale-95 transition-all shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Restore this conversation
                </button>
            </div>
            @endif {{-- /panel session --}}

        </div>{{-- /drawer inner --}}
    </div>{{-- /drawer --}}
</div>{{-- /root --}}
