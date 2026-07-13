<div class="min-h-screen bg-background" x-data>
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnPrimary = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
        $btnOutline = 'inline-flex items-center justify-center gap-2 px-4 h-9 text-xs font-semibold rounded-lg border border-border bg-card text-foreground hover:bg-muted transition focus:outline-none';
    @endphp

    <div class="px-4 sm:px-6 py-6 space-y-5">

        {{-- Header --}}
        <x-page-header title="Priority Room Booking" subtitle="Submit a priority booking. Conflicts with existing offline bookings require receptionist approval." />

        {{-- Tabs --}}
        <div class="flex gap-1 bg-muted/40 border border-border rounded-xl p-1 w-fit">
            <button wire:click="setTab('form')"
                class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'form' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Booking
            </button>
            <button wire:click="setTab('status')"
                class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'status' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                My Bookings
            </button>
        </div>

        {{-- ── FORM TAB ── --}}
        @if($activeTab === 'form')
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-foreground">Priority Room Booking Form</p>
                    <p class="text-xs text-muted-foreground">This booking takes priority over existing offline room reservations</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Room --}}
                    <div>
                        <label class="{{ $label }}">Room <span class="text-destructive">*</span></label>
                        <select wire:model.live="room_id" wire:change="detectConflict" class="{{ $input }}">
                            <option value="">— Select Room —</option>
                            @foreach($rooms as $r)
                                <option value="{{ $r['id'] }}">{{ $r['name'] }}{{ $r['capacity'] ? ' (cap. '.$r['capacity'].')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('room_id') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Meeting Title --}}
                    <div>
                        <label class="{{ $label }}">Meeting Title <span class="text-destructive">*</span></label>
                        <input type="text" wire:model.defer="meeting_title" class="{{ $input }}" placeholder="e.g. Board Emergency Meeting">
                        @error('meeting_title') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Date --}}
                    <div>
                        <label class="{{ $label }}">Date <span class="text-destructive">*</span></label>
                        <input type="date" wire:model.live="date" wire:change="detectConflict" class="{{ $input }}">
                        @error('date') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Attendees --}}
                    <div>
                        <label class="{{ $label }}">Number of Attendees</label>
                        <input type="number" wire:model.defer="number_of_attendees" class="{{ $input }}" min="1" placeholder="1">
                    </div>

                    {{-- Start Time --}}
                    <div>
                        <label class="{{ $label }}">Start Time <span class="text-destructive">*</span></label>
                        <input type="time" wire:model.live="start_time" wire:change="detectConflict" class="{{ $input }}">
                        @error('start_time') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- End Time --}}
                    <div>
                        <label class="{{ $label }}">End Time <span class="text-destructive">*</span></label>
                        <input type="time" wire:model.live="end_time" wire:change="detectConflict" class="{{ $input }}">
                        @error('end_time') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Special Notes --}}
                <div>
                    <label class="{{ $label }}">Special Notes</label>
                    <textarea wire:model.defer="special_notes" rows="2" class="{{ $input }} h-auto py-2.5 resize-none" placeholder="Any requirements or notes..."></textarea>
                </div>

                {{-- Conflict warning banner --}}
                @if($conflictingBooking)
                <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-600">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div class="text-sm">
                        <p class="font-semibold">Conflict detected!</p>
                        <p class="text-xs mt-0.5 text-orange-600/80">
                            Booking <span class="font-mono">#{{ $conflictingBooking->bookingroom_id }}</span> —
                            "{{ $conflictingBooking->meeting_title }}"
                            (<span class="font-semibold">{{ strtoupper($conflictingBooking->status) }}</span>)
                            is occupying this room at this time. Submitting will request cancellation of this booking regardless of its current stage.
                        </p>
                    </div>
                </div>
                @endif

                <div class="flex justify-end pt-1">
                    <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled">
                        <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Submit Priority Booking
                    </button>
                </div>
            </form>
        </div>
        @endif

        {{-- ── STATUS TAB ── --}}
        @if($activeTab === 'status')
        <div class="space-y-4">
            {{-- Filter row --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $lbl)
                <button wire:click="$set('statusFilter','{{ $val }}')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $statusFilter === $val ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80' }}">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>

            @forelse($myBookings as $b)
            @php
                $color = match($b->status) {
                    'approved'   => ['bg'=>'bg-emerald-500/10','text'=>'text-emerald-600','border'=>'border-emerald-500/30'],
                    'pending_receipt', 'pending_cancellation' => ['bg'=>'bg-amber-500/10','text'=>'text-amber-600','border'=>'border-amber-500/30'],
                    default      => ['bg'=>'bg-red-500/10','text'=>'text-red-600','border'=>'border-red-500/30'],
                };
            @endphp
            <div class="{{ $card }} p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1 min-w-0">
                        <p class="text-sm font-semibold text-foreground truncate">{{ $b->meeting_title }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $b->room?->room_name ?? '—' }} &bull;
                            {{ \Carbon\Carbon::parse($b->date)->format('d M Y') }} &bull;
                            {{ $b->start_time }} – {{ $b->end_time }}
                        </p>
                        @if($b->cancels_booking_id)
                        <p class="text-xs text-orange-500">Cancellation requested for booking #{{ $b->cancels_booking_id }}</p>
                        @endif
                        @if($b->rejection_reason)
                        <p class="text-xs text-muted-foreground italic">Reason: {{ $b->rejection_reason }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold rounded-full {{ $color['bg'] }} {{ $color['text'] }} border {{ $color['border'] }}">
                            {{ $b->statusLabel() }}
                        </span>
                        @if($b->isActionable())
                        <button wire:click="openCancelModal({{ $b->id }})" class="{{ $btnOutline }} text-destructive border-destructive/30 hover:bg-destructive/5">
                            Cancel
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-14 text-muted-foreground">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-sm">No bookings found.</p>
            </div>
            @endforelse
            <div class="mt-2">{{ $myBookings->links() }}</div>
        </div>
        @endif

    </div>

    {{-- ── CONFLICT MODAL ── --}}
    @if($showConflictModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-500/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-foreground">Booking Conflict</p>
                    <p class="text-xs text-muted-foreground mt-0.5">An existing offline booking occupies this slot.</p>
                </div>
            </div>
            @if($conflictingBooking)
            <div class="bg-muted/40 rounded-xl p-3.5 text-xs space-y-1">
                <p><span class="font-semibold">Booking #{{ $conflictingBooking->bookingroom_id }}</span> — {{ $conflictingBooking->meeting_title }}</p>
                <p class="text-muted-foreground">{{ $conflictingBooking->room?->room_name }} · {{ \Carbon\Carbon::parse($conflictingBooking->date)->format('d M Y') }} · {{ $conflictingBooking->start_time }} – {{ $conflictingBooking->end_time }}</p>
            </div>
            @endif
            <p class="text-sm text-foreground">Do you want to <strong>request cancellation</strong> of the conflicting booking — even if it is currently ongoing — requiring receptionist approval, or go back?</p>
            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                <button wire:click="confirmWithCancellation" class="{{ $btnPrimary }} flex-1 bg-orange-500 hover:bg-orange-600 focus:ring-orange-500/20">
                    Request Cancellation
                </button>
                <button wire:click="closeConflictModal" class="{{ $btnOutline }} flex-1">
                    Go Back
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── CANCEL OWN BOOKING MODAL ── --}}
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
            <p class="font-semibold text-foreground">Cancel Priority Booking?</p>
            <p class="text-sm text-muted-foreground">This will cancel your priority booking request. This action cannot be undone.</p>
            <div class="flex gap-2 pt-1">
                <button wire:click="cancelBooking" class="{{ $btnPrimary }} flex-1 bg-destructive hover:bg-destructive/90 focus:ring-destructive/20">Confirm Cancel</button>
                <button wire:click="closeCancelModal" class="{{ $btnOutline }} flex-1">Keep</button>
            </div>
        </div>
    </div>
    @endif

</div>
