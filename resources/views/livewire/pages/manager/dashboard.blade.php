<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Page header --}}
        <x-page-header title="{{ __('app.manager_analytics') }}" subtitle="{{ __('app.interactive_insights') }} {{ $selectedYear }}">
            <x-slot:actions>
                <button wire:click="setFilter('all')"
                    class="px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md border border-border hover:bg-accent transition-colors">
                    {{ __('app.reset_view') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Year Selector --}}
        <div class="bg-card border border-border rounded-lg p-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">{{ __('app.select_year') }}</p>
                    <p class="text-xs text-muted-foreground/70">{{ __('app.viewing_data_for') }} {{ $selectedYear }}</p>
                </div>
                @if(empty($availableYears))
                    <span class="text-sm text-muted-foreground">{{ __('app.no_data_available') }}</span>
                @else
                    @php
                        $yearOptions = array_map(
                            fn($y) => ['value' => (string) $y, 'label' => (string) $y],
                            array_reverse($availableYears)
                        );
                    @endphp
                    <x-custom-select wire:model.live="selectedYear" :options="$yearOptions" />
                @endif
            </div>
        </div>

        {{-- KPI Cards --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($stats as $s)
                @php $isActive = $activeFilter === $s['key']; $isUp = $s['direction'] === 'up'; @endphp
                <div wire:click="setFilter('{{ $s['key'] }}')"
                    class="cursor-pointer bg-card border rounded-lg p-5 transition-all duration-150 hover:bg-accent/50
                           {{ $isActive ? 'border-foreground ring-1 ring-foreground' : 'border-border' }}">
                    <div class="flex justify-between items-start">
                        <p class="text-sm font-medium text-muted-foreground">{{ $s['label'] }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium
                            {{ $isUp ? 'text-success bg-success/10' : 'text-destructive bg-destructive/10' }}">
                            {{ $isUp ? '+' : '' }}{{ $s['trend'] }}%
                        </span>
                    </div>
                    <h2 class="text-2xl font-semibold mt-3 text-card-foreground tracking-tight">{{ number_format($s['value']) }}</h2>
                    <p class="mt-2 text-xs text-muted-foreground/60">{{ __('app.click_filter_chart') }}</p>
                </div>
            @endforeach
        </section>

        {{-- Priority Bookings Alert (only when present) --}}
        @if($pendingPriorityRoom->isNotEmpty() || $pendingPriorityVehicle->isNotEmpty())
        <div class="bg-card border border-amber-300 rounded-lg overflow-hidden"
             x-data="{ roomOpen: false, vehOpen: false }">
            <div class="px-4 py-3 border-b border-amber-200 bg-amber-50/80 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Priority Bookings Pending</p>
                    <p class="text-[11px] text-amber-600/80">Awaiting receptionist action</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-border/60">
                @if($pendingPriorityRoom->isNotEmpty())
                <div>
                    <button @click="roomOpen = !roomOpen"
                        class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-amber-700 bg-amber-50/40 hover:bg-amber-50 transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                            Room Priority
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white">{{ $pendingPriorityRoom->count() }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="roomOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="roomOpen" x-collapse class="divide-y divide-border/40">
                        @foreach($pendingPriorityRoom as $pb)
                        <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $pb->meeting_title }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">
                                    {{ $pb->room?->room_name ?? '—' }} &bull; {{ \Carbon\Carbon::parse($pb->date)->format('d M') }} {{ $pb->start_time }}–{{ $pb->end_time }}
                                </p>
                                @if($pb->status === 'pending_cancellation')
                                <span class="text-[10px] text-orange-600 font-medium">Cancel request pending</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @if($pendingPriorityVehicle->isNotEmpty())
                <div>
                    <button @click="vehOpen = !vehOpen"
                        class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-blue-700 bg-blue-50/40 hover:bg-blue-50 transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                            </svg>
                            Vehicle Priority
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">{{ $pendingPriorityVehicle->count() }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="vehOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="vehOpen" x-collapse class="divide-y divide-border/40">
                        @foreach($pendingPriorityVehicle as $pb)
                        <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $pb->borrower_name }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">
                                    {{ $pb->vehicle?->name ?? '—' }} &bull; {{ $pb->start_at?->format('d M H:i') }}
                                </p>
                                @if($pb->status === 'pending_cancellation')
                                <span class="text-[10px] text-orange-600 font-medium">Cancel request pending</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Status Summary Cards Row --}}
        <div
            x-data="{
                modal: null,
                page: 1,
                perPage: 6,
                get items() {
                    if (!this.modal) return [];
                    const all = {
                        room:    @js($pendingRoomBookings->concat($ongoingRoomBookings)->values()),
                        vehicle: @js($pendingVehicleBookings->concat($ongoingVehicleBookings)->values()),
                        visitor: @js($todayVisitors->values()),
                        docpack: @js($pendingDocpacks->values()),
                    };
                    return all[this.modal] ?? [];
                },
                get totalPages() { return Math.max(1, Math.ceil(this.items.length / this.perPage)); },
                get paged() { return this.items.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
                open(type) { this.modal = type; this.page = 1; document.body.classList.add('overflow-hidden'); },
                close() { this.modal = null; document.body.classList.remove('overflow-hidden'); },
            }"
            @keydown.escape.window="close()"
            class="grid grid-cols-2 lg:grid-cols-4 gap-4"
        >
            {{-- Card: Room Bookings --}}
            @php $totalRoom = $pendingRoomBookings->count() + $ongoingRoomBookings->count(); @endphp
            <button @click="open('room')"
                class="group text-left bg-card border border-[#4E653D]/40 rounded-xl p-5 hover:border-[#4E653D] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4E653D]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4E653D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4E653D]">{{ $totalRoom }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a4d2e]">Room Bookings</p>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-[11px] text-amber-600 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>{{ $pendingRoomBookings->count() }} pending
                    </span>
                    <span class="text-[11px] text-emerald-600 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>{{ $ongoingRoomBookings->count() }} ongoing
                    </span>
                </div>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4E653D] transition-colors">Click to view details &rarr;</p>
            </button>

            {{-- Card: Vehicle Bookings --}}
            @php $totalVehicle = $pendingVehicleBookings->count() + $ongoingVehicleBookings->count(); @endphp
            <button @click="open('vehicle')"
                class="group text-left bg-card border border-[#4A2F24]/40 rounded-xl p-5 hover:border-[#4A2F24] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4A2F24]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4A2F24]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4A2F24]">{{ $totalVehicle }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a241c]">Vehicle Bookings</p>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-[11px] text-amber-600 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>{{ $pendingVehicleBookings->count() }} pending
                    </span>
                    <span class="text-[11px] text-blue-600 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 inline-block"></span>{{ $ongoingVehicleBookings->count() }} on road
                    </span>
                </div>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4A2F24] transition-colors">Click to view details &rarr;</p>
            </button>

            {{-- Card: Visitors Today --}}
            <button @click="open('visitor')"
                class="group text-left bg-card border border-violet-400/40 rounded-xl p-5 hover:border-violet-500 hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-violet-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4" stroke-width="2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M23 21v-2a4 4 0 00-3-3.87"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-violet-700">{{ $todayVisitors->count() }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-violet-800">Visitors Today</p>
                <p class="text-[11px] text-violet-600/70 mt-2">Guestbook entries today</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-violet-600 transition-colors">Click to view details &rarr;</p>
            </button>

            {{-- Card: DocPack Status --}}
            <button @click="open('docpack')"
                class="group text-left bg-card border border-amber-400/40 rounded-xl p-5 hover:border-amber-500 hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-amber-700">{{ $pendingDocpacks->count() }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Doc / Package Status</p>
                <p class="text-[11px] text-amber-600/70 mt-2">Pending &amp; stored items</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-amber-600 transition-colors">Click to view details &rarr;</p>
            </button>

            {{-- Modal Overlay --}}
            <div
                x-show="modal !== null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="close()"
                style="display:none"
            >
                <div
                    x-show="modal !== null"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden"
                    style="display:none"
                >
                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
                        <div>
                            <h2 class="text-sm font-semibold text-card-foreground" x-text="{
                                room:    'Room Bookings',
                                vehicle: 'Vehicle Bookings',
                                visitor: 'Visitors Today',
                                docpack: 'Doc / Package Status'
                            }[modal]"></h2>
                            <p class="text-xs text-muted-foreground mt-0.5" x-text="items.length + ' total records'"></p>
                        </div>
                        <button @click="close()" class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-accent transition-colors text-muted-foreground hover:text-foreground">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Modal Body: scrollable card grid --}}
                    <div class="overflow-y-auto flex-1 p-6">
                        <template x-if="paged.length === 0">
                            <p class="text-sm text-muted-foreground text-center py-8 italic">No records found.</p>
                        </template>

                        {{-- Room Booking Cards --}}
                        <template x-if="modal === 'room'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(b, i) in paged" :key="i">
                                    <div class="bg-background border border-border rounded-xl p-4">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <p class="text-xs font-semibold text-foreground leading-snug" x-text="b.meeting_title || '—'"></p>
                                            <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full font-medium"
                                                :class="b.status === 'ongoing' || b.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                x-text="b.status ? b.status.charAt(0).toUpperCase() + b.status.slice(1) : '—'">
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground" x-text="(b.room?.room_name ?? '—') + ' · ' + (b.date ?? '')"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="(b.start_time ? b.start_time.substring(0,5) : '') + '–' + (b.end_time ? b.end_time.substring(0,5) : '')"></p>
                                        <p class="text-[11px] text-muted-foreground mt-1" x-text="b.requester_name ? 'By: ' + b.requester_name : ''"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Vehicle Booking Cards --}}
                        <template x-if="modal === 'vehicle'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(b, i) in paged" :key="i">
                                    <div class="bg-background border border-border rounded-xl p-4">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <p class="text-xs font-semibold text-foreground leading-snug" x-text="b.borrower_name || '—'"></p>
                                            <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full font-medium"
                                                :class="b.status === 'approved' || b.status === 'on_road' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                                                x-text="b.status ? b.status.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase()) : '—'">
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground" x-text="b.vehicle?.name ?? '—'"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="b.start_at ? 'From: ' + b.start_at : ''"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="b.destination ? 'To: ' + b.destination : ''"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Visitor Cards --}}
                        <template x-if="modal === 'visitor'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(v, i) in paged" :key="i">
                                    <div class="bg-background border border-border rounded-xl p-4">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-8 h-8 rounded-full bg-violet-500/10 flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4" stroke-width="2"/>
                                                </svg>
                                            </div>
                                            <p class="text-xs font-semibold text-foreground truncate" x-text="v.name || '—'"></p>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground" x-text="v.instansi || v.keperluan || '—'"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="v.jam_in ? 'In: ' + v.jam_in : ''"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="v.visitor_count > 1 ? v.visitor_count + ' visitors' : ''"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- DocPack Cards --}}
                        <template x-if="modal === 'docpack'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(d, i) in paged" :key="i">
                                    <div class="bg-background border border-border rounded-xl p-4">
                                        <div class="flex items-start justify-between gap-2 mb-2">
                                            <p class="text-xs font-semibold text-foreground leading-snug" x-text="d.item_name || '—'"></p>
                                            <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full font-medium"
                                                :class="d.status === 'stored' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                                                x-text="d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : '—'">
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground capitalize" x-text="d.type || 'item'"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="d.nama_pengirim ? 'From: ' + d.nama_pengirim : ''"></p>
                                        <p class="text-[11px] text-muted-foreground" x-text="d.nama_penerima ? 'To: ' + d.nama_penerima : ''"></p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Modal Footer: Pagination --}}
                    <div class="border-t border-border px-6 py-3 flex items-center justify-between shrink-0 bg-card">
                        <span class="text-xs text-muted-foreground"
                            x-text="'Page ' + page + ' of ' + totalPages + ' · ' + items.length + ' items'">
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="page = Math.max(1, page - 1)"
                                :disabled="page === 1"
                                class="px-3 py-1.5 text-xs rounded-md border border-border bg-secondary hover:bg-accent disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                &larr; Prev
                            </button>
                            <template x-for="p in totalPages" :key="p">
                                <button
                                    @click="page = p"
                                    :class="page === p ? 'bg-foreground text-background border-foreground' : 'bg-secondary hover:bg-accent border-border text-foreground'"
                                    class="w-7 h-7 text-xs rounded-md border transition-colors font-medium"
                                    x-text="p">
                                </button>
                            </template>
                            <button
                                @click="page = Math.min(totalPages, page + 1)"
                                :disabled="page === totalPages"
                                class="px-3 py-1.5 text-xs rounded-md border border-border bg-secondary hover:bg-accent disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- end status cards + modal x-data --}}
         {{-- Full-width Chart --}}
        <div class="bg-card border border-border p-6 rounded-lg">
            <h3 class="text-sm font-semibold text-card-foreground mb-4">
                {{ __('app.booking_trends') }} — {{ $selectedYear }}
            </h3>
            <div wire:ignore style="position: relative; height: 520px;">
                <canvas id="chart"></canvas>
            </div>
        </div>
    </main>
