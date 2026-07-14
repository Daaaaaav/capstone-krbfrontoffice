<div class="min-h-screen bg-background" wire:poll.60s>
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Page header — simple greeting --}}
        <x-page-header
            title="{{ __('app.dashboard') }}"
            subtitle="{{ __('app.dashboard_subtitle') }}"
        />

        {{-- KPI Cards --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card
                label="{{ __('app.room_bookings_label') }}"
                :value="$weeklyRoomBookingsCount"
                icon="heroicon-o-calendar-days"
                href="{{ route('receptionist.schedule') }}"
            />
            <x-stat-card
                label="{{ __('app.vehicle_bookings_label') }}"
                :value="$weeklyVehicleBookingsCount"
                icon="heroicon-o-truck"
                href="{{ route('receptionist.bookingvehicle') }}"
            />
            <x-stat-card
                label="{{ __('app.guest_visits') }}"
                :value="$weeklyGuestsCount"
                icon="heroicon-o-user-group"
                href="{{ route('receptionist.guestbook') }}"
            />
            <x-stat-card
                label="{{ __('app.documents_packages') }}"
                :value="$weeklyDocsCount"
                icon="heroicon-o-archive-box"
                href="{{ route('receptionist.docpackform') }}"
            />
        </section>

        {{-- Quick Actions + Recent Activity --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Recent Activity — spans 2 cols --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Latest Room Bookings --}}
                <div class="bg-card border border-border rounded-lg">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                        <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.recent_room_bookings') }}</h3>
                        <a href="{{ route('receptionist.bookinghistory') }}" class="text-xs text-muted-foreground hover:text-foreground transition-colors">{{ __('app.view_all') }}</a>
                    </div>
                    @if($latestBookingRooms->isEmpty())
                        <x-empty-state icon="heroicon-o-calendar-days" title="{{ __('app.no_room_bookings') }}" description="{{ __('app.no_bookings_7days') }}" />
                    @else
                        <div class="divide-y divide-border">
                            @foreach($latestBookingRooms as $br)
                                <div wire:click="openDetailModal({{ $br['id'] }})" class="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                                            <x-heroicon-o-calendar-days class="w-4 h-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $br['title'] }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $br['date'] }} · {{ $br['time'] }}</p>
                                        </div>
                                    </div>
                                    <x-status-badge :status="$br['status_label']" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Latest Vehicle Bookings --}}
                <div class="bg-card border border-border rounded-lg">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                        <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.vehicle_bookings_label') }}</h3>
                        <a href="{{ route('receptionist.vehiclestatus') }}" class="text-xs text-muted-foreground hover:text-foreground transition-colors">{{ __('app.view_all') }}</a>
                    </div>
                    @if($latestVehicleBookings->isEmpty())
                        <x-empty-state icon="heroicon-o-truck" title="{{ __('app.no_vehicle_bookings') ?? 'No vehicle bookings' }}" description="{{ __('app.no_bookings_7days') }}" />
                    @else
                        <div class="divide-y divide-border">
                            @foreach($latestVehicleBookings as $vb)
                                <div wire:click="openVehicleDetailModal({{ $vb['id'] }})" class="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                                            <x-heroicon-o-truck class="w-4 h-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $vb['borrower'] }}</p>
                                            <p class="text-xs text-muted-foreground truncate">{{ $vb['vehicle_name'] }} · {{ $vb['time'] }}</p>
                                        </div>
                                    </div>
                                    <x-status-badge :status="$vb['status_label']" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Latest Guest Entries --}}
                <div class="bg-card border border-border rounded-lg">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                        <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.recent_guests') }}</h3>
                        <a href="{{ route('receptionist.guestbookhistory') }}" class="text-xs text-muted-foreground hover:text-foreground transition-colors">{{ __('app.view_all') }}</a>
                    </div>
                    @if($latestGuests->isEmpty())
                        <x-empty-state icon="heroicon-o-user-group" title="{{ __('app.no_guests') }}" description="{{ __('app.no_guest_entries') }}" />
                    @else
                        <div class="divide-y divide-border">
                            @foreach($latestGuests as $g)
                                <div class="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-xs font-semibold shrink-0">
                                            {{ strtoupper(substr($g['name'], 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $g['name'] }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $g['purpose'] }} · {{ $g['date'] }}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-muted-foreground font-mono">{{ $g['time_in'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right Column: Quick Actions + Latest Docs --}}
            <div class="space-y-4">

                {{-- Quick Actions --}}
                <div class="bg-card border border-border rounded-lg">
                    <div class="px-4 py-3 border-b border-border">
                        <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.quick_actions') }}</h3>
                    </div>
                    <div class="p-3 space-y-1.5">
                        <a href="{{ route('receptionist.guestbook') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-muted transition-colors group">
                            <div class="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                                <x-heroicon-o-user-plus class="w-4 h-4 text-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ __('app.new_guest_entry') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('app.register_visitor') }}</p>
                            </div>
                        </a>
                        <a href="{{ route('receptionist.schedule') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-muted transition-colors group">
                            <div class="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                                <x-heroicon-o-calendar-days class="w-4 h-4 text-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ __('app.book_a_room') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('app.schedule_meeting_room') }}</p>
                            </div>
                        </a>
                        <a href="{{ route('receptionist.docpackform') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-muted transition-colors group">
                            <div class="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                                <x-heroicon-o-document-text class="w-4 h-4 text-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ __('app.docpac_form_action') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('app.log_doc_package') }}</p>
                            </div>
                        </a>
                        <a href="{{ route('receptionist.bookingvehicle') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-muted transition-colors group">
                            <div class="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                                <x-heroicon-o-truck class="w-4 h-4 text-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ __('app.book_vehicle_action') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('app.reserve_vehicle') }}</p>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Latest Documents & Packages --}}
                <div class="bg-card border border-border rounded-lg">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                        <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.recent_docs_packages') }}</h3>
                        <a href="{{ route('receptionist.docpackhistory') }}" class="text-xs text-muted-foreground hover:text-foreground transition-colors">{{ __('app.view_all') }}</a>
                    </div>
                    @if($latestDocs->isEmpty())
                        <x-empty-state icon="heroicon-o-archive-box" title="{{ __('app.no_documents') }}" description="{{ __('app.no_docs_recorded') }}" />
                    @else
                        <div class="divide-y divide-border">
                            @foreach($latestDocs as $d)
                                <div class="px-4 py-3 hover:bg-muted/50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-foreground truncate">{{ $d['item'] }}</p>
                                        <x-status-badge :status="$d['status']" />
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $d['type'] }} · {{ $d['direction'] }} · {{ $d['created'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </main>

    {{-- ═══ BOOKING DETAIL MODAL ═══ --}}
    @if($showDetailModal && $selectedBookingDetail)
    @php
        $detail = $selectedBookingDetail;
        $isPending = strtolower($detail->status ?? '') === 'pending';
        $isOnline  = in_array($detail->booking_type, ['online_meeting', 'onlinemeeting']);
        $statusClass = [
            'approved'  => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
            'pending'   => 'bg-amber-500/10 text-amber-600 border-amber-500/20',
            'rejected'  => 'bg-rose-500/10 text-rose-600 border-rose-500/20',
            'completed' => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
            'cancelled' => 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        ];
        $requesterName  = $detail->user?->full_name ?? $detail->user?->name ?? '—';
        $departmentName = $detail->department?->department_name ?? $detail->user?->department?->department_name ?? '—';
    @endphp
    <div class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4"
        role="dialog" aria-modal="true"
        wire:key="dash-detail-modal-{{ $detail->bookingroom_id }}"
        wire:keydown.escape.window="closeDetailModal">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeDetailModal"></div>

        <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden transform transition-all duration-300 flex flex-col max-h-[85vh]" tabindex="-1">

            {{-- Header --}}
            <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                        <x-heroicon-o-eye class="w-4 h-4 text-[#CDDEA7]" />
                    </div>
                    <h3 class="font-bold tracking-tight text-base">{{ __('app.detail_booking') }}</h3>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeDetailModal">✕</button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                {{-- Title + Status --}}
                <div class="pb-3 border-b border-border">
                    <h4 class="text-base font-bold text-foreground mb-2 leading-tight">
                        {{ $detail->meeting_title ?? 'Untitled Meeting' }}
                    </h4>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $statusClass[strtolower($detail->status ?? 'cancelled')] ?? 'bg-muted text-muted-foreground border-border' }}">
                            {{ ucfirst(strtolower($detail->status ?? 'unknown')) }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                            {{ $isOnline ? 'Online' : 'Offline' }}
                        </span>
                        <span class="text-[10px] font-semibold text-muted-foreground/60 bg-muted/50 border border-border/40 px-2 py-0.5 rounded font-mono uppercase tracking-wider">ID: {{ $detail->bookingroom_id }}</span>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- Requester & Department --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-user class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.requester') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $requesterName }}</p>
                        </div>
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.department') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $departmentName }}</p>
                        </div>
                    </div>

                    {{-- Date & Time --}}
                    <div class="space-y-1 border-t border-border/40 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <x-heroicon-o-calendar class="w-3.5 h-3.5 text-muted-foreground/60" />
                            <span>{{ __('app.booking_time_label') }}</span>
                        </div>
                        <p class="text-sm font-semibold text-foreground">
                            {{ \Carbon\Carbon::parse($detail->date)->format('d M Y') }}
                            <span class="text-muted-foreground/40 mx-1.5">/</span>
                            {{ \Carbon\Carbon::parse($detail->start_time)->format('H:i') }} &ndash; {{ \Carbon\Carbon::parse($detail->end_time)->format('H:i') }}
                        </p>
                    </div>

                    {{-- Attendees + Room/Provider --}}
                    <div class="grid grid-cols-2 gap-4 border-t border-border/40 pt-3">
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-user-group class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.attendees_count') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $detail->number_of_attendees > 0 ? $detail->number_of_attendees : '—' }}</p>
                        </div>
                        @if(!$isOnline)
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-building-office-2 class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.meeting_room_label') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $detail->room?->room_name ?? '—' }}</p>
                        </div>
                        @else
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-swatch class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.online_provider_label') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground capitalize">{{ str_replace('_', ' ', $detail->online_provider ?? '—') }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Requirements --}}
                    @if($detail->requirements->isNotEmpty())
                    <div class="p-3 bg-muted/20 border border-border/60 rounded-xl space-y-2 border-t border-border/40 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Requirements</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($detail->requirements->pluck('name') as $req)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 font-medium">{{ $req }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Reject Reason (if rejected) --}}
                    @if($detail->book_reject)
                    <div class="p-3 bg-amber-500/5 border border-amber-500/20 rounded-xl space-y-1 border-t border-border/40 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 flex items-center gap-1.5">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                            <span>{{ __('app.reject_reason') }}</span>
                        </div>
                        <p class="text-xs text-amber-800 leading-relaxed whitespace-pre-wrap">{{ $detail->book_reject }}</p>
                    </div>
                    @endif

                    {{-- Special Notes --}}
                    @if(trim((string)($detail->special_notes ?? '')) !== '')
                    <div class="space-y-1 border-t border-border/40 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <x-heroicon-o-document-text class="w-3.5 h-3.5 text-muted-foreground/60" />
                            <span>{{ __('app.special_notes_label') }}</span>
                        </div>
                        <p class="text-xs text-foreground/80 leading-relaxed whitespace-pre-wrap">{{ $detail->special_notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Footer: Reject only if pending --}}
            <div class="border-t border-border px-6 py-4 flex items-center justify-between bg-muted/10">
                <div>
                    @if($isPending)
                    <button wire:click="openReject({{ $detail->bookingroom_id }})" type="button"
                        class="h-9 px-4 rounded-lg bg-destructive text-destructive-foreground text-xs font-semibold hover:bg-destructive/90 transition inline-flex items-center gap-1.5 shadow-sm">
                        <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                        <span>{{ __('app.reject') }}</span>
                    </button>
                    @endif
                </div>
                <button wire:click="closeDetailModal" type="button"
                    class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                    <span>{{ __('app.close') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ VEHICLE DETAIL MODAL ═══ --}}
    @if($showVehicleDetailModal && $selectedVehicleBookingDetail)
    @php
        $vd = $selectedVehicleBookingDetail;
        $vIsPending = strtolower($vd->status ?? '') === 'pending';
        $vStatusClass = [
            'approved'  => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
            'pending'   => 'bg-amber-500/10 text-amber-600 border-amber-500/20',
            'rejected'  => 'bg-rose-500/10 text-rose-600 border-rose-500/20',
            'ongoing'   => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
            'completed' => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
            'cancelled' => 'bg-gray-500/10 text-gray-600 border-gray-500/20',
        ];
        $vBorrower   = $vd->user?->full_name ?? $vd->borrower_name ?? '—';
        $vDepartment = $vd->department?->department_name ?? $vd->user?->department?->department_name ?? '—';
    @endphp
    <div class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4"
        role="dialog" aria-modal="true"
        wire:key="dash-vehicle-detail-{{ $vd->vehiclebooking_id }}"
        wire:keydown.escape.window="closeVehicleDetailModal">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeVehicleDetailModal"></div>

        <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden transform transition-all duration-300 flex flex-col max-h-[85vh]" tabindex="-1">

            {{-- Header --}}
            <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                        <x-heroicon-o-truck class="w-4 h-4 text-[#CDDEA7]" />
                    </div>
                    <h3 class="font-bold tracking-tight text-base">Vehicle Booking Detail</h3>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeVehicleDetailModal">✕</button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                {{-- Vehicle + Status --}}
                <div class="pb-3 border-b border-border">
                    <h4 class="text-base font-bold text-foreground mb-2 leading-tight">
                        {{ $vd->vehicle?->name ?? '—' }}
                        @if($vd->vehicle?->plate_number)
                        <span class="text-sm font-normal text-muted-foreground ml-1">({{ $vd->vehicle->plate_number }})</span>
                        @endif
                    </h4>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $vStatusClass[strtolower($vd->status ?? 'cancelled')] ?? 'bg-muted text-muted-foreground border-border' }}">
                            {{ ucfirst(strtolower($vd->status ?? 'unknown')) }}
                        </span>
                        <span class="text-[10px] font-semibold text-muted-foreground/60 bg-muted/50 border border-border/40 px-2 py-0.5 rounded font-mono uppercase tracking-wider">ID: {{ $vd->vehiclebooking_id }}</span>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- Borrower & Department --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-user class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>Borrower</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $vBorrower }}</p>
                        </div>
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.department') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ $vDepartment }}</p>
                        </div>
                    </div>

                    {{-- Start / End --}}
                    <div class="grid grid-cols-2 gap-4 border-t border-border/40 pt-3">
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-calendar class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>Start</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ \Carbon\Carbon::parse($vd->start_at)->format('d M Y H:i') }}</p>
                        </div>
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-calendar class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>End</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">{{ \Carbon\Carbon::parse($vd->end_at)->format('d M Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Purpose & Destination --}}
                    <div class="grid grid-cols-2 gap-4 border-t border-border/40 pt-3">
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Purpose</div>
                            <p class="text-sm font-semibold text-foreground">{{ $vd->purpose ?? '—' }}</p>
                        </div>
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Destination</div>
                            <p class="text-sm font-semibold text-foreground">{{ $vd->destination ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- Odd/Even + Purpose Type --}}
                    @if($vd->odd_even_area && $vd->odd_even_area !== 'tidak')
                    <div class="border-t border-border/40 pt-3 space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Plate Restriction</div>
                        <p class="text-sm font-semibold text-foreground capitalize">{{ $vd->odd_even_area }}</p>
                    </div>
                    @endif

                    {{-- Notes (rejection reason) --}}
                    @if(trim((string)($vd->notes ?? '')) !== '')
                    <div class="p-3 bg-amber-500/5 border border-amber-500/20 rounded-xl space-y-1 border-t border-border/40 pt-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 flex items-center gap-1.5">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                            <span>Notes / Rejection Reason</span>
                        </div>
                        <p class="text-xs text-amber-800 leading-relaxed whitespace-pre-wrap">{{ $vd->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Footer: Approve + Reject for pending --}}
            <div class="border-t border-border px-6 py-4 flex items-center justify-between bg-muted/10">
                <div class="flex items-center gap-2">
                    @if($vIsPending)
                    <button wire:click="approveVehicleBooking({{ $vd->vehiclebooking_id }})" type="button"
                        class="h-9 px-4 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition inline-flex items-center gap-1.5 shadow-sm"
                        wire:loading.attr="disabled" wire:target="approveVehicleBooking">
                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                        <span>Approve</span>
                    </button>
                    <button wire:click="openVehicleReject({{ $vd->vehiclebooking_id }})" type="button"
                        class="h-9 px-4 rounded-lg bg-destructive text-destructive-foreground text-xs font-semibold hover:bg-destructive/90 transition inline-flex items-center gap-1.5 shadow-sm">
                        <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                        <span>{{ __('app.reject') }}</span>
                    </button>
                    @endif
                </div>
                <button wire:click="closeVehicleDetailModal" type="button"
                    class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                    <span>{{ __('app.close') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ VEHICLE REJECT MODAL ═══ --}}
    @if($showVehicleRejectModal)
    <div class="fixed inset-0 z-[70] overflow-y-auto flex items-center justify-center p-4"
        role="dialog" aria-modal="true"
        wire:key="dash-vehicle-reject-modal"
        wire:keydown.escape.window="closeVehicleReject">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeVehicleReject"></div>

        <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden transform transition-all duration-300" tabindex="-1">
            <form wire:submit.prevent="confirmVehicleReject">
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <x-heroicon-o-x-circle class="w-4 h-4 text-[#CDDEA7]" />
                        </div>
                        <h3 class="font-bold tracking-tight text-base">Reject Vehicle Booking</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeVehicleReject">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-xs text-muted-foreground">{{ __('app.reject_reason_required') }}</p>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.reject_reason_ph') }} <span class="text-destructive">*</span></label>
                        <textarea wire:model.live="vehicleRejectReason"
                            rows="4"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                            placeholder="Contoh: Kendaraan sedang dalam perawatan / Jadwal bentrok"
                            required></textarea>
                        @error('vehicleRejectReason')
                        <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5">
                    <button type="button" class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5" wire:click="closeVehicleReject" wire:loading.attr="disabled" wire:target="confirmVehicleReject">
                        <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                        <span>{{ __('app.cancel') }}</span>
                    </button>
                    <button type="submit"
                        class="h-9 px-4 rounded-lg bg-destructive text-destructive-foreground text-xs font-semibold hover:bg-destructive/95 transition shadow-sm inline-flex items-center gap-1.5"
                        wire:loading.attr="disabled" wire:target="confirmVehicleReject">
                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                        <span>{{ __('app.reject') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>