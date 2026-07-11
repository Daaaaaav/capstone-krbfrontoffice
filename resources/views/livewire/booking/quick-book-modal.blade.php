<div>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data="{ show: @entangle('show') }"
         x-show="show"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display:none">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="close"></div>

        {{-- Modal --}}
        <div class="relative z-10 w-full max-w-xl bg-card rounded-2xl border border-border shadow-2xl overflow-hidden"
             wire:keydown.escape="close">

                {{-- Header --}}
                <div class="px-6 py-5 border-b border-border flex items-center justify-between bg-muted/10">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            @if ($booking_type === 'online_meeting')
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                          d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <h3 class="text-base font-bold text-foreground tracking-tight">
                            {{ $booking_type === 'online_meeting' ? 'Quick Online Meeting' : __('app.quick_book_title') }}
                        </h3>
                    </div>
                    <button class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition"
                            wire:click="close">✕</button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-4 max-h-[72vh] overflow-y-auto">

                    {{-- ── AI context strip ── --}}
                    @if ($ai_department || $ai_historical_user)
                        <div class="flex flex-wrap gap-2 bg-primary/5 border border-primary/20 rounded-xl px-3 py-2">
                            @if ($ai_historical_user)
                                <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <svg class="w-3.5 h-3.5 text-primary/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="font-medium text-foreground">{{ $ai_historical_user }}</span>
                                </span>
                            @endif
                            @if ($ai_department)
                                <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <svg class="w-3.5 h-3.5 text-primary/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                    <span class="font-medium text-foreground">{{ $ai_department }}</span>
                                </span>
                            @endif
                            <span class="text-[10px] text-muted-foreground/60 italic ml-auto self-center">from AI context</span>
                        </div>
                    @endif

                    {{-- ── Booking type toggle ── --}}
                    <div class="flex rounded-xl border border-border overflow-hidden">
                        <button type="button"
                                wire:click="$set('booking_type','meeting')"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold transition
                                       {{ $booking_type === 'meeting'
                                           ? 'bg-primary text-primary-foreground'
                                           : 'bg-background text-muted-foreground hover:bg-muted' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            In-Room Meeting
                        </button>
                        <button type="button"
                                wire:click="$set('booking_type','online_meeting')"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold transition
                                       {{ $booking_type === 'online_meeting'
                                           ? 'bg-primary text-primary-foreground'
                                           : 'bg-background text-muted-foreground hover:bg-muted' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            </svg>
                            Online Meeting
                        </button>
                    </div>

                    {{-- ── Room (offline only) / Provider (online only) + Date ── --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @if ($booking_type === 'meeting')
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    {{ __('app.quick_book_room') }}
                                </label>
                                <select wire:model.live="room_id"
                                        class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    <option value="">— Select a room —</option>
                                    @foreach ($rooms as $r)
                                        <option value="{{ $r['id'] }}">{{ $r['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('room_id') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    Platform
                                </label>
                                <div class="flex rounded-lg border border-border overflow-hidden h-10">
                                    <button type="button"
                                            wire:click="$set('online_provider','google_meet')"
                                            class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold transition
                                                   {{ $online_provider === 'google_meet'
                                                       ? 'bg-primary text-primary-foreground'
                                                       : 'bg-background text-muted-foreground hover:bg-muted' }}">
                                        {{-- Google Meet icon --}}
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1 14.414L5.586 11 7 9.586l4 4 8-8L20.414 7 11 16.414z"/>
                                        </svg>
                                        Google Meet
                                    </button>
                                    <button type="button"
                                            wire:click="$set('online_provider','zoom')"
                                            class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold transition
                                                   {{ $online_provider === 'zoom'
                                                       ? 'bg-primary text-primary-foreground'
                                                       : 'bg-background text-muted-foreground hover:bg-muted' }}">
                                        {{-- Zoom icon --}}
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M4 6a2 2 0 00-2 2v8a2 2 0 002 2h10a2 2 0 002-2v-2.5l4 2V8.5l-4 2V8a2 2 0 00-2-2H4z"/>
                                        </svg>
                                        Zoom
                                    </button>
                                </div>
                                @error('online_provider') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.quick_book_date') }}
                            </label>
                            <input type="date" wire:model.live="date"
                                   class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('date') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- ── Start / End time ── --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.quick_book_start') }}
                            </label>
                            <input type="time" wire:model="start_time" min="{{ $minStart }}"
                                   class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('start_time') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.quick_book_end') }}
                            </label>
                            <input type="time" wire:model="end_time" min="{{ $start_time ?: $minStart }}"
                                   class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('end_time') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- ── Meeting title / Attendees ── --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.quick_book_meeting_title') }}
                            </label>
                            <input type="text" wire:model="meeting_title"
                                   placeholder="{{ __('app.quick_book_meeting_ph') }}"
                                   class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('meeting_title') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.quick_book_attendees') }}
                            </label>
                            <input type="number" wire:model="number_of_attendees" min="1" placeholder="0"
                                   class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('number_of_attendees') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- ── Room requirements (offline only) ── --}}
                    @if ($booking_type === 'meeting')
                        @php
                            $quickReqMap = [
                                'projector'        => __('app.req_projector_screen'),
                                'whiteboard'       => __('app.req_whiteboard'),
                                'video_conference' => __('app.req_video_conference'),
                                'catering'         => __('app.req_catering'),
                                'other'            => __('app.req_other'),
                            ];
                        @endphp
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                {{ __('app.quick_book_add_req') }}
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-muted/20 border border-border rounded-xl p-4">
                                @foreach (['projector', 'whiteboard', 'video_conference', 'catering', 'other'] as $req)
                                    <label class="flex items-center space-x-2.5 cursor-pointer group">
                                        <input type="checkbox" wire:model.live="requirements" value="{{ $req }}"
                                               class="w-4 h-4 rounded border-input text-primary focus:ring-primary/20 bg-background transition-all">
                                        <span class="text-xs text-foreground group-hover:text-primary transition-colors">{{ $quickReqMap[$req] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @if (in_array('other', $requirements ?? [], true))
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    {{ __('app.quick_book_special_notes') }}
                                </label>
                                <textarea wire:model.defer="special_notes" rows="3"
                                          placeholder="{{ __('app.quick_book_notes_ph') }}"
                                          class="w-full px-3.5 py-2.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"></textarea>
                                @error('special_notes') <span class="text-destructive text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    @endif

                    {{-- ── Online meeting info note ── --}}
                    @if ($booking_type === 'online_meeting')
                        <div class="flex items-start gap-2.5 bg-blue-500/8 border border-blue-500/20 rounded-xl px-3.5 py-3">
                            <svg class="w-4 h-4 text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-400 leading-relaxed">
                                A {{ $online_provider === 'zoom' ? 'Zoom' : 'Google Meet' }} link will be created automatically
                                when you confirm. The meeting URL will appear in the booking details after approval.
                            </p>
                        </div>
                    @endif

                </div>{{-- /body --}}

                {{-- Footer --}}
                <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/10">
                    <button wire:click="close"
                            class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition">
                        {{ __('app.quick_book_cancel') }}
                    </button>
                    <button wire:click="submit"
                            class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm">
                        {{ __('app.quick_book_confirm') }}
                    </button>
                </div>

            </div>{{-- /modal --}}
        </div>{{-- /overlay --}}
</div>