</div>


@push('scripts')
{{-- Chart.js is bundled via resources/js/app.js (chart.js npm package) and exposed as window.Chart --}}
<script>
    const CHART_COLORS = {
        'room':    { border: '#4E653D', bg: 'rgba(78, 101, 61, 0.1)' },
        'vehicle': { border: '#4A2F24', bg: 'rgba(74, 47, 36, 0.1)' },
        'visitor': { border: '#7C3AED', bg: 'rgba(124, 58, 237, 0.1)' },
        'docpack': { border: '#D97706', bg: 'rgba(217, 119, 6, 0.1)' },
    };
    const FALLBACK_COLORS = ['#354C2B', '#CDDEA7'];

    function applyDatasetStyles(datasets) {
        return datasets.map((ds, i) => {
            const { type: dsType, ...rest } = ds;
            const c = CHART_COLORS[dsType] ?? {
                border: FALLBACK_COLORS[i] ?? '#374151',
                bg:     (FALLBACK_COLORS[i] ?? '#374151') + '14',
            };
            return {
                ...rest,
                borderColor:      c.border,
                backgroundColor:  c.bg,
                borderWidth:      2,
                tension:          0.35,
                fill:             false,
                pointRadius:      3,
                pointHoverRadius: 5,
            };
        });
    }

    function buildChart(labels, datasets) {
        const ctx = document.getElementById('chart');
        if (!ctx) return;
        if (window.dashChart && typeof window.dashChart.destroy === 'function') {
            window.dashChart.destroy();
        }
        window.dashChart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: applyDatasetStyles(datasets) },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: { family: 'Inter', size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'hsl(0 0% 9%)',
                        titleFont: { family: 'Inter', size: 12 },
                        bodyFont: { family: 'Inter', size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Inter', size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        title: { display: true, text: @json(__('app.bookings_axis')), font: { family: 'Inter', size: 12 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } },
                        title: { display: true, text: @json(__('app.month_axis')), font: { family: 'Inter', size: 12 } }
                    }
                }
            }
        });
    }

    function updateChart(labels, datasets) {
        if (!window.dashChart) { buildChart(labels, datasets); return; }
        window.dashChart.data.labels = labels;
        window.dashChart.data.datasets = applyDatasetStyles(datasets);
        window.dashChart.update('active');
    }

    // The Vite bundle loads as a module (deferred), so window.Chart may not be
    // available yet when this inline script runs. Poll until it is ready.
    function waitForChart(cb) {
        if (window.Chart) { cb(); return; }
        var t = setInterval(function () {
            if (window.Chart) { clearInterval(t); cb(); }
        }, 20);
    }

    document.addEventListener('DOMContentLoaded', () => {
        waitForChart(() => buildChart(@json($labels), @json($datasets)));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('chart-data-updated', ({ labels, datasets }) => {
            waitForChart(() => updateChart(labels, datasets));
        });
    });
</script>
@endpush
