<div>
    @if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" wire:click="close"></div>

        {{-- Modal --}}
        <div class="relative z-10 w-full max-w-xl bg-card rounded-2xl border border-border shadow-2xl overflow-hidden"
             wire:keydown.escape="close">

            {{-- Header --}}
            <div class="px-6 py-5 border-b border-border flex items-center justify-between bg-muted/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                  d="M8 17h8M3 11l2-5h14l2 5M5 11v6a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-6"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-foreground tracking-tight">
                        {{ $mode === 'rebook' ? 'Rebook Vehicle Trip' : 'Quick Vehicle Booking' }}
                    </h3>
                </div>
                <button wire:click="close"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition">✕</button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">

                {{-- AI context strip --}}
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

                {{-- Vehicle + Borrower --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Vehicle</label>
                        <select wire:model.live="vehicle_id"
                                class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <option value="">— Select a vehicle —</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v['id'] }}" @selected($vehicle_id == $v['id'])>{{ $v['label'] }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Borrower Name</label>
                        <input type="text" wire:model.live="borrower_name"
                               value="{{ $borrower_name }}"
                               placeholder="Full name of borrower"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('borrower_name') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Date From</label>
                        <input type="date" wire:model.live="date_from"
                               value="{{ $date_from }}"
                               min="{{ $minDate }}"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('date_from') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Date To</label>
                        <input type="date" wire:model.live="date_to"
                               value="{{ $date_to }}"
                               min="{{ $date_from ?: $minDate }}"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('date_to') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Times --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Start Time</label>
                        <input type="time" wire:model.live="start_time"
                               value="{{ $start_time }}"
                               min="{{ $minStart }}"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('start_time') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">End Time</label>
                        <input type="time" wire:model.live="end_time"
                               value="{{ $end_time }}"
                               min="{{ $start_time ?: $minStart }}"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('end_time') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Purpose type --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Purpose Type</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach (['dinas' => 'Dinas', 'operasional' => 'Operasional', 'antar_jemput' => 'Antar/Jemput', 'lainnya' => 'Lainnya'] as $val => $label)
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" wire:model.live="purpose_type"
                                   value="{{ $val }}"
                                   @checked($purpose_type === $val)
                                   class="text-primary focus:ring-primary/20 bg-background">
                            <span class="text-xs text-foreground group-hover:text-primary transition">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('purpose_type') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                </div>

                {{-- Purpose + Destination --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Purpose / Description</label>
                        <input type="text" wire:model.live="purpose"
                               value="{{ $purpose }}"
                               placeholder="Brief purpose of the trip"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('purpose') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Destination</label>
                        <input type="text" wire:model.live="destination"
                               value="{{ $destination }}"
                               placeholder="Destination (optional)"
                               class="w-full h-10 px-3.5 border border-input rounded-lg bg-background text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        @error('destination') <span class="text-destructive text-xs mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

            </div>{{-- /body --}}

            {{-- Footer --}}
            <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/10">
                <button wire:click="close"
                        class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition">
                    Cancel
                </button>
                <button wire:click="submit"
                        class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm">
                    Confirm Booking
                </button>
            </div>

        </div>{{-- /modal --}}
    </div>{{-- /overlay --}}
    @endif
</div>
