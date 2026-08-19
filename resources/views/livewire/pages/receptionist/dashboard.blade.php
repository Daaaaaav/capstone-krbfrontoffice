<div class="min-h-screen bg-background" wire:poll.60s>
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Page header --}}
        <x-page-header
            title="{{ __('app.dashboard') }}"
            subtitle="{{ __('app.dashboard_subtitle') }}"
        >
            <x-slot:actions>
                <button wire:click="$refresh"
                    class="px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md border border-border hover:bg-accent transition-colors">
                    {{ __('app.refresh') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Statistics Cards (benchmark aligned) --}}
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Room Bookings Card --}}
            <a href="{{ route('receptionist.schedule') }}"
                class="group text-left bg-card border border-[#4E653D]/40 rounded-xl p-5 hover:border-[#4E653D] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4E653D]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4E653D]">{{ $weeklyRoomBookingsCount }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a4d2e]">{{ __('app.room_bookings_label') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4E653D] transition-colors">{{ __('app.click_to_manage') }} &rarr;</p>
            </a>

            {{-- Vehicle Bookings Card --}}
            <a href="{{ route('receptionist.bookingvehicle') }}"
                class="group text-left bg-card border border-[#4A2F24]/40 rounded-xl p-5 hover:border-[#4A2F24] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4A2F24]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4A2F24]">{{ $weeklyVehicleBookingsCount }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a241c]">{{ __('app.vehicle_bookings_label') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4A2F24] transition-colors">{{ __('app.click_to_manage') }} &rarr;</p>
            </a>

            {{-- Guest Visits Card --}}
            <a href="{{ route('receptionist.guestbook') }}"
                class="group text-left bg-card border border-violet-400/40 rounded-xl p-5 hover:border-violet-500 hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-violet-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-violet-700">{{ $weeklyGuestsCount }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-violet-800">{{ __('app.guest_visits') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-violet-600 transition-colors">{{ __('app.click_to_manage') }} &rarr;</p>
            </a>

            {{-- Documents / Packages Card --}}
            <a href="{{ route('receptionist.docpackform') }}"
                class="group text-left bg-card border border-amber-400/40 rounded-xl p-5 hover:border-amber-500 hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                            <line x1="12" y1="12" x2="12" y2="16"/>
                            <line x1="10" y1="14" x2="14" y2="14"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-amber-700">{{ $weeklyDocsCount }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-800">{{ __('app.documents_packages') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-amber-600 transition-colors">{{ __('app.click_to_manage') }} &rarr;</p>
            </a>
        </section>

        {{-- Quick Actions --}}
        <div class="bg-card border border-border rounded-xl p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-card-foreground">{{ __('app.quick_actions') }}</h2>
                <p class="text-xs text-muted-foreground mt-1">{{ __('app.manage_system_users_resources') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- New Guest Entry --}}
                <a href="{{ route('receptionist.guestbook') }}"
                   class="flex items-center gap-4 p-5 bg-purple-50 border-2 border-purple-100 hover:border-purple-300 hover:bg-purple-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                        <svg class="w-7 h-7 text-purple-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="22" y1="11" x2="16" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.new_guest_entry') }}</p>
                        <p class="text-xs text-purple-700/70 mt-0.5">{{ __('app.register_visitor') }}</p>
                    </div>
                </a>

                {{-- Book a Room --}}
                <a href="{{ route('receptionist.schedule') }}"
                   class="flex items-center gap-4 p-5 bg-orange-50 border-2 border-orange-100 hover:border-orange-300 hover:bg-orange-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                        <svg class="w-7 h-7 text-orange-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.book_a_room') }}</p>
                        <p class="text-xs text-orange-700/70 mt-0.5">{{ __('app.schedule_meeting_room') }}</p>
                    </div>
                </a>

                {{-- Log Doc/Package --}}
                <a href="{{ route('receptionist.docpackform') }}"
                   class="flex items-center gap-4 p-5 bg-amber-50 border-2 border-amber-100 hover:border-amber-300 hover:bg-amber-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                        <svg class="w-7 h-7 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.docpac_form_action') }}</p>
                        <p class="text-xs text-amber-700/70 mt-0.5">{{ __('app.log_doc_package') }}</p>
                    </div>
                </a>

                {{-- Reserve Vehicle --}}
                <a href="{{ route('receptionist.bookingvehicle') }}"
                   class="flex items-center gap-4 p-5 bg-blue-50 border-2 border-blue-100 hover:border-blue-300 hover:bg-blue-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                        <svg class="w-7 h-7 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.book_vehicle_action') }}</p>
                        <p class="text-xs text-blue-700/70 mt-0.5">{{ __('app.reserve_vehicle') }}</p>
                    </div>
                </a>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Latest Room Bookings --}}
            <div class="bg-card border border-border rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-card-foreground">{{ __('app.recent_room_bookings') }}</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Latest meeting schedules</p>
                    </div>
                    <a href="{{ route('receptionist.bookinghistory') }}" class="text-xs text-[#4E653D] hover:underline font-semibold transition-colors">{{ __('app.view_all') }} &rarr;</a>
                </div>
                @if($latestBookingRooms->isEmpty())
                    <x-empty-state icon="heroicon-o-calendar-days" title="{{ __('app.no_room_bookings') }}" description="{{ __('app.no_bookings_7days') }}" />
                @else
                    <div class="divide-y divide-border/60">
                        @foreach($latestBookingRooms as $br)
                            <div class="flex items-center justify-between py-2.5 hover:bg-muted/30 px-2 rounded-lg transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-[#4E653D]/10 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-foreground truncate">{{ $br['title'] }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $br['date'] }} &middot; {{ $br['time'] }}</p>
                                    </div>
                                </div>
                                <x-status-badge :status="$br['status']" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Latest Guest Entries --}}
            <div class="bg-card border border-border rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-card-foreground">{{ __('app.recent_guests') }}</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Visitors recorded today</p>
                    </div>
                    <a href="{{ route('receptionist.guestbookhistory') }}" class="text-xs text-violet-600 hover:underline font-semibold transition-colors">{{ __('app.view_all') }} &rarr;</a>
                </div>
                @if($latestGuests->isEmpty())
                    <x-empty-state icon="heroicon-o-user-group" title="{{ __('app.no_guests') }}" description="{{ __('app.no_guest_entries') }}" />
                @else
                    <div class="divide-y divide-border/60">
                        @foreach($latestGuests as $g)
                            <div class="flex items-center justify-between py-2.5 hover:bg-muted/30 px-2 rounded-lg transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-violet-600 text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                        {{ strtoupper(substr($g['name'], 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-foreground truncate">{{ $g['name'] }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $g['purpose'] }} &middot; {{ $g['date'] }}</p>
                                    </div>
                                </div>
                                <span class="text-xs text-muted-foreground font-mono bg-muted/50 px-2 py-0.5 rounded">{{ $g['time_in'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Latest Documents & Packages --}}
            <div class="bg-card border border-border rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-card-foreground">{{ __('app.recent_docs_packages') }}</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Incoming & outgoing mail/packages</p>
                    </div>
                    <a href="{{ route('receptionist.docpackhistory') }}" class="text-xs text-amber-600 hover:underline font-semibold transition-colors">{{ __('app.view_all') }} &rarr;</a>
                </div>
                @if($latestDocs->isEmpty())
                    <x-empty-state icon="heroicon-o-archive-box" title="{{ __('app.no_documents') }}" description="{{ __('app.no_docs_recorded') }}" />
                @else
                    <div class="divide-y divide-border/60">
                        @foreach($latestDocs as $d)
                            <div class="flex items-center justify-between py-2.5 hover:bg-muted/30 px-2 rounded-lg transition-colors">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $d['item'] }}</p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ $d['type'] }} &middot; {{ $d['direction'] }} &middot; {{ $d['created'] }}</p>
                                </div>
                                <x-status-badge :status="$d['status']" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Vehicle Quick History --}}
            <div class="bg-card border border-border rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-border mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-card-foreground">Vehicle History</h3>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Recent operational assignments</p>
                    </div>
                    <a href="{{ route('receptionist.vehicleshistory') }}" class="text-xs text-[#4A2F24] hover:underline font-semibold transition-colors">{{ __('app.view_all') }} &rarr;</a>
                </div>
                @if($latestVehicleBookings->isEmpty())
                    <x-empty-state icon="heroicon-o-truck" title="No Vehicle Bookings" description="No recent vehicle bookings found." />
                @else
                    <div class="divide-y divide-border/60">
                        @foreach($latestVehicleBookings as $v)
                            <div class="flex items-center justify-between py-2.5 hover:bg-muted/30 px-2 rounded-lg transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-foreground truncate">{{ $v['borrower'] }} &ndash; {{ $v['destination'] }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $v['date'] }} &middot; {{ $v['time'] }}</p>
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