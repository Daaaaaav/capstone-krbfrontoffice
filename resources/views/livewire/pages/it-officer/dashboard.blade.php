<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        <x-page-header title="{{ __('app.dashboard') }}" subtitle="{{ __('app.it_officer_system_management') }}">
            <x-slot:actions>
                <button wire:click="$refresh"
                    class="px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md border border-border hover:bg-accent transition-colors">
                    {{ __('app.refresh') }}
                </button>
            </x-slot:actions>
        </x-page-header>

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
                <a href="{{ route('it-officer.receptionists') }}" 
                   class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-[#4E653D]/40 shadow-sm hover:shadow-md rounded-xl transition-all group">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-[#4E653D]/10 flex items-center justify-center group-hover:bg-[#4E653D]/20 transition-colors">
                        <svg class="w-7 h-7 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="22" y1="11" x2="16" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-foreground">{{ __('app.manage_receptionists') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_edit_receptionist_users') }}</p>
                    </div>
                </a>

                <a href="{{ route('it-officer.managers') }}" 
                   class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-[#4A2F24]/40 shadow-sm hover:shadow-md rounded-xl transition-all group">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-[#4A2F24]/10 flex items-center justify-center group-hover:bg-[#4A2F24]/20 transition-colors">
                        <svg class="w-7 h-7 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="22" y1="11" x2="16" y2="11"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-foreground">{{ __('app.manage_managers') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_edit_manager_users') }}</p>
                    </div>
                </a>

                <a href="{{ route('it-officer.manageroom') }}" 
                   class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-[#4E653D]/40 shadow-sm hover:shadow-md rounded-xl transition-all group">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-[#4E653D]/10 flex items-center justify-center group-hover:bg-[#4E653D]/20 transition-colors">
                        <svg class="w-7 h-7 text-[#4E653D]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-foreground">{{ __('app.manage_rooms') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_configure_meeting_rooms') }}</p>
                    </div>
                </a>

                <a href="{{ route('it-officer.managevehicle') }}" 
                   class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-[#4A2F24]/40 shadow-sm hover:shadow-md rounded-xl transition-all group">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-[#4A2F24]/10 flex items-center justify-center group-hover:bg-[#4A2F24]/20 transition-colors">
                        <svg class="w-7 h-7 text-[#4A2F24]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-foreground">{{ __('app.manage_vehicles') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_configure_vehicles') }}</p>
                    </div>
                </a>

                <a href="{{ route('it-officer.managestorage') }}" 
                   class="flex items-center gap-4 p-5 bg-card border-2 border-transparent hover:border-amber-400/40 shadow-sm hover:shadow-md rounded-xl transition-all group">
                    <div class="w-14 h-14 shrink-0 rounded-xl bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition-colors">
                        <svg class="w-7 h-7 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-bold text-foreground">{{ __('app.manage_storages') }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_configure_storage_areas') }}</p>
                    </div>
                </a>
            </div>
        </div>
    </main>
</div>
