<?php

$sidebarPath = __DIR__ . '/resources/views/livewire/components/partials/receptionist/sidebar.blade.php';
$cssPath = __DIR__ . '/resources/css/app.css';
$layoutPath = __DIR__ . '/resources/views/layouts/receptionist.blade.php';

// 1. Update Layout
$layout = file_get_contents($layoutPath);
$layout = str_replace(
    ":style=\"isMobile ? 'padding-left: 0;' : (sidebarLocked ? 'padding-left: 344px;' : 'padding-left: 64px;')\"",
    ":style=\"isMobile ? 'padding-left: 0;' : (sidebarLocked ? 'padding-left: 280px;' : 'padding-left: 64px;')\"",
    $layout
);
file_put_contents($layoutPath, $layout);

// 2. Generate new Sidebar HTML
$newSidebar = <<<'HTML'
<div class="sidebar-root" x-data="{ search: '' }">
    {{-- Mobile Backdrop --}}
    <div x-show="mobileMenuOpen" x-transition.opacity class="sidebar-backdrop lg:hidden" @click="mobileMenuOpen = false" x-cloak></div>

    <aside class="sidebar-unified"
           :class="isMobile ? (mobileMenuOpen ? 'mobile-open' : 'mobile-closed') : (sidebarLocked ? 'desktop-locked' : (sidebarCollapsed ? 'desktop-collapsed' : 'desktop-hovered'))"
           @mouseenter="sidebarEnter()" @mouseleave="sidebarLeave()"
           x-cloak>
           
        <div class="sidebar-unified-inner">
            {{-- Header --}}
            <div class="sidebar-unified-header">
                <a href="{{ route('receptionist.dashboard') }}" class="sidebar-unified-logo" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    <div class="logo-icon-wrapper">
                        <img src="{{ $brandLogo }}" alt="Logo" class="sidebar-logo-img" />
                    </div>
                    <span class="logo-text" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'opacity-100' : 'opacity-0 hidden'">{{ $brandName }}</span>
                </a>
                
                <div class="sidebar-unified-actions" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'opacity-100' : 'opacity-0 hidden'">
                    <button @click.stop="sidebarLocked = !sidebarLocked" class="sidebar-pin-btn max-lg:hidden" :class="sidebarLocked ? 'pinned' : ''" title="Toggle Pin">
                        <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarLocked ? 'rotate-0' : '-rotate-45'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 17v5"/>
                            <path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>
                        </svg>
                    </button>
                    <button @click="mobileMenuOpen = false" class="sidebar-close-btn lg:hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            {{-- Search --}}
            <div class="sidebar-unified-search" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                <div class="relative w-full">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="{{ __('app.search_modules') }}" class="sidebar-search-input w-full" tabindex="-1" />
                </div>
            </div>

            {{-- Nav Items --}}
            <nav class="sidebar-unified-nav">
                @php $homeActive = request()->routeIs('receptionist.dashboard'); @endphp
                <a href="{{ route('receptionist.dashboard') }}" class="sidebar-unified-item {{ $homeActive ? 'active' : '' }}" x-show="!search || 'home'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    @if($homeActive)<div class="active-pip"></div>@endif
                    <div class="item-icon"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <span class="item-label">{{ __('app.home') }}</span>
                    <div class="tooltip">{{ __('app.home') }}</div>
                </a>

                {{-- Rooms --}}
                @php $roomActiveGroup = request()->routeIs('receptionist.schedule', 'receptionist.bookings', 'receptionist.bookinghistory'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: {{ $roomActiveGroup ? 'true' : 'false' }} }" x-show="!search || ['room management','booking room','room book approval','booking history'].some(s => s.includes(search.toLowerCase()))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.room_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $schedActive = request()->routeIs('receptionist.schedule'); @endphp
                        <a href="{{ route('receptionist.schedule') }}" class="sidebar-unified-item {{ $schedActive ? 'active' : '' }}" x-show="!search || 'booking room'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($schedActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.booking_room') }}</span>
                            <div class="tooltip">{{ __('app.booking_room') }}</div>
                        </a>
                        
                        @php $bookActive = request()->routeIs('receptionist.bookings'); @endphp
                        <a href="{{ route('receptionist.bookings') }}" class="sidebar-unified-item {{ $bookActive ? 'active' : '' }}" x-show="!search || 'room book approval'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($bookActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.room_book_approval') }}</span>
                            <div class="tooltip">{{ __('app.room_book_approval') }}</div>
                        </a>
                        
                        @php $histActive = request()->routeIs('receptionist.bookinghistory'); @endphp
                        <a href="{{ route('receptionist.bookinghistory') }}" class="sidebar-unified-item {{ $histActive ? 'active' : '' }}" x-show="!search || 'booking history'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($histActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                    <polyline points="3 3 3 8 8 8"/>
                                    <line x1="12" y1="7" x2="12" y2="12"/>
                                    <line x1="12" y1="12" x2="16" y2="14"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.booking_history') }}</span>
                            <div class="tooltip">{{ __('app.booking_history') }}</div>
                        </a>
                    </div>
                </div>

                {{-- Vehicles --}}
                @php $vehActiveGroup = request()->routeIs('receptionist.bookingvehicle', 'receptionist.vehiclestatus', 'receptionist.vehicleshistory'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: {{ $vehActiveGroup ? 'true' : 'false' }} }" x-show="!search || ['vehicle management','vehicle book','vehicle status','vehicle history'].some(s => s.includes(search.toLowerCase()))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.vehicle_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $vbookActive = request()->routeIs('receptionist.bookingvehicle'); @endphp
                        <a href="{{ route('receptionist.bookingvehicle') }}" class="sidebar-unified-item {{ $vbookActive ? 'active' : '' }}" x-show="!search || 'vehicle book'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($vbookActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.vehicle_book') }}</span>
                            <div class="tooltip">{{ __('app.vehicle_book') }}</div>
                        </a>
                        
                        @php $vstatActive = request()->routeIs('receptionist.vehiclestatus'); @endphp
                        <a href="{{ route('receptionist.vehiclestatus') }}" class="sidebar-unified-item {{ $vstatActive ? 'active' : '' }}" x-show="!search || 'vehicle status'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($vstatActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.vehicle_status_menu') }}</span>
                            <div class="tooltip">{{ __('app.vehicle_status_menu') }}</div>
                        </a>
                        
                        @php $vhistActive = request()->routeIs('receptionist.vehicleshistory'); @endphp
                        <a href="{{ route('receptionist.vehicleshistory') }}" class="sidebar-unified-item {{ $vhistActive ? 'active' : '' }}" x-show="!search || 'vehicle history'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($vhistActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 6c-2 0-3.5 1.5-3.5 3.5H7l2.5 3 2.5-3h-1.5c0-1 1-2 2-2s2 1 2 2-1 2-2 2v1.5c2 0 3.5-1.5 3.5-3.5S14 6 12 6z"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.vehicle_history') }}</span>
                            <div class="tooltip">{{ __('app.vehicle_history') }}</div>
                        </a>
                    </div>
                </div>

                {{-- Guests --}}
                @php $guestActiveGroup = request()->routeIs('receptionist.guestbook', 'receptionist.guestbookstatus', 'receptionist.guestbookhistory'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: {{ $guestActiveGroup ? 'true' : 'false' }} }" x-show="!search || ['guest management','guestbook','guestbook status','guestbook history'].some(s => s.includes(search.toLowerCase()))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.guest_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $gbookActive = request()->routeIs('receptionist.guestbook'); @endphp
                        <a href="{{ route('receptionist.guestbook') }}" class="sidebar-unified-item {{ $gbookActive ? 'active' : '' }}" x-show="!search || 'guestbook'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($gbookActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.guestbook') }}</span>
                            <div class="tooltip">{{ __('app.guestbook') }}</div>
                        </a>
                        
                        @php $gstatActive = request()->routeIs('receptionist.guestbookstatus'); @endphp
                        <a href="{{ route('receptionist.guestbookstatus') }}" class="sidebar-unified-item {{ $gstatActive ? 'active' : '' }}" x-show="!search || 'guestbook status'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($gstatActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <path d="M14 14h3v3m0 4v-4h4"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.guestbook_status') }}</span>
                            <div class="tooltip">{{ __('app.guestbook_status') }}</div>
                        </a>
                        
                        @php $ghistActive = request()->routeIs('receptionist.guestbookhistory'); @endphp
                        <a href="{{ route('receptionist.guestbookhistory') }}" class="sidebar-unified-item {{ $ghistActive ? 'active' : '' }}" x-show="!search || 'guestbook history'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($ghistActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                    <path d="M12 6v4l2.5 2.5"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.guestbook_history') }}</span>
                            <div class="tooltip">{{ __('app.guestbook_history') }}</div>
                        </a>
                    </div>
                </div>

                {{-- Docpacks --}}
                @php $docActiveGroup = request()->routeIs('receptionist.docpackform', 'receptionist.docpackstatus', 'receptionist.docpackhistory'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: {{ $docActiveGroup ? 'true' : 'false' }} }" x-show="!search || ['docpac management','docpac form','docpac status','docpac history'].some(s => s.includes(search.toLowerCase()))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.docpac_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $dformActive = request()->routeIs('receptionist.docpackform'); @endphp
                        <a href="{{ route('receptionist.docpackform') }}" class="sidebar-unified-item {{ $dformActive ? 'active' : '' }}" x-show="!search || 'docpac form'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($dformActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.docpac_form') }}</span>
                            <div class="tooltip">{{ __('app.docpac_form') }}</div>
                        </a>
                        
                        @php $dstatActive = request()->routeIs('receptionist.docpackstatus'); @endphp
                        <a href="{{ route('receptionist.docpackstatus') }}" class="sidebar-unified-item {{ $dstatActive ? 'active' : '' }}" x-show="!search || 'docpac status'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($dstatActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                    <circle cx="12" cy="7" r="2.5"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.docpac_status') }}</span>
                            <div class="tooltip">{{ __('app.docpac_status') }}</div>
                        </a>
                        
                        @php $dhistActive = request()->routeIs('receptionist.docpackhistory'); @endphp
                        <a href="{{ route('receptionist.docpackhistory') }}" class="sidebar-unified-item {{ $dhistActive ? 'active' : '' }}" x-show="!search || 'docpac history'.includes(search.toLowerCase())" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($dhistActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.docpac_history') }}</span>
                            <div class="tooltip">{{ __('app.docpac_history') }}</div>
                        </a>
                    </div>
                </div>
            </nav>

            <div class="sidebar-unified-footer">
                @php $setActive = request()->routeIs('receptionist.settings'); @endphp
                <a href="{{ route('receptionist.settings') }}" class="sidebar-unified-item {{ $setActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    @if($setActive)<div class="active-pip"></div>@endif
                    <div class="item-icon">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                    </div>
                    <span class="item-label">{{ __('app.settings') }}</span>
                    <div class="tooltip">{{ __('app.settings') }}</div>
                </a>

                @php $helpActive = request()->routeIs('receptionist.help'); @endphp
                <a href="{{ route('receptionist.help') }}" class="sidebar-unified-item {{ $helpActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    @if($helpActive)<div class="active-pip"></div>@endif
                    <div class="item-icon">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <span class="item-label">{{ __('app.help') }}</span>
                    <div class="tooltip">{{ __('app.help') }}</div>
                </a>
                
                <div class="sidebar-unified-user border-t border-white/10 mt-1 pt-2">
                    <div x-data="{ open: false }" class="relative w-full">
                        <button @click.stop="open = !open" class="sidebar-unified-user-card" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            <div class="user-avatar">
                                {{ strtoupper(substr($fullName ?? 'U', 0, 1)) }}
                            </div>
                            <div class="user-info">
                                <p class="user-name">{{ $fullName ?? 'User' }}</p>
                                <p class="user-role">Receptionist</p>
                            </div>
                            <svg class="user-chevron w-4 h-4 text-white/40 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                            <div class="tooltip">{{ $fullName ?? 'User' }}</div>
                        </button>
                        
                        <div x-show="open" @click.outside="open = false" class="sidebar-profile-popover" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''" style="display:none;" x-cloak>
                            <button type="submit" form="logout-form" class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                {{ __('app.logout') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>
HTML;
file_put_contents($sidebarPath, $newSidebar);

// 3. Update CSS
$css = file_get_contents($cssPath);
// Remove everything after /* ==========================================================================
$pos = strpos($css, '/* ==========================================================================');
if ($pos !== false) {
    $css = substr($css, 0, $pos);
}

$newCss = <<<'CSS'
/* ==========================================================================
   UNIFIED EXPANDABLE SIDEBAR
   ========================================================================== */

.sidebar-root {
    display: contents;
}

.sidebar-logo-img {
    filter: brightness(0) invert(1);
    transition: transform 0.3s;
}
.sidebar-logo-img:hover {
    transform: rotate(12deg);
}

.sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 45;
    -webkit-backdrop-filter: blur(2px);
    backdrop-filter: blur(2px);
}

.sidebar-unified {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    background: #4E653D;
    border-right: 1px solid #354C2B;
    z-index: 50;
    display: flex;
    flex-direction: column;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    box-shadow: 4px 0 24px rgba(0,0,0,0.1);
}

/* States */
.sidebar-unified.desktop-collapsed {
    width: 64px;
}
.sidebar-unified.desktop-hovered {
    width: 280px;
}
.sidebar-unified.desktop-locked {
    width: 280px;
}

@media (max-width: 1023px) {
    .sidebar-unified.mobile-closed {
        width: 280px;
        transform: translateX(-100%);
    }
    .sidebar-unified.mobile-open {
        width: 280px;
        transform: translateX(0);
    }
}

.sidebar-unified-inner {
    width: 280px; /* Force inner width to prevent text wrap during animation */
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Header */
.sidebar-unified-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    height: 64px; /* fixed height for header */
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}

.sidebar-unified-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    padding: 0.25rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    width: 40px; /* Collapsed state space */
    overflow: hidden;
}
.sidebar-unified-logo.expanded {
    width: 100%;
}
.logo-icon-wrapper {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.logo-text {
    font-weight: 700;
    color: white;
    white-space: nowrap;
    transition: opacity 0.3s, width 0.3s;
}

.sidebar-unified-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    transition: opacity 0.3s;
}
.sidebar-pin-btn, .sidebar-close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.sidebar-pin-btn:hover, .sidebar-close-btn:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}
.sidebar-pin-btn.pinned {
    color: #CDDEA7;
    background: rgba(205, 222, 167, 0.15);
}

