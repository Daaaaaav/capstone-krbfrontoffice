<div class="relative" x-data="{ open: @entangle('open').live }" @click.outside="open = false">

    {{-- Bell button --}}
    <button @click="$wire.toggle()"
        class="relative flex items-center justify-center w-9 h-9 rounded-xl border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition focus:outline-none focus:ring-2 focus:ring-primary/20"
        title="Notifications">
        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full bg-destructive text-destructive-foreground text-[10px] font-bold leading-none shadow">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-card border border-border rounded-2xl shadow-xl z-[200] overflow-hidden"
         style="display:none;">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-border flex items-center justify-between bg-muted/30">
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-foreground">Notifications</p>
                @if($unreadCount > 0)
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-destructive text-destructive-foreground">{{ $unreadCount }}</span>
                @endif
            </div>
            @if($unreadCount > 0)
            <button wire:click="markAllRead" class="text-xs text-primary hover:underline font-medium">Mark all read</button>
            @endif
        </div>

        {{-- Notification list --}}
        <div class="max-h-[28rem] overflow-y-auto divide-y divide-border/60">
            @forelse($notifs as $n)
            <div class="group px-4 py-3.5 transition cursor-pointer {{ !$n->is_read ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-muted/30' }}"
                 wire:key="notif-{{ $n->id }}"
                 wire:click="openDetail({{ $n->id }})">
                <div class="flex items-start gap-3">
                    {{-- Type icon --}}
                    @php
                        $isRoom    = str_contains($n->type, 'room');
                        $isVisitor = $n->type === \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR;
                        $isVehicle = !$isRoom && !$isVisitor;
                        $iconBg    = $isRoom ? 'bg-amber-500/10 text-amber-500'
                                  : ($isVisitor ? 'bg-emerald-500/10 text-emerald-600'
                                  : 'bg-blue-500/10 text-blue-500');
                    @endphp
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 {{ $iconBg }}">
                        @if($isRoom)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/></svg>
                        @elseif($isVisitor)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold text-foreground leading-snug">{{ $n->title }}</p>
                            @if(!$n->is_read)
                            <span class="w-2 h-2 rounded-full bg-primary shrink-0 mt-1 flex-none"></span>
                            @endif
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5 leading-relaxed line-clamp-2">{{ $n->message }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-[10px] text-muted-foreground/60">{{ $n->created_at->diffForHumans() }}</p>
                            @if($n->isPendingAction())
                            <span class="text-[10px] font-semibold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded-full">Action required</span>
                            @elseif($n->action_taken)
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full
                                {{ $n->action_taken === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-500' }}">
                                {{ ucfirst($n->action_taken) }}
                            </span>
                            @else
                            {{-- Direct/informational notification — no action needed, still clickable for details --}}
                            <span class="text-[10px] font-medium text-muted-foreground/70 bg-muted px-1.5 py-0.5 rounded-full">
                                View details
                            </span>
                            @endif
                        </div>
                    </div>
                    {{-- Chevron hint --}}
                    <svg class="w-3.5 h-3.5 text-muted-foreground/40 group-hover:text-muted-foreground shrink-0 mt-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </div>
            @empty
            <div class="px-4 py-10 text-center text-muted-foreground">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-xs">No notifications yet.</p>
            </div>
            @endforelse
        </div>
    </div>


    {{-- ── Detail modal ──────────────────────────────────────────────────── --}}
    @if($detailNotif)
    <div class="fixed inset-0 z-[500] flex items-center justify-center p-4"
         wire:key="notif-detail-modal">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="closeDetail"></div>

        {{-- Panel --}}
        <div class="relative w-full max-w-lg bg-card border border-border rounded-2xl shadow-2xl overflow-hidden">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-muted/30">
                <div class="flex items-center gap-3">
                    @php
                        $dIsRoom    = str_contains($detailNotif->type, 'room');
                        $dIsVisitor = $detailNotif->type === \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR;
                        $dIconBg    = $dIsRoom ? 'bg-amber-500/10 text-amber-500'
                                   : ($dIsVisitor ? 'bg-emerald-500/10 text-emerald-600'
                                   : 'bg-blue-500/10 text-blue-500');
                    @endphp
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center {{ $dIconBg }}">
                        @if($dIsRoom)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/></svg>
                        @elseif($dIsVisitor)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                        @endif
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-semibold text-foreground">{{ $detailNotif->title }}</p>
                            {{-- Show booking status badge from the linked priority booking model --}}
                            @if($detailBooking && !$detailNotif->isPendingAction() && !$detailNotif->action_taken && $detailNotif->type !== \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR)
                            @php
                                $bkStatus = method_exists($detailBooking, 'statusLabel') ? ($detailBooking->statusLabel() ?? ucfirst($detailBooking->status ?? '')) : ucfirst($detailBooking->status ?? '');
                                $bkColor  = match($detailBooking->status ?? '') {
                                    'approved'   => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                                    'completed'  => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
                                    'rejected', 'cancelled_conflict_denied' => 'bg-red-500/10 text-red-500 border-red-500/20',
                                    default      => 'bg-muted text-muted-foreground border-border',
                                };
                            @endphp
                            <span class="inline-flex items-center text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $bkColor }}">
                                {{ $bkStatus }}
                            </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-muted-foreground">{{ $detailNotif->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                <button wire:click="closeDetail"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>


            {{-- Modal body --}}
            <div class="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">

                {{-- Notification message --}}
                <p class="text-sm text-muted-foreground leading-relaxed">{{ $detailNotif->message }}</p>

                @if($detailBooking)
                <div class="rounded-xl border border-border bg-muted/20 divide-y divide-border/60">

                    @if($detailNotif->type === \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR)
                    {{-- ── Scheduled Visitor details ── --}}
                    <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Visitor Name</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Organisation</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->instansi ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Scheduled Date</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->date ? \Carbon\Carbon::parse($detailBooking->date)->format('d M Y') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Arrival Time</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->jam_in ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Purpose</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->keperluan ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Visitors</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->visitor_count ?? 1 }}</p>
                        </div>
                        @if($detailBooking->phone_number)
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Phone</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->phone_number }}</p>
                        </div>
                        @endif
                        @if($detailBooking->department)
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Visiting</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->department->department_name ?? '—' }}</p>
                        </div>
                        @endif
                    </div>
                    {{-- Navigate to Guestbook Status --}}
                    <div class="px-4 py-3 bg-emerald-500/5">
                        <a href="{{ route('receptionist.guestbookstatus') }}"
                           class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            View in Guestbook Status
                        </a>
                    </div>

                    @elseif(str_contains($detailNotif->type, 'room'))
                    {{-- ── Room booking details ── --}}
                    <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Room</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->room?->room_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Meeting Title</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->meeting_title ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Date</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->date ? \Carbon\Carbon::parse($detailBooking->date)->format('d M Y') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Time</p>
                            <p class="font-semibold text-foreground">
                                {{ $detailBooking->start_time ? \Carbon\Carbon::parse($detailBooking->start_time)->format('H:i') : '—' }}
                                –
                                {{ $detailBooking->end_time ? \Carbon\Carbon::parse($detailBooking->end_time)->format('H:i') : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Attendees</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->number_of_attendees ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Requested by</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->manager?->full_name ?? '—' }}</p>
                        </div>
                        @if($detailBooking->special_notes)
                        <div class="col-span-2">
                            <p class="text-muted-foreground font-medium mb-0.5">Special Notes</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->special_notes }}</p>
                        </div>
                        @endif
                    </div>

                    @if($detailBooking->cancels_booking_id && $detailBooking->cancelledBooking)
                    <div class="px-4 py-3 bg-amber-500/5">
                        <p class="text-[11px] font-semibold text-amber-700 mb-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            Conflicting Booking to Cancel
                        </p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <div>
                                <p class="text-muted-foreground font-medium mb-0.5">Booking #</p>
                                <p class="font-semibold text-foreground">#{{ $detailBooking->cancels_booking_id }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground font-medium mb-0.5">Title</p>
                                <p class="font-semibold text-foreground">{{ $detailBooking->cancelledBooking->meeting_title ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @else
                    {{-- ── Vehicle booking details ── --}}
                    <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Vehicle</p>
                            <p class="font-semibold text-foreground">
                                {{ $detailBooking->vehicle?->name ?? '—' }}
                                @if($detailBooking->vehicle?->plate_number)
                                <span class="text-muted-foreground font-normal">— {{ $detailBooking->vehicle->plate_number }}</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Borrower</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->borrower_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Start</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->start_at ? \Carbon\Carbon::parse($detailBooking->start_at)->format('d M Y H:i') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">End</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->end_at ? \Carbon\Carbon::parse($detailBooking->end_at)->format('d M Y H:i') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Purpose</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->purpose ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Destination</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->destination ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Requested by</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->manager?->full_name ?? '—' }}</p>
                        </div>
                        @if($detailBooking->department)
                        <div>
                            <p class="text-muted-foreground font-medium mb-0.5">Department</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->department->department_name ?? '—' }}</p>
                        </div>
                        @endif
                        @if($detailBooking->special_notes)
                        <div class="col-span-2">
                            <p class="text-muted-foreground font-medium mb-0.5">Special Notes</p>
                            <p class="font-semibold text-foreground">{{ $detailBooking->special_notes }}</p>
                        </div>
                        @endif
                    </div>

                    @if($detailBooking->cancels_booking_id && $detailBooking->cancelledBooking)
                    <div class="px-4 py-3 bg-amber-500/5">
                        <p class="text-[11px] font-semibold text-amber-700 mb-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            Conflicting Booking to Cancel
                        </p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <div>
                                <p class="text-muted-foreground font-medium mb-0.5">Booking #</p>
                                <p class="font-semibold text-foreground">#{{ $detailBooking->cancels_booking_id }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground font-medium mb-0.5">Borrower</p>
                                <p class="font-semibold text-foreground">{{ $detailBooking->cancelledBooking->borrower_name ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endif

                </div>
                @endif

                {{-- Already actioned: result banner --}}
                @if($detailNotif->action_taken)
                <div class="flex items-center gap-2.5 px-4 py-3 rounded-xl
                    {{ $detailNotif->action_taken === 'approved' ? 'bg-emerald-500/10 border border-emerald-500/20' : 'bg-red-500/10 border border-red-500/20' }}">
                    @if($detailNotif->action_taken === 'approved')
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-xs font-semibold text-emerald-700">This booking was approved and any conflicting booking was cancelled.</p>
                    @else
                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <p class="text-xs font-semibold text-red-600">This priority booking request was denied. The original booking is kept.</p>
                    @endif
                </div>
                @elseif($detailBooking && !$detailNotif->isPendingAction() && $detailNotif->type !== \App\Models\ManagerNotification::TYPE_SCHEDULED_VISITOR)
                {{-- Direct/informational notification: show live booking status from the model --}}
                @php
                    $liveStatus   = method_exists($detailBooking, 'statusLabel') ? $detailBooking->statusLabel() : ucfirst($detailBooking->status ?? '—');
                    $liveBg = match($detailBooking->status ?? '') {
                        'approved'   => 'bg-emerald-500/10 border-emerald-500/20',
                        'completed'  => 'bg-blue-500/10 border-blue-500/20',
                        'rejected', 'cancelled_conflict_denied' => 'bg-red-500/10 border-red-500/20',
                        default      => 'bg-muted/50 border-border',
                    };
                    $liveIcon = match($detailBooking->status ?? '') {
                        'approved', 'completed' => 'check',
                        'rejected', 'cancelled_conflict_denied' => 'x',
                        default => 'info',
                    };
                    $liveText = match($detailBooking->status ?? '') {
                        'approved'  => 'text-emerald-700',
                        'completed' => 'text-blue-700',
                        'rejected', 'cancelled_conflict_denied' => 'text-red-600',
                        default     => 'text-muted-foreground',
                    };
                @endphp
                <div class="flex items-center gap-2.5 px-4 py-3 rounded-xl border {{ $liveBg }}">
                    @if($liveIcon === 'check')
                    <svg class="w-4 h-4 {{ $liveText }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($liveIcon === 'x')
                    <svg class="w-4 h-4 {{ $liveText }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @else
                    <svg class="w-4 h-4 {{ $liveText }} shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                    <p class="text-xs font-semibold {{ $liveText }}">Booking status: {{ $liveStatus }}</p>
                </div>
                @endif

                {{-- Deny reason form --}}
                @if($detailNotif->isPendingAction() && $showDenyForm)
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-foreground">Reason for denial <span class="text-destructive">*</span></label>
                    <textarea wire:model="denyReason" rows="3" placeholder="Explain why this priority request is being denied…"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 resize-none"></textarea>
                    @error('denyReason')<p class="text-[11px] text-destructive">{{ $message }}</p>@enderror
                </div>
                @endif

            </div>


            {{-- Modal footer / action buttons --}}
            <div class="px-6 py-4 border-t border-border bg-muted/20 flex items-center justify-end gap-2">
                @if($detailNotif->isPendingAction())
                    @if(!$showDenyForm)
                    {{-- Initial action buttons --}}
                    <button wire:click="closeDetail"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border text-muted-foreground hover:bg-muted transition">
                        Close
                    </button>
                    <button wire:click="openDenyForm"
                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive/20 transition">
                        <svg class="w-3.5 h-3.5 inline -mt-px mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Deny
                    </button>
                    <button wire:click="approveFromDetail"
                        wire:loading.attr="disabled"
                        wire:target="approveFromDetail"
                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition disabled:opacity-60 shadow-sm">
                        <svg wire:loading wire:target="approveFromDetail" class="animate-spin w-3.5 h-3.5 inline -mt-px mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <svg wire:loading.remove wire:target="approveFromDetail" class="w-3.5 h-3.5 inline -mt-px mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Approve &amp; Cancel Conflict
                    </button>
                    @else
                    {{-- Deny form confirmation --}}
                    <button wire:click="$set('showDenyForm', false)"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border text-muted-foreground hover:bg-muted transition">
                        Back
                    </button>
                    <button wire:click="submitDeny"
                        wire:loading.attr="disabled"
                        wire:target="submitDeny"
                        class="px-4 py-2 text-xs font-semibold rounded-lg bg-destructive text-destructive-foreground hover:bg-destructive/90 active:scale-95 transition disabled:opacity-60">
                        <svg wire:loading wire:target="submitDeny" class="animate-spin w-3.5 h-3.5 inline -mt-px mr-1" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Confirm Denial
                    </button>
                    @endif
                @else
                <button wire:click="closeDetail"
                    class="px-4 py-2 text-xs font-semibold rounded-lg border border-border text-muted-foreground hover:bg-muted transition">
                    Close
                </button>
                @endif
            </div>

        </div>{{-- /panel --}}
    </div>{{-- /modal --}}
    @endif

</div>{{-- /root --}}
