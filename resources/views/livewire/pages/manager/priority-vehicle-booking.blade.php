<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnPrimary = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
        $btnOutline = 'inline-flex items-center justify-center gap-2 px-4 h-9 text-xs font-semibold rounded-lg border border-border bg-card text-foreground hover:bg-muted transition focus:outline-none';
    @endphp

    <div class="px-4 sm:px-6 py-6 space-y-5">

        <x-page-header title="Priority Vehicle Booking" subtitle="Submit a priority vehicle booking. Conflicts with pending (not yet approved) bookings can be cancelled with receptionist approval." />

        {{-- Tabs --}}
        <div class="flex gap-1 bg-muted/40 border border-border rounded-xl p-1 w-fit">
            <button wire:click="setTab('form')" class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'form' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Booking
            </button>
            <button wire:click="setTab('status')" class="px-5 py-2 text-xs font-semibold rounded-lg transition {{ $activeTab === 'status' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                My Bookings
            </button>
        </div>

        {{-- ── FORM TAB ── --}}
        @if($activeTab === 'form')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
            <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-foreground">Priority Vehicle Booking Form</p>
                    <p class="text-xs text-muted-foreground">Only conflicts with <strong>pending</strong> (not yet on-road) bookings can be cancelled</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    {{-- Vehicle --}}
                    <div>
                        <label class="{{ $label }}">Vehicle <span class="text-destructive">*</span></label>
                        <select wire:model.live="vehicle_id" wire:change="detectConflict" class="{{ $input }}">
                            <option value="">— Select Vehicle —</option>
                            @foreach($vehicles as $v)
                                <option value="{{ $v['id'] }}">{{ $v['label'] }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Department --}}
                    <div>
                        <label class="{{ $label }}">Department</label>
                        <select wire:model.live="department_id" class="{{ $input }}">
                            <option value="">— Optional —</option>
                            @foreach($departments as $d)
                                <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Borrower --}}
                    <div>
                        <label class="{{ $label }}">Borrower Name <span class="text-destructive">*</span></label>
                        @if(count($usersForCombobox))
                        <select wire:model.live="borrower_name" class="{{ $input }}">
                            <option value="">— Select User —</option>
                            @foreach($usersForCombobox as $u)
                                <option value="{{ $u['label'] }}">{{ $u['label'] }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="text" wire:model.defer="borrower_name" class="{{ $input }}" placeholder="Full name of borrower">
                        @endif
                        @error('borrower_name') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Date From --}}
                    <div>
                        <label class="{{ $label }}">Date From <span class="text-destructive">*</span></label>
                        <input type="date" wire:model.live="date_from" wire:change="detectConflict" class="{{ $input }}" min="{{ now()->toDateString() }}">
                        @error('date_from') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label class="{{ $label }}">Date To <span class="text-destructive">*</span></label>
                        <input type="date" wire:model.live="date_to" wire:change="detectConflict" class="{{ $input }}" min="{{ now()->toDateString() }}">
                        @error('date_to') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- Purpose Type --}}
                    <div>
                        <label class="{{ $label }}">Purpose Type <span class="text-destructive">*</span></label>
                        <select wire:model.defer="purpose_type" class="{{ $input }}">
                            <option value="dinas">Dinas</option>
                            <option value="operasional">Operasional</option>
                            <option value="antar_jemput">Antar / Jemput</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
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

                    {{-- Purpose --}}
                    <div>
                        <label class="{{ $label }}">Purpose <span class="text-destructive">*</span></label>
                        <input type="text" wire:model.defer="purpose" class="{{ $input }}" placeholder="Purpose of trip">
                        @error('purpose') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Destination --}}
                <div>
                    <label class="{{ $label }}">Destination</label>
                    <input type="text" wire:model.defer="destination" class="{{ $input }}" placeholder="Destination address">
                </div>

                {{-- Notes --}}
                <div>
                    <label class="{{ $label }}">Special Notes</label>
                    <textarea wire:model.defer="special_notes" rows="2" class="{{ $input }} h-auto py-2.5 resize-none" placeholder="Any notes..."></textarea>
                </div>

                {{-- Conflict warning --}}
                @if($conflictingVehicleBooking)
                <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-600">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div class="text-sm">
                        <p class="font-semibold">Conflict detected — pending booking exists!</p>
                        <p class="text-xs mt-0.5 text-orange-600/80">
                            Booking <span class="font-mono">#{{ $conflictingVehicleBooking->vehiclebooking_id }}</span>
                            by {{ $conflictingVehicleBooking->borrower_name }} —
                            {{ $conflictingVehicleBooking->start_at?->format('d M H:i') }} to {{ $conflictingVehicleBooking->end_at?->format('d M H:i') }}
                            is pending for this vehicle. You can request its cancellation.
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
        </div>{{-- end lg:col-span-2 --}}

        {{-- ── RIGHT SIDEBAR: Vehicle Booking Status ── --}}
        <aside class="lg:col-span-1">
            <div class="bg-card border border-border rounded-2xl overflow-hidden shadow-sm">
                <div class="px-4 py-3 border-b border-border bg-muted/30">
                    <p class="text-xs font-bold uppercase tracking-wider text-foreground">Vehicle Booking Status</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">Pending &amp; ongoing vehicle requests</p>
                </div>
                <div class="divide-y divide-border/50 max-h-[70vh] overflow-y-auto">
                    @forelse($sidebarVehicles as $b)
                    @php
                        $isPending = $b->status === 'pending';
                        $isOnRoad  = $b->status === 'on_progress';
                        $statusBg  = $isPending ? 'bg-amber-500/10 text-amber-600'
                            : ($isOnRoad ? 'bg-blue-500/10 text-blue-600' : 'bg-emerald-500/10 text-emerald-600');
                        $statusDot = $isPending ? 'bg-amber-400'
                            : ($isOnRoad ? 'bg-blue-500 animate-pulse' : 'bg-emerald-500 animate-pulse');
                        $label = $isPending ? 'Pending' : ($isOnRoad ? 'On Road' : 'Approved');
                    @endphp
                    <div class="flex items-start gap-3 px-4 py-3 hover:bg-muted/30 transition cursor-pointer"
                         wire:click="openVehicleSidebarDetail({{ $b->vehiclebooking_id }})">
                        <div class="w-8 h-8 rounded-lg {{ $statusBg }} flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $statusDot }}"></span>
                                <p class="text-xs font-semibold text-foreground truncate">{{ $b->vehicle?->name ?? '—' }}</p>
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-0.5 truncate">{{ $b->borrower_name }}</p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ $b->start_at?->format('d M H:i') }}–{{ $b->end_at?->format('H:i') }}
                            </p>
                        </div>
                        <span class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $statusBg }}">
                            {{ $label }}
                        </span>
                    </div>
                    @empty
                    <div class="px-4 py-8 text-center text-muted-foreground">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                        <p class="text-xs">No active vehicle bookings.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </aside>
        </div>{{-- end grid --}}
        @endif

        {{-- ── STATUS TAB ── --}}
        @if($activeTab === 'status')
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'on_road' => 'On the Road', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $val => $lbl)
                <button wire:click="$set('statusFilter','{{ $val }}')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition {{ $statusFilter === $val ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80' }}">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>

            @forelse($myBookings as $b)
            @php
                $color = match(true) {
                    $b->status === 'on_progress' => ['bg'=>'bg-blue-500/10','text'=>'text-blue-600','border'=>'border-blue-500/30'],
                    $b->status === 'approved'   => ['bg'=>'bg-emerald-500/10','text'=>'text-emerald-600','border'=>'border-emerald-500/30'],
                    $b->status === 'completed'  => ['bg'=>'bg-gray-500/10','text'=>'text-gray-600','border'=>'border-gray-500/30'],
                    in_array($b->status,['pending_receipt','pending_cancellation']) => ['bg'=>'bg-amber-500/10','text'=>'text-amber-600','border'=>'border-amber-500/30'],
                    default => ['bg'=>'bg-red-500/10','text'=>'text-red-600','border'=>'border-red-500/30'],
                };
            @endphp
            <div class="{{ $card }} p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1 min-w-0">
                        <p class="text-sm font-semibold text-foreground">{{ $b->vehicle?->name ?? '—' }}{{ $b->vehicle?->plate_number ? ' — '.$b->vehicle->plate_number : '' }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $b->borrower_name }} &bull;
                            {{ $b->start_at?->format('d M Y H:i') }} – {{ $b->end_at?->format('H:i') }} &bull;
                            {{ $b->purpose }}
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
                        <button wire:click="openCancelModal({{ $b->id }})" class="inline-flex items-center gap-1 px-3 h-8 text-xs font-semibold rounded-lg border border-destructive/30 text-destructive hover:bg-destructive/5 transition">
                            Cancel
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-14 text-muted-foreground">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                <p class="text-sm">No bookings found.</p>
            </div>
            @endforelse
            <div class="mt-2">{{ $myBookings->links() }}</div>
        </div>
        @endif

    </div>

    {{-- Conflict Modal --}}
    @if($showConflictModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-500/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-foreground">Pending Vehicle Booking Conflict</p>
                    <p class="text-xs text-muted-foreground mt-0.5">A pending booking exists for this vehicle.</p>
                </div>
            </div>
            @if($conflictingVehicleBooking)
            <div class="bg-muted/40 rounded-xl p-3.5 text-xs space-y-1">
                <p><span class="font-semibold">Booking #{{ $conflictingVehicleBooking->vehiclebooking_id }}</span> — {{ $conflictingVehicleBooking->borrower_name }}</p>
                <p class="text-muted-foreground">{{ $conflictingVehicleBooking->start_at?->format('d M Y H:i') }} – {{ $conflictingVehicleBooking->end_at?->format('d M Y H:i') }}</p>
            </div>
            @endif
            <p class="text-sm text-foreground">An existing regular vehicle booking conflicts with your Priority Booking. <strong>Cancel the conflicting booking immediately</strong> and continue with your Priority Booking?</p>
            <p class="text-xs text-muted-foreground mt-2">
                <svg class="w-3.5 h-3.5 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Note: The conflicting booking can only be cancelled if it starts at least 3 hours from now. No Receptionist approval required.
            </p>
            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                <button wire:click="confirmWithCancellation" class="{{ $btnPrimary }} flex-1 bg-orange-500 hover:bg-orange-600 focus:ring-orange-500/20">
                    Cancel & Continue
                </button>
                <button wire:click="closeConflictModal" class="{{ $btnOutline }} flex-1">Go Back</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Cancel Own Booking Modal --}}
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
            <p class="font-semibold text-foreground">Cancel Priority Booking?</p>
            <p class="text-sm text-muted-foreground">This will cancel your priority vehicle booking request.</p>
            <div class="flex gap-2 pt-1">
                <button wire:click="cancelBooking" class="{{ $btnPrimary }} flex-1 bg-destructive hover:bg-destructive/90">Confirm Cancel</button>
                <button wire:click="closeCancelModal" class="{{ $btnOutline }} flex-1">Keep</button>
            </div>
        </div>
    </div>
    @endif



