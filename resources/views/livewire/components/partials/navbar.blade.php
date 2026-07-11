<div>
    <style>
        .dropdown-menu { display: none; opacity: 0; transform: translateY(-10px); transition: opacity .2s ease, transform .2s ease; }
        .dropdown-menu.show { display: block; opacity: 1; transform: translateY(0); }
        .mobile-menu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; }
        .mobile-menu.open { max-height: 100vh; }
        .mobile-dropdown-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .mobile-dropdown-content.open { max-height: 500px; }
        .profile-icon-bnw { filter: none; }
        .logo-full-white { filter: brightness(0) invert(1); opacity: 0.9; }

        span.bg-red-600:is([data-count="0"]) {
            display: none !important;
        }
    </style>

    {{-- FIXED NAVBAR --}}
    <nav class="bg-sidebar/95 backdrop-blur-md border-b border-sidebar-border/40 fixed inset-x-0 top-0 z-50 shadow-xl shadow-sidebar/5 w-full overflow-x-hidden">
        <div class="w-full max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
            <div class="flex justify-between items-center h-14 sm:h-16 w-full">
                {{-- Logo --}}
                <div class="flex-shrink-0">
                    @php
                    $company = auth()->user()?->company;
                    $rawLogo = $company?->image;
                    $fallback = asset('images/logo/kebun-raya-bogor.png');
                    $logoUrl = $fallback;

                    if (!empty($rawLogo)) {
                        if (preg_match('#^https?://#i', $rawLogo)) {
                            $logoUrl = $rawLogo;
                        } else {
                            $paths = [public_path($rawLogo), public_path('storage/'.$rawLogo), public_path('images/'.$rawLogo)];
                            foreach ($paths as $path) {
                                if (file_exists($path)) {
                                    $logoUrl = asset(str_replace(public_path() . '/', '', $path));
                                    break;
                                }
                            }
                        }
                    }
                    @endphp
                    <a href="{{ route('home') }}" class="transition-transform hover:scale-105 inline-block">
                        <img src="{{ $logoUrl }}" alt="{{ $company?->company_name ?? 'KRBS' }} Logo" class="h-8 sm:h-10 w-auto logo-full-white">
                    </a>
                </div>

                {{-- Desktop Menu --}}
                <div class="hidden md:flex items-center space-x-0.5 lg:space-x-1 flex-wrap justify-center">
                    <a href="{{ route('create-ticket') }}" class="px-2 lg:px-3.5 py-2 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('create-ticket') ? 'bg-primary text-white shadow-lg shadow-primary/15' : '' }} truncate">
                        {{ __('app.create_ticket') }}
                    </a>
                    <a href="{{ route('book-room') }}" class="px-2 lg:px-3.5 py-2 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('book-room') ? 'bg-primary text-white shadow-lg shadow-primary/15' : '' }} truncate">
                        {{ __('app.book_room') }}
                    </a>
                    <a href="{{ route('book-vehicle') }}" class="px-2 lg:px-3.5 py-2 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('book-vehicle') ? 'bg-primary text-white shadow-lg shadow-primary/15' : '' }} truncate">
                        {{ __('app.book_vehicle') }}
                    </a>

                    {{-- Status Dropdown --}}
                    @if(Auth::check())
                    <div class="relative" data-exclusive-dropdown>
                        <button type="button" data-dropdown-toggle class="px-2 lg:px-3.5 py-2 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 flex items-center gap-1 lg:gap-1.5 transition-all duration-300" aria-haspopup="true" aria-expanded="false">
                            <x-heroicon-o-chart-bar class="w-3 lg:w-4 h-3 lg:h-4 flex-shrink-0" />
                            <span class="hidden lg:inline truncate">{{ __('app.status') }}</span>
                            <span class="lg:hidden text-xs font-bold truncate">S</span>
                            <span class="ml-1 px-1 lg:px-1.5 py-0.5 text-xs font-bold text-white bg-accent rounded-full leading-none shadow-sm whitespace-nowrap" data-count="{{ $totalUnreadCount }}">{{ $totalUnreadCount }}</span>
                            <x-heroicon-o-chevron-down class="w-3 lg:w-4 h-3 lg:h-4 transition-transform duration-300 flex-shrink-0" data-dropdown-arrow />
                        </button>
                        <div data-dropdown-menu class="dropdown-menu absolute right-0 mt-2 w-44 lg:w-52 bg-sidebar/95 backdrop-blur-md rounded-xl lg:rounded-2xl shadow-2xl border border-sidebar-border/60 py-2 z-50">
                            <a href="{{ route('ticketstatus') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors truncate">
                                <x-heroicon-o-ticket class="w-3.5 lg:w-4 h-3.5 lg:h-4 flex-shrink-0" />
                                <span class="truncate">{{ __('app.ticket_status') }}</span>
                                <span class="ml-auto px-1 lg:px-1.5 py-0.5 text-xs font-bold text-white bg-accent rounded-full leading-none shadow-sm whitespace-nowrap flex-shrink-0" data-count="{{ $totalUnreadCount }}">{{ $totalUnreadCount }}</span>
                            </a>
                            <a href="{{ route('bookingstatus') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors truncate">
                                <x-heroicon-o-calendar class="w-3.5 lg:w-4 h-3.5 lg:h-4 flex-shrink-0" />
                                <span class="truncate">{{ __('app.meeting_status') }}</span>
                            </a>
                            <a href="{{ route('vehiclestatus') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors truncate">
                                <x-heroicon-o-truck class="w-3.5 lg:w-4 h-3.5 lg:h-4 flex-shrink-0" />
                                <span class="truncate">{{ __('app.vehicle_status') }}</span>
                            </a>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('ticketstatus') }}" class="px-2 lg:px-3.5 py-2 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('ticketstatus') ? 'bg-primary text-white shadow-lg shadow-primary/15' : '' }} truncate">
                        Status
                    </a>
                    @endif

                    @guest
                    <a href="{{ route('login') }}" class="ml-1 lg:ml-3 bg-primary hover:bg-primary/90 text-primary-foreground hover:shadow-lg hover:shadow-primary/15 px-2 lg:px-4 py-1.5 lg:py-2 rounded-lg lg:rounded-xl text-xs lg:text-sm font-semibold transition-all duration-300 hover:scale-105 active:scale-95 flex items-center gap-1 lg:gap-2 whitespace-nowrap flex-shrink-0">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-3 lg:w-4 h-3 lg:h-4" />
                        <span class="hidden sm:inline">{{ __('app.login') }}</span>
                        <span class="sm:hidden text-xs">Log</span>
                    </a>
                    @endguest

                    {{-- Notification Bell (Desktop) --}}
                    @auth
                    <a href="{{ route('notifications.index') }}" class="relative ml-1 lg:ml-2 flex items-center justify-center w-8 h-8 lg:w-9 lg:h-9 rounded-full text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300">
                        <x-heroicon-o-bell class="w-4.5 h-4.5 lg:w-5 lg:h-5" />
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-sidebar"></span>
                    </a>
                    @endauth

                    {{-- Language Toggle (Desktop) --}}
                    <div class="relative ml-1 lg:ml-2" x-data="{ open: false }">
                        @php $isEn = app()->getLocale() === 'en'; @endphp
                        <button @click.stop="open = !open" type="button"
                            class="flex items-center gap-0.5 lg:gap-1.5 px-1.5 lg:px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 text-sidebar-foreground hover:text-white hover:bg-primary/10 border border-sidebar-border/40 flex-shrink-0">
                            <svg class="w-3 lg:w-3.5 h-3 lg:h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            <span class="text-xs">{{ $isEn ? 'EN' : 'ID' }}</span>
                            <svg class="w-2.5 lg:w-3 h-2.5 lg:h-3 transition-transform duration-200 flex-shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div x-show="open" x-transition @click.outside="open = false"
                            class="absolute right-0 top-full mt-1.5 w-32 lg:w-36 rounded-lg lg:rounded-xl bg-sidebar/95 backdrop-blur-md border border-sidebar-border/60 shadow-2xl z-[9999] overflow-hidden"
                            style="display:none;">
                            <a href="{{ route('lang.switch', 'en') }}" class="flex items-center gap-2 px-2.5 lg:px-3 py-2 lg:py-2.5 text-xs lg:text-sm transition-colors {{ $isEn ? 'text-white bg-primary/10 font-semibold' : 'text-sidebar-foreground hover:text-white hover:bg-primary/10' }} truncate">
                                <span>🇬🇧</span><span class="truncate">{{ __('app.english') }}</span>
                                @if($isEn)<svg class="w-3 h-3 ml-auto text-primary flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                            </a>
                            <a href="{{ route('lang.switch', 'id') }}" class="flex items-center gap-2 px-2.5 lg:px-3 py-2 lg:py-2.5 text-xs lg:text-sm transition-colors {{ !$isEn ? 'text-white bg-primary/10 font-semibold' : 'text-sidebar-foreground hover:text-white hover:bg-primary/10' }} truncate">
                                <span>🇮🇩</span><span class="truncate">{{ __('app.indonesian') }}</span>
                                @if(!$isEn)<svg class="w-3 h-3 ml-auto text-primary flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                            </a>
                        </div>
                    </div>

                    @auth
                    {{-- Profile Dropdown --}}
                    <div class="relative ml-1 lg:ml-3" data-exclusive-dropdown>
                        <button type="button" data-dropdown-toggle class="flex items-center gap-1 lg:gap-2 px-1.5 lg:px-3 py-1 lg:py-1 text-xs lg:text-sm font-medium rounded-lg lg:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300" aria-haspopup="true" aria-expanded="false">
                            <div class="w-6 lg:w-8 h-6 lg:h-8 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-semibold text-xs profile-icon-bnw shadow-md flex-shrink-0">
                                {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->name ?? 'U', 0, 1)) }}
                            </div>
                            <span class="hidden xl:block truncate text-xs lg:text-sm">{{ explode(' ', auth()->user()->full_name ?? auth()->user()->name ?? 'User')[0] }}</span>
                            <x-heroicon-o-chevron-down class="w-3 lg:w-4 h-3 lg:h-4 transition-transform duration-300 flex-shrink-0" data-dropdown-arrow />
                        </button>
                        <div data-dropdown-menu class="dropdown-menu absolute right-0 mt-2 w-56 lg:w-64 bg-sidebar/95 backdrop-blur-md rounded-xl lg:rounded-2xl shadow-2xl border border-sidebar-border/60 py-3 z-50">
                            <div class="px-3 lg:px-4 py-3 border-b border-sidebar-border/40">
                                <p class="text-xs lg:text-sm font-semibold text-white truncate font-sans">{{ auth()->user()->full_name ?? auth()->user()->name ?? 'User' }}</p>
                                <p class="text-xs text-sidebar-foreground/75 mt-0.5 truncate font-mono text-xs">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors mt-2">
                                <x-heroicon-o-user-circle class="w-4 lg:w-5 h-4 lg:h-5 flex-shrink-0" /> {{ __('app.my_profile') }}
                            </a>

                            @php $role = auth()->user()->role->name; @endphp

                            @if($role === 'Manager')
                            <a href="{{ route('manager.dashboard') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                                <x-heroicon-o-shield-check class="w-4 lg:w-5 h-4 lg:h-5 flex-shrink-0" /> {{ __('app.manager_db') }}
                            </a>
                            @endif

                            @if(in_array($role, ['Manager', 'Receptionist']))
                            <a href="{{ route('receptionist.dashboard') }}" class="flex items-center gap-2 lg:gap-3 px-3 lg:px-4 py-2 lg:py-2.5 text-xs lg:text-sm text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                                <x-heroicon-o-clipboard-document-list class="w-4 lg:w-5 h-4 lg:h-5 flex-shrink-0" /> {{ __('app.receptionist_db') }}
                            </a>
                            @endif
                            <div class="border-t border-sidebar-border/40 my-2"></div>
                            <form method="POST" action="{{ route('logout') }}" class="px-2 lg:px-3">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2 lg:gap-3 px-2 lg:px-3 py-1.5 lg:py-2 text-xs lg:text-sm text-white hover:text-rose-300 hover:bg-rose-950/20 rounded-lg lg:rounded-xl transition-colors">
                                    <x-heroicon-o-arrow-left-on-rectangle class="w-4 lg:w-5 h-4 lg:h-5 flex-shrink-0" /> {{ __('app.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                    @endauth
                </div>

                {{-- Mobile Hamburger --}}
                <button id="hamburger" class="md:hidden p-1.5 rounded-lg text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 focus:outline-none flex-shrink-0" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mobile-menu">
                    <div class="w-5 h-5 flex flex-col justify-center space-y-1 transition-transform duration-300" data-hamburger-icon>
                        <span class="block w-5 h-0.5 bg-current rounded-full transition-transform duration-300 ease-in-out origin-center"></span>
                        <span class="block w-5 h-0.5 bg-current rounded-full transition-opacity duration-300 ease-in-out"></span>
                        <span class="block w-5 h-0.5 bg-current rounded-full transition-transform duration-300 ease-in-out origin-center"></span>
                    </div>
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div id="mobile-menu" class="md:hidden mobile-menu border-t border-sidebar-border/40 bg-sidebar/95 backdrop-blur-md max-h-screen overflow-y-auto">
            <div class="px-3 sm:px-4 py-3 space-y-2">
                @auth
                <div class="mb-3 p-2 sm:p-3 bg-sidebar-accent border border-sidebar-border/60 rounded-lg sm:rounded-2xl">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 sm:w-10 h-8 sm:h-10 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-semibold text-xs profile-icon-bnw shadow-md flex-shrink-0">
                            {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-semibold text-white truncate font-sans">{{ auth()->user()->full_name ?? auth()->user()->name ?? 'User' }}</p>
                            <p class="text-xs text-sidebar-foreground/75 truncate font-mono">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
                @endauth

                {{-- Mobile Nav Links --}}
                <a href="{{ route('create-ticket') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('create-ticket') ? 'bg-primary text-white' : '' }}">
                    <x-heroicon-o-ticket class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.create_ticket') }}
                </a>
                <a href="{{ route('book-room') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('book-room') ? 'bg-primary text-white' : '' }}">
                    <x-heroicon-o-building-office class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.book_room') }}
                </a>
                <a href="{{ route('book-vehicle') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-all duration-300 {{ request()->routeIs('book-vehicle') ? 'bg-primary text-white' : '' }}">
                    <x-heroicon-o-truck class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.book_vehicle') }}
                </a>

                {{-- Mobile Status Dropdown --}}
                @if(Auth::check())
                <div data-mobile-dropdown>
                    <button type="button" data-mobile-toggle class="w-full flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                        <span class="flex items-center gap-2 sm:gap-3">
                            <x-heroicon-o-chart-bar class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.status') }}
                            <span class="ml-1 px-1 lg:px-1.5 py-0.5 text-xs font-bold text-white bg-accent rounded-full leading-none shadow-sm whitespace-nowrap" data-count="{{ $totalUnreadCount }}">{{ $totalUnreadCount }}</span>
                        </span>
                        <x-heroicon-o-chevron-down data-mobile-arrow class="w-4 sm:w-5 h-4 sm:h-5 transition-transform duration-300 flex-shrink-0" />
                    </button>
                    <div data-mobile-content class="mobile-dropdown-content pl-3 sm:pl-4">
                        <a href="{{ route('ticketstatus') }}" class="flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm text-sidebar-foreground/80 hover:text-white hover:bg-primary/10 rounded-lg sm:rounded-xl transition-colors mt-1">
                            <span class="flex items-center gap-2 sm:gap-3"><x-heroicon-o-ticket class="w-3.5 sm:w-4 h-3.5 sm:h-4 flex-shrink-0" /> {{ __('app.ticket_status') }}</span>
                            <span class="px-1 py-0.5 text-xs font-bold text-white bg-accent rounded-full leading-none shadow-sm whitespace-nowrap flex-shrink-0" data-count="{{ $totalUnreadCount }}">{{ $totalUnreadCount }}</span>
                        </a>
                        <a href="{{ route('bookingstatus') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm text-sidebar-foreground/80 hover:text-white hover:bg-primary/10 rounded-lg sm:rounded-xl transition-colors mt-1">
                            <x-heroicon-o-calendar class="w-3.5 sm:w-4 h-3.5 sm:h-4 flex-shrink-0" /> {{ __('app.meeting_status') }}
                        </a>
                        <a href="{{ route('vehiclestatus') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm text-sidebar-foreground/80 hover:text-white hover:bg-primary/10 rounded-lg sm:rounded-xl transition-colors mt-1">
                            <x-heroicon-o-truck class="w-3.5 sm:w-4 h-3.5 sm:h-4 flex-shrink-0" /> {{ __('app.vehicle_status') }}
                        </a>
                    </div>
                </div>
                @else
                <a href="{{ route('ticketstatus') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10"><x-heroicon-o-chart-bar class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> Status</a>
                @endif

                @auth
                <div class="border-t border-sidebar-border/40 pt-3 mt-3 space-y-1">
                    <a href="{{ route('notifications.index') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                        <x-heroicon-o-bell class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" />
                        <span>{{ __('app.notifications', ['default' => 'Notifications']) }}</span>
                        <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">New</span>
                    </a>
                    <a href="{{ route('profile') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors"><x-heroicon-o-user-circle class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.my_profile') }}</a>

                    @php $role = auth()->user()->role->name; @endphp

                    @if($role === 'Manager')
                    <a href="{{ route('manager.dashboard') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                        <x-heroicon-o-shield-check class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.manager_db') }}
                    </a>
                    @endif

                    @if(in_array($role, ['Manager', 'Receptionist']))
                    <a href="{{ route('receptionist.dashboard') }}" class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-sidebar-foreground hover:text-white hover:bg-primary/10 transition-colors">
                        <x-heroicon-o-clipboard-document-list class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.receptionist_db') }}
                    </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-medium rounded-lg sm:rounded-xl text-white hover:text-rose-300 hover:bg-rose-950/20 transition-colors"><x-heroicon-o-arrow-left-on-rectangle class="w-4 sm:w-5 h-4 sm:h-5 flex-shrink-0" /> {{ __('app.logout') }}</button>
                    </form>
                </div>
                @endauth

                @guest
                <div class="border-t border-sidebar-border/40 pt-3 mt-3">
                    <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base font-semibold text-primary-foreground bg-primary rounded-lg sm:rounded-xl hover:bg-primary/95 transition-all"><x-heroicon-o-arrow-right-on-rectangle class="w-4 sm:w-5 h-4 sm:h-5" /> {{ __('app.login_register') }}</a>
                </div>
                @endguest

                {{-- Language Toggle (Mobile) --}}
                <div class="border-t border-sidebar-border/40 pt-3 mt-3">
                    @php $isEnMobile = app()->getLocale() === 'en'; @endphp
                    <p class="px-3 sm:px-4 text-xs font-semibold text-sidebar-foreground/50 uppercase tracking-wider mb-2">{{ __('app.language') }}</p>
                    <div class="flex gap-2 px-3 sm:px-4">
                        <a href="{{ route('lang.switch', 'en') }}"
                           class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ $isEnMobile ? 'bg-primary text-white shadow-md shadow-primary/20' : 'text-sidebar-foreground hover:bg-primary/10 border border-sidebar-border/40' }}">
                            <span>🇬🇧</span> <span>{{ __('app.english') }}</span>
                            @if($isEnMobile)
                                <svg class="w-3.5 h-3.5 ml-auto shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </a>
                        <a href="{{ route('lang.switch', 'id') }}"
                           class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ !$isEnMobile ? 'bg-primary text-white shadow-md shadow-primary/20' : 'text-sidebar-foreground hover:bg-primary/10 border border-sidebar-border/40' }}">
                            <span>🇮🇩</span> <span>{{ __('app.indonesian') }}</span>
                            @if(!$isEnMobile)
                                <svg class="w-3.5 h-3.5 ml-auto shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Spacer --}}
    <div class="h-14 sm:h-16"></div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            console.log('Livewire Navbar Component Initialized/Updated.');
        });

        document.addEventListener('DOMContentLoaded', () => {
            const exclusiveDropdowns = document.querySelectorAll('[data-exclusive-dropdown]');
            const mobileDropdown = document.querySelector('[data-mobile-dropdown]');
            const mobileContent = mobileDropdown ? mobileDropdown.querySelector('[data-mobile-content]') : null;
            const mobileArrow = mobileDropdown ? mobileDropdown.querySelector('[data-mobile-arrow]') : null;

            const mobileMenu = document.getElementById('mobile-menu');
            const hamburger = document.getElementById('hamburger');
            const hamburgerIcon = document.querySelector('[data-hamburger-icon]');

            function toggleExclusiveDropdown(targetDropdown) {
                const menu = targetDropdown.querySelector('[data-dropdown-menu]');
                const arrow = targetDropdown.querySelector('[data-dropdown-arrow]');
                const isOpen = menu.classList.contains('show');

                const toggle = targetDropdown.querySelector('[data-dropdown-toggle]');
                if (toggle.innerText.includes('Status')) {
                    console.log('--- Status Dropdown Clicked ---');
                    const linkCount = targetDropdown.querySelector('a[href*="ticketstatus"] span.bg-red-600');
                    console.log('Ticket Status Link Badge Value:', linkCount ? linkCount.innerText.trim() : 'N/A (Badge not rendered)');
                }

                exclusiveDropdowns.forEach(dropdown => {
                    if (dropdown !== targetDropdown) {
                        dropdown.querySelector('[data-dropdown-menu]').classList.remove('show');
                        dropdown.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
                        const otherArrow = dropdown.querySelector('[data-dropdown-arrow]');
                        if (otherArrow) otherArrow.style.transform = 'rotate(0deg)';
                    }
                });

                if (isOpen) {
                    menu.classList.remove('show');
                    targetDropdown.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                } else {
                    menu.classList.add('show');
                    targetDropdown.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'true');
                    if (arrow) arrow.style.transform = 'rotate(180deg)';
                }
            }

            exclusiveDropdowns.forEach(dropdown => {
                dropdown.querySelector('[data-dropdown-toggle]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleExclusiveDropdown(dropdown);
                });
            });

            document.addEventListener('click', (e) => {
                exclusiveDropdowns.forEach(dropdown => {
                    const menu = dropdown.querySelector('[data-dropdown-menu]');
                    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
                    if (menu.classList.contains('show') && !toggle.contains(e.target) && !menu.contains(e.target)) {
                        toggleExclusiveDropdown(dropdown);
                    }
                });
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    exclusiveDropdowns.forEach(dropdown => {
                        if (dropdown.querySelector('[data-dropdown-menu]').classList.contains('show')) {
                            toggleExclusiveDropdown(dropdown);
                        }
                    });
                }
            });

            hamburger.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.contains('open');
                mobileMenu.classList.toggle('open');

                const lines = hamburgerIcon.children;
                if (!isOpen) {
                    lines[0].style.transform = 'rotate(45deg) translate(6px, 6px)';
                    lines[1].style.opacity = '0';
                    lines[2].style.transform = 'rotate(-45deg) translate(6px, -6px)';
                    hamburger.setAttribute('aria-expanded', 'true');
                } else {
                    lines[0].style.transform = 'none';
                    lines[1].style.opacity = '1';
                    lines[2].style.transform = 'none';
                    hamburger.setAttribute('aria-expanded', 'false');
                }
            });

            if (mobileDropdown) {
                mobileDropdown.querySelector('[data-mobile-toggle]').addEventListener('click', () => {
                    const isOpen = mobileContent.classList.contains('open');
                    mobileContent.classList.toggle('open');
                    if (mobileArrow) {
                        mobileArrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                    }
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    if (mobileMenu.classList.contains('open')) {
                        mobileMenu.classList.remove('open');
                        hamburger.click();
                    }
                    exclusiveDropdowns.forEach(dropdown => {
                        if (dropdown.querySelector('[data-dropdown-menu]').classList.contains('show')) {
                            toggleExclusiveDropdown(dropdown);
                        }
                    });
                }
            });
        });
    </script>
</div>
