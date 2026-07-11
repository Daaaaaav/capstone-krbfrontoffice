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

        {{-- Quick Actions --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('receptionist.guestbook') }}"
               class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-primary shadow-sm hover:shadow-md rounded-xl transition-all group">
                <div class="w-14 h-14 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <x-heroicon-o-user-plus class="w-7 h-7 text-primary" />
                </div>
                <div>
                    <p class="text-base font-bold text-foreground">{{ __('app.new_guest_entry') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.register_visitor') }}</p>
                </div>
            </a>
            <a href="{{ route('receptionist.schedule') }}"
               class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-primary shadow-sm hover:shadow-md rounded-xl transition-all group">
                <div class="w-14 h-14 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <x-heroicon-o-calendar-days class="w-7 h-7 text-primary" />
                </div>
                <div>
                    <p class="text-base font-bold text-foreground">{{ __('app.book_a_room') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.schedule_meeting_room') }}</p>
                </div>
            </a>
            <a href="{{ route('receptionist.docpackform') }}"
               class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-primary shadow-sm hover:shadow-md rounded-xl transition-all group">
                <div class="w-14 h-14 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <x-heroicon-o-document-text class="w-7 h-7 text-primary" />
                </div>
                <div>
                    <p class="text-base font-bold text-foreground">{{ __('app.docpac_form_action') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.log_doc_package') }}</p>
                </div>
            </a>
            <a href="{{ route('receptionist.bookingvehicle') }}"
               class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-primary shadow-sm hover:shadow-md rounded-xl transition-all group">
                <div class="w-14 h-14 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                    <x-heroicon-o-truck class="w-7 h-7 text-primary" />
                </div>
                <div>
                    <p class="text-base font-bold text-foreground">{{ __('app.book_vehicle_action') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.reserve_vehicle') }}</p>
                </div>
            </a>
        </section>

        {{-- Recent Activity --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

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
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                                        <x-heroicon-o-calendar-days class="w-4 h-4 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate">{{ $br['title'] }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $br['date'] }} · {{ $br['time'] }}</p>
                                    </div>
                                </div>
                                <x-status-badge :status="$br['status']" />
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

            {{-- Vehicle Quick History --}}
            <div class="bg-card border border-border rounded-lg">
                <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                    <h3 class="text-sm font-semibold text-card-foreground">Vehicle History</h3>
                    <a href="{{ route('receptionist.vehicleshistory') }}" class="text-xs text-muted-foreground hover:text-foreground transition-colors">{{ __('app.view_all') }}</a>
                </div>
                @if($latestVehicleBookings->isEmpty())
                    <x-empty-state icon="heroicon-o-truck" title="No Vehicle Bookings" description="No recent vehicle bookings found." />
                @else
                    <div class="divide-y divide-border">
                        @foreach($latestVehicleBookings as $v)
                            <div class="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                                        <x-heroicon-o-truck class="w-4 h-4 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate">{{ $v['borrower'] }} - {{ $v['destination'] }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $v['date'] }} · {{ $v['time'] }}</p>
                                    </div>
                                </div>
                                <x-status-badge :status="$v['status']" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

    </main>
</div>