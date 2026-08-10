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
                <a href="{{ route('it-officer.dashboard') }}" class="sidebar-unified-logo" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                @php $homeActive = request()->routeIs('it-officer.dashboard'); @endphp
                <a href="{{ route('it-officer.dashboard') }}" class="sidebar-unified-item {{ $homeActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                    @if($homeActive)<div class="active-pip"></div>@endif
                    <div class="item-icon"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <span class="item-label">{{ __('app.dashboard') }}</span>
                    <div class="tooltip">{{ __('app.dashboard') }}</div>
                </a>

                {{-- User Management --}}
                @php $userGroupActiveState = request()->routeIs('it-officer.receptionists', 'it-officer.managers', 'it-officer.users-per-department'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_user') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_user', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{{ __('app.user_management') }}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $recepActive = request()->routeIs('it-officer.receptionists'); @endphp
                        <a href="{{ route('it-officer.receptionists') }}" class="sidebar-unified-item {{ $recepActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($recepActive)<div class="active-pip"></div>@endif
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

                        @php $managerActive = request()->routeIs('it-officer.managers'); @endphp
                        <a href="{{ route('it-officer.managers') }}" class="sidebar-unified-item {{ $managerActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($managerActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <span class="item-label">Managers</span>
                            <div class="tooltip">Manager Users</div>
                        </a>

                        @php $perDeptActive = request()->routeIs('it-officer.users-per-department'); @endphp
                        <a href="{{ route('it-officer.users-per-department') }}" class="sidebar-unified-item {{ $perDeptActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($perDeptActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    <path d="M3 9h18M3 15h12"/>
                                </svg>
                            </div>
                            <span class="item-label">Users per Department</span>
                            <div class="tooltip">Manage Users per Department</div>
                        </a>
                    </div>
                </div>

                {{-- Resource Management --}}
                @php $resourceGroupActive = request()->routeIs('it-officer.manageroom', 'it-officer.managevehicle', 'it-officer.managestorage', 'it-officer.id-types', 'it-officer.visitor-lanyards', 'it-officer.requirements'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_resource') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_resource', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">Resource Management</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $manageRoomActive = request()->routeIs('it-officer.manageroom'); @endphp
                        <a href="{{ route('it-officer.manageroom') }}" class="sidebar-unified-item {{ $manageRoomActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                        
                        @php $manageVehActive = request()->routeIs('it-officer.managevehicle'); @endphp
                        <a href="{{ route('it-officer.managevehicle') }}" class="sidebar-unified-item {{ $manageVehActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                        
                        @php $manageStorActive = request()->routeIs('it-officer.managestorage'); @endphp
                        <a href="{{ route('it-officer.managestorage') }}" class="sidebar-unified-item {{ $manageStorActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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

                        @php $idTypesActive = request()->routeIs('it-officer.id-types'); @endphp
                        <a href="{{ route('it-officer.id-types') }}" class="sidebar-unified-item {{ $idTypesActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($idTypesActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                            </div>
                            <span class="item-label">ID Types</span>
                            <div class="tooltip">Manage ID Types</div>
                        </a>

                        @php $lanyardsActive = request()->routeIs('it-officer.visitor-lanyards'); @endphp
                        <a href="{{ route('it-officer.visitor-lanyards') }}" class="sidebar-unified-item {{ $lanyardsActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($lanyardsActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <span class="item-label">Visitor Lanyards</span>
                            <div class="tooltip">Manage Visitor Lanyards</div>
                        </a>

                        @php $requirementsActive = request()->routeIs('it-officer.requirements'); @endphp
                        <a href="{{ route('it-officer.requirements') }}" class="sidebar-unified-item {{ $requirementsActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                            @if($requirementsActive)<div class="active-pip"></div>@endif
                            <div class="item-icon">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <span class="item-label">Room Requirements</span>
                            <div class="tooltip">Manage Room Booking Requirements</div>
                        </a>
                    </div>
                </div>

                {{-- AI Security --}}
                @php $aiGroupActive = request()->routeIs('it-officer.lstm-predictions', 'it-officer.occupancy', 'it-officer.ai-security'); @endphp
                <div class="sidebar-unified-group" x-data="{ expanded: JSON.parse(localStorage.getItem('sg_ai') ?? 'true') }" x-init="$watch('expanded', v => localStorage.setItem('sg_ai', v))">
                    <button @click="expanded = !expanded" class="group-heading" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
                        <span class="group-label">{!! __('app.ai_security') !!}</span>
                        <svg class="group-chevron transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="expanded || (!(!sidebarCollapsed || sidebarLocked || isMobile))" class="group-items" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'pl-2' : ''" x-collapse>
                        @php $lstmActive = request()->routeIs('it-officer.lstm-predictions'); @endphp
                        <a href="{{ route('it-officer.lstm-predictions') }}" class="sidebar-unified-item {{ $lstmActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                        
                        @php $occActive = request()->routeIs('it-officer.occupancy'); @endphp
                        <a href="{{ route('it-officer.occupancy') }}" class="sidebar-unified-item {{ $occActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                        
                        @php $secActive = request()->routeIs('it-officer.ai-security'); @endphp
                        <a href="{{ route('it-officer.ai-security') }}" class="sidebar-unified-item {{ $secActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                @php $setActive = request()->routeIs('it-officer.settings'); @endphp
                <a href="{{ route('it-officer.settings') }}" class="sidebar-unified-item {{ $setActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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

                @php $helpActive = request()->routeIs('it-officer.help'); @endphp
                <a href="{{ route('it-officer.help') }}" class="sidebar-unified-item {{ $helpActive ? 'active' : '' }}" :class="(!sidebarCollapsed || sidebarLocked || isMobile) ? 'expanded' : ''">
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
                                {{ strtoupper($initials) }}
                            </div>
                            <div class="user-info">
                                <p class="user-name">{{ $fullName }}</p>
                                <p class="user-role">IT Officer</p>
                            </div>
                            <svg class="user-chevron w-4 h-4 text-white/40 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                            <div class="tooltip">{{ $fullName }}</div>
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
