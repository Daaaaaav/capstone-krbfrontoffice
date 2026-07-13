<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnPrimary = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    <div class="px-4 sm:px-6 py-6 space-y-5">

        <x-page-header title="Schedule Future Visitor" subtitle="Pre-register an upcoming visitor. They will appear in Receptionist Guestbook Status on their scheduled arrival date." />

        {{-- Tabs --}}
        <div class="flex gap-1 bg-muted/40 border border-border rounded-xl p-1 w-fit">
            <button wire:click="setTab('form')" class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'form' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Entry
            </button>
            <button wire:click="setTab('upcoming')" class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'upcoming' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Upcoming Visitors
            </button>
        </div>

        {{-- ── FORM TAB ── --}}
        @if($activeTab === 'form')
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-foreground">Pre-register Visitor</p>
                    <p class="text-xs text-muted-foreground">QR codes will be emailed to the visitor. Entry appears in Receptionist status at arrival time.</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-5">

                {{-- Scheduled arrival --}}
                <div class="flex flex-wrap items-center gap-2 text-xs mb-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/25 text-primary font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Scheduled Arrival
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-muted border border-border text-muted-foreground font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Registered by: {{ auth()->user()->full_name ?? auth()->user()->name ?? 'Manager' }}
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
                        <p class="mt-1 text-[10px] text-muted-foreground">QR code will be emailed here.</p>
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
                        <label class="{{ $label }}">Institution / Company</label>
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
                        <p class="mt-1 text-[10px] text-muted-foreground">ID card storage number (optional).</p>
                    </div>

                    {{-- Target Department --}}
                    <div wire:ignore>
                        <label class="{{ $label }}">Target Department (optional)</label>
                        <select wire:model.live="department_id" class="{{ $input }}">
                            <option value="">— None —</option>
                            @foreach($departments_list as $dept)
                                <option value="{{ $dept['id'] }}">{{ $dept['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target User --}}
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
        @endif

        {{-- ── UPCOMING TAB ── --}}
        @if($activeTab === 'upcoming')
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" wire:model.live.debounce.300ms="q" placeholder="Search visitors…" class="w-full h-9 pl-9 pr-4 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
            </div>

            @forelse($upcoming as $g)
            <div class="{{ $card }} p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">{{ $g->name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $g->email }} &bull; {{ $g->phone_number ?? '—' }} &bull; {{ $g->instansi ?? '—' }}
                        </p>
                        <p class="text-xs text-muted-foreground">Purpose: {{ $g->keperluan }}</p>
                    </div>
                    <div class="text-right shrink-0 space-y-1">
                        <p class="text-xs font-semibold text-primary">
                            {{ \Carbon\Carbon::parse($g->date)->format('d M Y') }} at {{ $g->jam_in }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ $g->visitor_count }} visitor{{ $g->visitor_count != 1 ? 's' : '' }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/30">
                            Scheduled
                        </span>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-14 text-muted-foreground">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm">No upcoming visitors scheduled.</p>
            </div>
            @endforelse
            <div class="mt-2">{{ $upcoming->links() }}</div>
        </div>
        @endif

    </div>
</div>