/* Search */
.sidebar-unified-search {
    padding: 0.75rem;
    flex-shrink: 0;
    transition: opacity 0.3s;
}
.sidebar-search-input {
    height: 2.25rem;
    padding-left: 2.25rem;
    padding-right: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 0.8125rem;
    outline: none;
    transition: all 0.2s;
}
.sidebar-search-input:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(205, 222, 167, 0.3);
}
.sidebar-search-input::placeholder {
    color: rgba(255,255,255,0.4);
}

/* Navigation */
.sidebar-unified-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
.sidebar-unified-nav::-webkit-scrollbar {
    width: 4px;
}
.sidebar-unified-nav::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
}

/* Items */
.sidebar-unified-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    height: 2.75rem;
    padding: 0 0.5rem;
    border-radius: 0.625rem;
    color: #D7C9B8;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    width: 48px; /* Collapsed width */
}
.sidebar-unified-item.expanded {
    width: 100%;
}
.sidebar-unified-item:hover {
    color: white;
    background: rgba(255,255,255,0.08);
}
.sidebar-unified-item.active {
    color: #CDDEA7;
    background: #4A2F24;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(53, 76, 43, 0.4);
}
.item-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.sidebar-unified-item:hover .item-icon {
    transform: scale(1.05);
}
.item-label {
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    font-size: 0.8125rem;
}
.sidebar-unified-item.expanded .item-label {
    opacity: 1;
    pointer-events: auto;
}
.active-pip {
    position: absolute;
    left: -0.2rem;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 16px;
    background: #CDDEA7;
    border-radius: 4px;
    box-shadow: 0 0 8px rgba(205,222,167,0.6);
}