{{-- ── SIDEBAR VEHICLE DETAIL MODAL ── --}}
@if($showVehicleSidebarDetail && $vehicleSidebarBooking)
<div class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-muted/20">
            <div>
                <p class="font-semibold text-foreground">{{ $vehicleSidebarBooking->vehicle?->name ?? 'Vehicle Booking' }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">Vehicle Booking Detail</p>
            </div>
            <button wire:click="closeVehicleSidebarDetail" class="text-muted-foreground hover:text-foreground">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-3 text-sm">
            @php
                $vbIsPending = $vehicleSidebarBooking->status === 'pending';
                $vbIsOnRoad  = $vehicleSidebarBooking->status === 'on_progress';
                $vbStatusLabel = $vbIsPending ? 'Pending' : ($vbIsOnRoad ? 'On Road' : 'Approved');
                $vbStatusClass = $vbIsPending ? 'bg-amber-100 text-amber-700' : ($vbIsOnRoad ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700');
            @endphp
            <div class="grid grid-cols-2 gap-3">
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Vehicle</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->vehicle?->name ?? '—' }}{{ $vehicleSidebarBooking->vehicle?->plate_number ? ' ('.$vehicleSidebarBooking->vehicle->plate_number.')' : '' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Status</p>
                    <span class="inline-flex mt-0.5 px-2 py-0.5 text-[11px] font-bold rounded-full {{ $vbStatusClass }}">{{ $vbStatusLabel }}</span>
                </div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Borrower</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->borrower_name ?? '—' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Department</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->department?->department_name ?? $vehicleSidebarBooking->user?->department?->department_name ?? '—' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Start</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->start_at?->format('d M Y H:i') ?? '—' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">End</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->end_at?->format('d M Y H:i') ?? '—' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Purpose</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->purpose ?? '—' }}</p></div>
                <div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Destination</p><p class="text-foreground font-medium mt-0.5">{{ $vehicleSidebarBooking->destination ?? '—' }}</p></div>
            </div>
            @if($vehicleSidebarBooking->notes)<div><p class="text-[10px] text-muted-foreground uppercase tracking-wider font-semibold">Notes</p><p class="text-foreground mt-0.5 text-xs">{{ $vehicleSidebarBooking->notes }}</p></div>@endif
            @if($showVehicleSidebarReject)
            <div class="pt-2 border-t border-border">
                <label class="block text-xs font-semibold text-destructive mb-1.5">Rejection Reason *</label>
                <textarea wire:model.defer="vehicleSidebarRejectReason" rows="2" class="w-full px-3 py-2 text-sm rounded-lg border border-input bg-background focus:outline-none focus:ring-2 focus:ring-destructive/20 resize-none" placeholder="Reason for rejection..."></textarea>
                @error('vehicleSidebarRejectReason') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
            </div>
            @endif
        </div>
        <div class="flex items-center justify-between px-6 py-4 border-t border-border bg-muted/10">
            <button wire:click="closeVehicleSidebarDetail" class="inline-flex items-center px-4 h-9 text-xs font-semibold rounded-lg border border-border bg-card text-foreground hover:bg-muted transition">Close</button>
            <div class="flex gap-2">
                @if($vbIsPending)
                    @if($this->canRejectVehicleSidebarBooking())
                        @if(!$showVehicleSidebarReject)
                        <button wire:click="openVehicleSidebarReject" class="inline-flex items-center gap-1.5 px-4 h-9 text-xs font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive/20 border border-destructive/30 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Reject
                        </button>
                        @else
                        <button wire:click="$set('showVehicleSidebarReject', false)" class="inline-flex items-center px-4 h-9 text-xs font-semibold rounded-lg border border-border text-muted-foreground hover:bg-muted transition">Cancel</button>
                        <button wire:click="submitVehicleSidebarReject" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-4 h-9 text-xs font-semibold rounded-lg bg-destructive text-white hover:bg-destructive/90 transition disabled:opacity-60">
                            <svg wire:loading wire:target="submitVehicleSidebarReject" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Confirm Reject
                        </button>
                        @endif
                    @else
                        <div class="flex items-center gap-2 px-3 py-1.5 text-xs text-amber-600 bg-amber-500/10 rounded-lg border border-amber-500/30">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="font-medium">Cannot reject within 3 hours of start time</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endif
</div>
