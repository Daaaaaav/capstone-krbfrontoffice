<div class="sidebar-root">
    {{-- Mobile Backdrop --}}
    <div x-show="mobileMenuOpen" x-transition.opacity class="sidebar-backdrop lg:hidden" @click="mobileMenuOpen = false" x-cloak></div>

    <aside class="sidebar-unified"
           :class="isMobile ? (mobileMenuOpen ? 'mobile-open' : 'mobile-closed') : (sidebarLocked ? 'desktop-locked' : (sidebarCollapsed ? 'desktop-collapsed' : 'desktop-hovered'))"
           @mouseenter="sidebarEnter()" @mouseleave="sidebarLeave()"
           x-cloak>
           
        <div class="sidebar-unified-inner">
            {{-- Header --}}
            <div class="sidebar-unified-header">
                <a href="{{ route('manager.dashboard') }}" class="sidebar-unified-logo" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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

            {{-- Nav Items --}}
            <nav class="sidebar-unified-nav">
                @php $homeActive = request()->routeIs('manager.dashboard'); @endphp
                <a href="{{ route('manager.dashboard') }}" class="sidebar-unified-item {{ $homeActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    @if($homeActive)<div class="active-pip"></div>@endif
                    <div class="item-icon"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <span class="item-label">{{ __('app.dashboard') }}</span>
                    <div class="tooltip">{{ __('app.dashboard') }}</div>
                </a>

                {{-- User Management --}}
                @php $userGroupActiveState = request()->routeIs('manager.receptionists'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_user') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_user', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.user_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        <a href="{{ route('manager.receptionists') }}" class="sidebar-unified-item {{ $userGroupActiveState ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($userGroupActiveState)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.receptionists') }}</span>
                            <div class="tooltip">{{ __('app.receptionists') }}</div>
                        </a>
                    </div>
                </div>

                {{-- Analytics --}}
                @php $analyticsGroupActive = request()->routeIs('manager.room', 'manager.vehicle', 'manager.delivery', 'manager.guestbook'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_analytics') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_analytics', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.analytics') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $roomActive = request()->routeIs('manager.room'); @endphp
                        <a href="{{ route('manager.room') }}" class="sidebar-unified-item {{ $roomActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($roomActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                                    <line x1="8" y1="7" x2="16" y2="7"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.room_bookings') }}</span>
                            <div class="tooltip">{{ __('app.room_bookings') }}</div>
                        </a>
                        
                        @php $vehActive = request()->routeIs('manager.vehicle'); @endphp
                        <a href="{{ route('manager.vehicle') }}" class="sidebar-unified-item {{ $vehActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($vehActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.vehicle_bookings') }}</span>
                            <div class="tooltip">{{ __('app.vehicle_bookings') }}</div>
                        </a>
                        
                        @php $delActive = request()->routeIs('manager.delivery'); @endphp
                        <a href="{{ route('manager.delivery') }}" class="sidebar-unified-item {{ $delActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($delActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.deliveries') }}</span>
                            <div class="tooltip">{{ __('app.deliveries') }}</div>
                        </a>
                        
                        @php $guestActive = request()->routeIs('manager.guestbook'); @endphp
                        <a href="{{ route('manager.guestbook') }}" class="sidebar-unified-item {{ $guestActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($guestActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.guestbook') }}</span>
                            <div class="tooltip">{{ __('app.guestbook') }}</div>
                        </a>
                    </div>
                </div>

                {{-- Priority Operations --}}
                @php $priorityGroupActive = request()->routeIs('manager.priority-room','manager.priority-vehicle','manager.guestbook-form','manager.docpack-form','manager.docpack-status'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_priority') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_priority', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">Priority &amp; Operations</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>

                        @php $prRoomActive = request()->routeIs('manager.priority-room'); @endphp
                        <a href="{{ route('manager.priority-room') }}" class="sidebar-unified-item {{ $prRoomActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($prRoomActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <path d="M10 21V11.5a1.5 1.5 0 013 0V21"/>
                                    <path d="M17 8l-5-5-5 5"/>
                                </svg>
                            </div>
                            <span class="item-label">Priority Room</span>
                            <div class="tooltip">Priority Room Booking</div>
                        </a>

                        @php $prVehActive = request()->routeIs('manager.priority-vehicle'); @endphp
                        <a href="{{ route('manager.priority-vehicle') }}" class="sidebar-unified-item {{ $prVehActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($prVehActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                    <path d="M17 8l-5-5-5 5"/>
                                </svg>
                            </div>
                            <span class="item-label">Priority Vehicle</span>
                            <div class="tooltip">Priority Vehicle Booking</div>
                        </a>

                        @php $gbFormActive = request()->routeIs('manager.guestbook-form'); @endphp
                        <a href="{{ route('manager.guestbook-form') }}" class="sidebar-unified-item {{ $gbFormActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($gbFormActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <line x1="19" y1="8" x2="19" y2="14"/>
                                    <line x1="22" y1="11" x2="16" y2="11"/>
                                </svg>
                            </div>
                            <span class="item-label">Schedule Visitor</span>
                            <div class="tooltip">Schedule Future Visitor</div>
                        </a>

                        @php $dpFormActive = request()->routeIs('manager.docpack-form'); @endphp
                        <a href="{{ route('manager.docpack-form') }}" class="sidebar-unified-item {{ $dpFormActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($dpFormActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            </div>
                            <span class="item-label">DocPack</span>
                            <div class="tooltip">Doc/Pack Form &amp; Status</div>
                        </a>

                    </div>
                </div>

                {{-- Resource Management --}}
                @php $resourceGroupActive = request()->routeIs('manager.manageroom', 'manager.managevehicle', 'manager.managestorage'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_resource') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_resource', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">Resource Management</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $manageRoomActive = request()->routeIs('manager.manageroom'); @endphp
                        <a href="{{ route('manager.manageroom') }}" class="sidebar-unified-item {{ $manageRoomActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($manageRoomActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                                </svg>
                            </div>
                            <span class="item-label">Manage Rooms</span>
                            <div class="tooltip">Manage Rooms</div>
                        </a>
                        
                        @php $manageVehActive = request()->routeIs('manager.managevehicle'); @endphp
                        <a href="{{ route('manager.managevehicle') }}" class="sidebar-unified-item {{ $manageVehActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($manageVehActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                                    <circle cx="7" cy="17" r="2"/>
                                    <circle cx="17" cy="17" r="2"/>
                                </svg>
                            </div>
                            <span class="item-label">Manage Vehicles</span>
                            <div class="tooltip">Manage Vehicles</div>
                        </a>
                        
                        @php $manageStorActive = request()->routeIs('manager.managestorage'); @endphp
                        <a href="{{ route('manager.managestorage') }}" class="sidebar-unified-item {{ $manageStorActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($manageStorActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                                    <line x1="12" y1="12" x2="12" y2="16"/>
                                    <line x1="10" y1="14" x2="14" y2="14"/>
                                </svg>
                            </div>
                            <span class="item-label">Manage Storages</span>
                            <div class="tooltip">Manage Storages</div>
                        </a>
                    </div>
                </div>

                {{-- AI Security --}}
                @php $aiGroupActive = request()->routeIs('manager.lstm-predictions', 'manager.occupancy', 'manager.ai-security'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_ai') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_ai', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{!! __('app.ai_security') !!}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $lstmActive = request()->routeIs('manager.lstm-predictions'); @endphp
                        <a href="{{ route('manager.lstm-predictions') }}" class="sidebar-unified-item {{ $lstmActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($lstmActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="4" width="16" height="16" rx="2"/>
                                    <rect x="9" y="9" width="6" height="6"/>
                                    <line x1="9" y1="1" x2="9" y2="4"/>
                                    <line x1="15" y1="1" x2="15" y2="4"/>
                                    <line x1="9" y1="20" x2="9" y2="23"/>
                                    <line x1="15" y1="20" x2="15" y2="23"/>
                                    <line x1="20" y1="9" x2="23" y2="9"/>
                                    <line x1="20" y1="15" x2="23" y2="15"/>
                                    <line x1="1" y1="9" x2="4" y2="9"/>
                                    <line x1="1" y1="15" x2="4" y2="15"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.visitor_predictions') }}</span>
                            <div class="tooltip">{{ __('app.visitor_predictions') }}</div>
                        </a>
                        
                        @php $occActive = request()->routeIs('manager.occupancy'); @endphp
                        <a href="{{ route('manager.occupancy') }}" class="sidebar-unified-item {{ $occActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($occActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"/>
                                    <path d="m19 9-5 5-4-4-3 3"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.occupancy_forecast') }}</span>
                            <div class="tooltip">{{ __('app.occupancy_forecast') }}</div>
                        </a>
                        
                        @php $secActive = request()->routeIs('manager.ai-security'); @endphp
                        <a href="{{ route('manager.ai-security') }}" class="sidebar-unified-item {{ $secActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($secActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    <path d="m9 11 2 2 4-4"/>
                                </svg>
                            </div>
                            <span class="item-label">{{ __('app.security_reports') }}</span>
                            <div class="tooltip">{{ __('app.security_reports') }}</div>
                        </a>
                    </div>
                </div>
            </nav>

            <div class="sidebar-unified-footer">
                @php $setActive = request()->routeIs('manager.settings'); @endphp
                <a href="{{ route('manager.settings') }}" class="sidebar-unified-item {{ $setActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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

                @php $helpActive = request()->routeIs('manager.help'); @endphp
                <a href="{{ route('manager.help') }}" class="sidebar-unified-item {{ $helpActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                                <p class="user-role">Manager</p>
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