/* Tooltip (only shows when collapsed) */
.tooltip {
    position: absolute;
    left: 4.5rem;
    top: 50%;
    transform: translateY(-50%) translateX(-8px);
    background: #4A2F24;
    color: #CDDEA7;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    border: 1px solid #354C2B;
    transition: all 0.2s;
}
.sidebar-unified.desktop-collapsed .sidebar-unified-item:hover .tooltip,
.sidebar-unified.desktop-collapsed .sidebar-unified-user-card:hover .tooltip {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}

/* Groups */
.sidebar-unified-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 0.25rem;
}
.sidebar-unified-group + .sidebar-unified-group {
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 0.5rem;
    margin-top: 0.25rem;
}
.group-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 48px;
    padding: 0.375rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: transparent;
    border: none;
    cursor: pointer;
    border-radius: 0.375rem;
    transition: all 0.2s;
    overflow: hidden;
}
.group-heading.expanded {
    width: 100%;
}
.group-heading:hover {
    color: rgba(255,255,255,0.7);
    background: rgba(255,255,255,0.04);
}
.group-label {
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s;
}
.group-heading.expanded .group-label {
    opacity: 1;
}
.group-chevron {
    width: 1rem;
    height: 1rem;
    opacity: 0;
    transition: opacity 0.3s, transform 0.2s;
    flex-shrink: 0;
}
.group-heading.expanded .group-chevron {
    opacity: 1;
}
.group-items {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    margin-top: 0.125rem;
}

