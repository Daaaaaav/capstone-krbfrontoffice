<div class="min-h-screen bg-background" wire:poll.30s="pollRefresh">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        <x-page-header title="{{ __('app.dashboard') }}" subtitle="{{ __('app.it_officer_system_management') }}">
            <x-slot:actions>
                <button wire:click="refreshHealth" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md border border-border hover:bg-accent transition-colors disabled:opacity-50">
                    <svg wire:loading.class="animate-spin" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                        <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                        <path d="M16 21h5v-5"/>
                    </svg>
                    <span>{{ __('app.refresh') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Application Health Monitoring Section --}}
        <section class="bg-card border border-border rounded-xl p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-card-foreground flex items-center gap-2">
                            {{ __('app.application_health') }}
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $applicationHealth['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                                @if(($applicationHealth['status'] ?? '') === 'healthy')
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                @elseif(($applicationHealth['status'] ?? '') === 'degraded')
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5 animate-pulse"></span>
                                @endif
                                {{ $applicationHealth['status_label'] ?? __('app.unknown') }}
                            </span>
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.application_health_desc') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs text-muted-foreground self-start sm:self-auto">
                    <span>{{ __('app.last_checked') }}:</span>
                    <span class="font-mono font-medium text-card-foreground">
                        {{ !empty($applicationHealth['last_checked']) ? \Carbon\Carbon::parse($applicationHealth['last_checked'])->format('H:i:s') : '-' }}
                    </span>
                </div>
            </div>

            {{-- 3 Endpoint Health Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- 1. KRB Laravel Application --}}
                @php $laravelSrv = $applicationHealth['services']['krb_laravel_local'] ?? null; @endphp
                <div class="bg-background border {{ ($laravelSrv['is_healthy'] ?? false) ? 'border-emerald-500/30' : 'border-rose-500/40' }} rounded-xl p-4 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('app.local_service') }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $laravelSrv['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ ($laravelSrv['is_healthy'] ?? false) ? 'bg-emerald-500' : 'bg-rose-500' }} mr-1.5"></span>
                            {{ $laravelSrv['status_label'] ?? __('app.unknown') }}
                        </span>
                    </div>
                    <h3 class="text-sm font-bold text-card-foreground">{{ $laravelSrv['name'] ?? 'KRB Laravel' }}</h3>
                    <p class="text-xs font-mono text-muted-foreground/80 truncate mt-0.5" title="{{ $laravelSrv['url'] ?? '' }}">
                        {{ $laravelSrv['url'] ?? 'http://127.0.0.1:8000/health' }}
                    </p>
                    <div class="mt-3 pt-3 border-t border-border flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">{{ __('app.latency') }}</span>
                        <span class="font-mono font-bold {{ ($laravelSrv['is_healthy'] ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600' }}">
                            {{ isset($laravelSrv['response_time_ms']) ? $laravelSrv['response_time_ms'] . ' ms' : '-' }}
                        </span>
                    </div>
                </div>

                {{-- 2. LSTM FastAPI Service --}}
                @php $lstmSrv = $applicationHealth['services']['lstm_local'] ?? null; @endphp
                <div class="bg-background border {{ ($lstmSrv['is_healthy'] ?? false) ? 'border-emerald-500/30' : 'border-rose-500/40' }} rounded-xl p-4 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('app.local_service') }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $lstmSrv['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ ($lstmSrv['is_healthy'] ?? false) ? 'bg-emerald-500' : 'bg-rose-500' }} mr-1.5"></span>
                            {{ $lstmSrv['status_label'] ?? __('app.unknown') }}
                        </span>
                    </div>
                    <h3 class="text-sm font-bold text-card-foreground">{{ $lstmSrv['name'] ?? 'LSTM Forecast Service' }}</h3>
                    <p class="text-xs font-mono text-muted-foreground/80 truncate mt-0.5" title="{{ $lstmSrv['url'] ?? '' }}">
                        {{ $lstmSrv['url'] ?? 'http://127.0.0.1:8001/' }}
                    </p>
                    <div class="mt-3 pt-3 border-t border-border flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">{{ __('app.latency') }}</span>
                        <span class="font-mono font-bold {{ ($lstmSrv['is_healthy'] ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600' }}">
                            {{ isset($lstmSrv['response_time_ms']) ? $lstmSrv['response_time_ms'] . ' ms' : '-' }}
                        </span>
                    </div>
                </div>

                {{-- 3. KRB Public Endpoint --}}
                @php $publicSrv = $applicationHealth['services']['krb_public'] ?? null; @endphp
                <div class="bg-background border {{ ($publicSrv['is_healthy'] ?? false) ? 'border-emerald-500/30' : 'border-rose-500/40' }} rounded-xl p-4 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('app.public_service') }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $publicSrv['status_badge'] ?? 'bg-gray-100 text-gray-800' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ ($publicSrv['is_healthy'] ?? false) ? 'bg-emerald-500' : 'bg-rose-500' }} mr-1.5"></span>
                            {{ $publicSrv['status_label'] ?? __('app.unknown') }}
                        </span>
                    </div>
                    <h3 class="text-sm font-bold text-card-foreground">{{ $publicSrv['name'] ?? 'KRB Public Endpoint' }}</h3>
                    <p class="text-xs font-mono text-muted-foreground/80 truncate mt-0.5" title="{{ $publicSrv['url'] ?? '' }}">
                        {{ $publicSrv['url'] ?? 'https://receptionistkebunraya.online/health' }}
                    </p>
                    <div class="mt-3 pt-3 border-t border-border flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">{{ __('app.latency') }}</span>
                        <span class="font-mono font-bold {{ ($publicSrv['is_healthy'] ?? false) ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600' }}">
                            {{ isset($publicSrv['response_time_ms']) ? $publicSrv['response_time_ms'] . ' ms' : '-' }}
                        </span>
                    </div>
                </div>

            </div>

            <div class="mt-4 pt-3 border-t border-border flex items-center justify-between text-[11px] text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-muted-foreground/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Endpoint availability is monitored independently from systemd service state (Wazuh Rule 40704).
                </span>
                <span class="font-medium">Livewire Auto-Poll: 30s</span>
            </div>
        </section>

        {{-- Statistics Cards --}}
        <section class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Receptionists Card --}}
            <button wire:navigate href="{{ route('it-officer.receptionists') }}"
                class="group text-left bg-card border border-[#4E653D]/40 rounded-xl p-5 hover:border-[#4E653D] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4E653D]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4E653D]">{{ $stats['receptionists'] }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a4d2e]">{{ __('app.receptionists') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4E653D] transition-colors">{{ __('app.click_to_manage') }} →</p>
            </button>

            {{-- Managers Card --}}
            <button wire:navigate href="{{ route('it-officer.managers') }}"
                class="group text-left bg-card border border-[#4A2F24]/40 rounded-xl p-5 hover:border-[#4A2F24] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4A2F24]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4A2F24]">{{ $stats['managers'] }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a241c]">{{ __('app.managers') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4A2F24] transition-colors">{{ __('app.click_to_manage') }} →</p>
            </button>

            {{-- Rooms Card --}}
            <button wire:navigate href="{{ route('it-officer.manageroom') }}"
                class="group text-left bg-card border border-[#4E653D]/40 rounded-xl p-5 hover:border-[#4E653D] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4E653D]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4E653D]">{{ $stats['rooms'] }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a4d2e]">{{ __('app.rooms') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4E653D] transition-colors">{{ __('app.click_to_manage') }} →</p>
            </button>

            {{-- Vehicles Card --}}
            <button wire:navigate href="{{ route('it-officer.managevehicle') }}"
                class="group text-left bg-card border border-[#4A2F24]/40 rounded-xl p-5 hover:border-[#4A2F24] hover:shadow-md transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4A2F24]">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-[#4A2F24]">{{ $stats['vehicles'] }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-[#3a241c]">{{ __('app.vehicles') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-[#4A2F24] transition-colors">{{ __('app.click_to_manage') }} →</p>
            </button>

            {{-- Storages Card --}}
            <button wire:navigate href="{{ route('it-officer.managestorage') }}"
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
                    <span class="text-2xl font-bold text-amber-700">{{ $stats['storages'] }}</span>
                </div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-800">{{ __('app.storages') }}</p>
                <p class="text-[11px] text-muted-foreground/60 mt-3 group-hover:text-amber-600 transition-colors">{{ __('app.click_to_manage') }} →</p>
            </button>
        </section>

        {{-- Quick Actions --}}
        <div class="bg-card border border-border rounded-xl p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-card-foreground">{{ __('app.quick_actions') }}</h2>
                <p class="text-xs text-muted-foreground mt-1">{{ __('app.manage_system_users_resources') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                {{-- ── GREEN GROUP: Receptionists & Managers ─────────────────────────── --}}

                {{-- Receptionists --}}
                <a href="{{ route('it-officer.receptionists') }}"
                   class="flex items-center gap-4 p-5 bg-green-50 border-2 border-green-100 hover:border-green-300 hover:bg-green-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
                        <svg class="w-7 h-7 text-green-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="22" y1="11" x2="16" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_receptionists') }}</p>
                        <p class="text-xs text-green-700/70 mt-0.5">{{ __('app.add_edit_receptionist_users') }}</p>
                    </div>
                </a>

                {{-- Managers --}}
                <a href="{{ route('it-officer.managers') }}"
                   class="flex items-center gap-4 p-5 bg-green-50 border-2 border-green-100 hover:border-green-300 hover:bg-green-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
                        <svg class="w-7 h-7 text-green-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="22" y1="11" x2="16" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_managers') }}</p>
                        <p class="text-xs text-green-700/70 mt-0.5">{{ __('app.add_edit_manager_users') }}</p>
                    </div>
                </a>

                {{-- ── ORANGE GROUP: Rooms & Room Requirements ───────────────────────── --}}

                {{-- Rooms --}}
                <a href="{{ route('it-officer.manageroom') }}"
                   class="flex items-center gap-4 p-5 bg-orange-50 border-2 border-orange-100 hover:border-orange-300 hover:bg-orange-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                        <svg class="w-7 h-7 text-orange-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_rooms') }}</p>
                        <p class="text-xs text-orange-700/70 mt-0.5">{{ __('app.add_configure_meeting_rooms') }}</p>
                    </div>
                </a>

                {{-- Room Requirements --}}
                <a href="{{ route('it-officer.requirements') }}"
                   class="flex items-center gap-4 p-5 bg-orange-50 border-2 border-orange-100 hover:border-orange-300 hover:bg-orange-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                        <svg class="w-7 h-7 text-orange-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_requirements') }}</p>
                        <p class="text-xs text-orange-700/70 mt-0.5">{{ __('app.add_configure_requirements') }}</p>
                    </div>
                </a>

                {{-- ── BLUE GROUP: Vehicles ──────────────────────────────────────────── --}}

                {{-- Vehicles --}}
                <a href="{{ route('it-officer.managevehicle') }}"
                   class="flex items-center gap-4 p-5 bg-blue-50 border-2 border-blue-100 hover:border-blue-300 hover:bg-blue-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                        <svg class="w-7 h-7 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_vehicles') }}</p>
                        <p class="text-xs text-blue-700/70 mt-0.5">{{ __('app.add_configure_vehicles') }}</p>
                    </div>
                </a>

                {{-- ── AMBER GROUP: Storages ─────────────────────────────────────────── --}}

                {{-- Storages --}}
                <a href="{{ route('it-officer.managestorage') }}"
                   class="flex items-center gap-4 p-5 bg-amber-50 border-2 border-amber-100 hover:border-amber-300 hover:bg-amber-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                        <svg class="w-7 h-7 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_storages') }}</p>
                        <p class="text-xs text-amber-700/70 mt-0.5">{{ __('app.add_configure_storage_areas') }}</p>
                    </div>
                </a>

                {{-- ── PURPLE GROUP: ID Types & Visitor Lanyards ────────────────────── --}}

                {{-- ID Types --}}
                <a href="{{ route('it-officer.id-types') }}"
                   class="flex items-center gap-4 p-5 bg-purple-50 border-2 border-purple-100 hover:border-purple-300 hover:bg-purple-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                        <svg class="w-7 h-7 text-purple-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_id_types') }}</p>
                        <p class="text-xs text-purple-700/70 mt-0.5">{{ __('app.add_configure_id_types') }}</p>
                    </div>
                </a>

                {{-- Visitor Lanyards --}}
                <a href="{{ route('it-officer.visitor-lanyards') }}"
                   class="flex items-center gap-4 p-5 bg-purple-50 border-2 border-purple-100 hover:border-purple-300 hover:bg-purple-100 shadow-sm hover:shadow-md rounded-xl transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                        <svg class="w-7 h-7 text-purple-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ __('app.manage_visitor_lanyards') }}</p>
                        <p class="text-xs text-purple-700/70 mt-0.5">{{ __('app.add_configure_visitor_lanyards') }}</p>
                    </div>
                </a>

            </div>
        </div>
    </main>
</div>