<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnPrimary = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    <div class="px-4 sm:px-6 py-6 space-y-5">
        <x-page-header title="Schedule Future Visitor" subtitle="Pre-register an upcoming visitor. They appear in Receptionist Guestbook Status on their arrival date." />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- LEFT: Form --}}
            <div class="lg:col-span-2 {{ $card }}">
                <div class="px-6 py-4 border-b border-border bg-muted/10 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">Pre-register Visitor</p>
                        <p class="text-xs text-muted-foreground">QR codes emailed. Entry appears in Receptionist status at arrival time.</p>
                    </div>
                </div>

                <form wire:submit.prevent="save" class="p-6 space-y-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs mb-1">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/25 text-primary font-semibold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Scheduled Arrival
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-muted border border-border text-muted-foreground font-semibold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            By: {{ auth()->user()->full_name ?? auth()->user()->name ?? 'Manager' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="{{ $label }}">Arrival Date <span class="text-destructive">*</span></label>
                            <input type="date" wire:model.defer="scheduled_date" class="{{ $input }}">
                            @error('scheduled_date') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Arrival Time <span class="text-destructive">*</span></label>
                            <input type="time" wire:model.defer="scheduled_time" class="{{ $input }}">
                            @error('scheduled_time') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="{{ $label }}">Full Name <span class="text-destructive">*</span></label>
                            <input type="text" wire:model.defer="name" class="{{ $input }}" placeholder="Visitor full name">
                            @error('name') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Email <span class="text-destructive">*</span></label>
                            <input type="email" wire:model.defer="email" class="{{ $input }}" placeholder="visitor@email.com">
                            @error('email') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Visitor Count <span class="text-destructive">*</span></label>
                            <input type="number" wire:model.defer="visitor_count" min="1" max="999" class="{{ $input }}" placeholder="1">
                            @error('visitor_count') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Phone</label>
                            <input type="text" wire:model.defer="phone_number" class="{{ $input }}" placeholder="+62...">
                        </div>
                        <div>
                            <label class="{{ $label }}">Institution</label>
                            <input type="text" wire:model.defer="instansi" class="{{ $input }}" placeholder="Organization name">
                        </div>
                        <div>
                            <label class="{{ $label }}">Visit Purpose <span class="text-destructive">*</span></label>
                            <input type="text" wire:model.defer="keperluan" class="{{ $input }}" placeholder="Purpose of visit">
                            @error('keperluan') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Storage Slot (1-100)</label>
                            <input type="number" wire:model.defer="storage_place" min="1" max="100" class="{{ $input }}" placeholder="e.g. 12">
                        </div>
                        <div>
                            <label class="{{ $label }}">Target Department (optional)</label>
                            <select wire:model.live="department_id" class="{{ $input }}">
                                <option value="">— None —</option>
                                @foreach($departments_list as $dept)
                                    <option value="{{ $dept['id'] }}">{{ $dept['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Target Person (optional)</label>
                            <select wire:model.defer="user_id" class="{{ $input }}" @disabled(!$department_id)>
                                <option value="">— None —</option>
                                @foreach($users_list as $u)
                                    <option value="{{ $u['id'] }}">{{ $u['full_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled">
                            <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Schedule Visitor
                        </button>
                    </div>
                </form>
            </div>

            {{-- RIGHT: Status Sidebar --}}
            <aside class="lg:col-span-1 space-y-4">

                {{-- Active today --}}
                <div class="{{ $card }}" x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 border-b border-border bg-muted/30 hover:bg-muted/50 transition">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <p class="text-xs font-bold uppercase tracking-wider text-foreground">Active Today</p>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white">{{ $activeToday->count() }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="divide-y divide-border/50 max-h-56 overflow-y-auto">
                        @forelse($activeToday as $g)
                        <div class="flex items-start gap-3 px-4 py-2.5 hover:bg-muted/20 transition">
                            <div class="w-7 h-7 rounded-full bg-emerald-500/15 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-[10px] font-bold text-emerald-600">{{ strtoupper(substr($g->name, 0, 1)) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $g->name }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $g->keperluan }}</p>
                                <p class="text-[10px] text-muted-foreground/60">In: {{ $g->jam_in }} · {{ $g->visitor_count }} pax</p>
                            </div>
                        </div>
                        @empty
                        <p class="px-4 py-4 text-xs text-muted-foreground italic text-center">No active visitors today.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Upcoming scheduled --}}
                <div class="{{ $card }}" x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 border-b border-border bg-muted/30 hover:bg-muted/50 transition">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                            <p class="text-xs font-bold uppercase tracking-wider text-foreground">Upcoming</p>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary text-primary-foreground">{{ $sidebarUpcoming->count() }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="divide-y divide-border/50 max-h-72 overflow-y-auto">
                        @forelse($sidebarUpcoming as $g)
                        <div class="flex items-start gap-3 px-4 py-2.5 hover:bg-muted/20 transition">
                            <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-[10px] font-bold text-primary">{{ strtoupper(substr($g->name, 0, 1)) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $g->name }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $g->keperluan }}</p>
                                <p class="text-[10px] text-muted-foreground/60">
                                    {{ \Carbon\Carbon::parse($g->date)->format('d M') }} · {{ substr($g->jam_in,0,5) }} · {{ $g->visitor_count }} pax
                                </p>
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">
                                {{ \Carbon\Carbon::parse($g->date)->diffForHumans() }}
                            </span>
                        </div>
                        @empty
                        <p class="px-4 py-4 text-xs text-muted-foreground italic text-center">No upcoming visitors scheduled.</p>
                        @endforelse
                    </div>
                </div>

            </aside>
        </div>
    </div>
</div>