/* Footer & User Card */
.sidebar-unified-footer {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex-shrink: 0;
}
.sidebar-unified-user-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    height: 3rem;
    padding: 0 0.5rem;
    border-radius: 0.75rem;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    width: 48px;
    text-align: left;
}
.sidebar-unified-user-card.expanded {
    width: 100%;
}
.sidebar-unified-user-card:hover {
    background: rgba(255,255,255,0.08);
}
.user-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    color: white;
    font-weight: 700;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.user-info {
    opacity: 0;
    white-space: nowrap;
    transition: opacity 0.3s;
    min-width: 0;
    flex: 1;
}
.sidebar-unified-user-card.expanded .user-info {
    opacity: 1;
}
.user-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: white;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-role {
    font-size: 0.6875rem;
    color: rgba(255,255,255,0.4);
}
.user-chevron {
    opacity: 0;
    transition: opacity 0.3s;
}
.sidebar-unified-user-card.expanded .user-chevron {
    opacity: 1;
}

.sidebar-profile-popover {
    position: absolute;
    bottom: 100%;
    left: 0;
    margin-bottom: 0.5rem;
    width: 200px;
    background: #2a1f1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    z-index: 100;
}
.sidebar-profile-popover:not(.expanded) {
    left: 4rem; /* Place next to avatar if collapsed */
    bottom: 0;
    margin-bottom: 0;
}
CSS;
file_put_contents($cssPath, $css . "\n" . $newCss);

echo "Done\n";
