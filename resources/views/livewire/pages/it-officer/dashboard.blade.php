<div class="py-6 space-y-6">
    {{-- Welcome Header --}}
    <div class="bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-border rounded-2xl p-6">
        <h1 class="text-3xl font-bold text-foreground mb-2">
            {{ __('app.welcome') }}, {{ Auth::user()->full_name }}!
        </h1>
        <p class="text-muted-foreground">
            Manage system users and resources from your IT Officer dashboard.
        </p>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Receptionists Card --}}
        <div class="bg-card border border-border rounded-xl p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-foreground">{{ $stats['receptionists'] }}</span>
            </div>
            <h3 class="text-sm font-semibold text-muted-foreground mb-1">{{ __('app.receptionists') }}</h3>
            <a href="{{ route('it-officer.receptionists') }}" class="text-sm text-primary hover:underline">Manage →</a>
        </div>

        {{-- Managers Card --}}
        <div class="bg-card border border-border rounded-xl p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-purple-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-foreground">{{ $stats['managers'] }}</span>
            </div>
            <h3 class="text-sm font-semibold text-muted-foreground mb-1">Managers</h3>
            <a href="{{ route('it-officer.managers') }}" class="text-sm text-primary hover:underline">Manage →</a>
        </div>

        {{-- Rooms Card --}}
        <div class="bg-card border border-border rounded-xl p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-foreground">{{ $stats['rooms'] }}</span>
            </div>
            <h3 class="text-sm font-semibold text-muted-foreground mb-1">Rooms</h3>
            <a href="{{ route('it-officer.manageroom') }}" class="text-sm text-primary hover:underline">Manage →</a>
        </div>

        {{-- Vehicles Card --}}
        <div class="bg-card border border-border rounded-xl p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-orange-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                        <circle cx="7" cy="17" r="2"/>
                        <circle cx="17" cy="17" r="2"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-foreground">{{ $stats['vehicles'] }}</span>
            </div>
            <h3 class="text-sm font-semibold text-muted-foreground mb-1">Vehicles</h3>
            <a href="{{ route('it-officer.managevehicle') }}" class="text-sm text-primary hover:underline">Manage →</a>
        </div>

        {{-- Storages Card --}}
        <div class="bg-card border border-border rounded-xl p-6 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-cyan-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                        <line x1="12" y1="12" x2="12" y2="16"/>
                        <line x1="10" y1="14" x2="14" y2="14"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-foreground">{{ $stats['storages'] }}</span>
            </div>
            <h3 class="text-sm font-semibold text-muted-foreground mb-1">Storages</h3>
            <a href="{{ route('it-officer.managestorage') }}" class="text-sm text-primary hover:underline">Manage →</a>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-card border border-border rounded-xl p-6">
        <h2 class="text-xl font-bold text-foreground mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('it-officer.receptionists') }}" class="flex items-center gap-3 p-4 bg-secondary/50 hover:bg-secondary rounded-lg transition-colors">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
                <span class="font-medium text-foreground">Add Receptionist</span>
            </a>

            <a href="{{ route('it-officer.managers') }}" class="flex items-center gap-3 p-4 bg-secondary/50 hover:bg-secondary rounded-lg transition-colors">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
                <span class="font-medium text-foreground">Add Manager</span>
            </a>

            <a href="{{ route('it-officer.manageroom') }}" class="flex items-center gap-3 p-4 bg-secondary/50 hover:bg-secondary rounded-lg transition-colors">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M10 21V11.5a1.5 1.5 0 0 1 3 0V21"/>
                </svg>
                <span class="font-medium text-foreground">Add Room</span>
            </a>

            <a href="{{ route('it-officer.managevehicle') }}" class="flex items-center gap-3 p-4 bg-secondary/50 hover:bg-secondary rounded-lg transition-colors">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                    <circle cx="7" cy="17" r="2"/>
                    <circle cx="17" cy="17" r="2"/>
                </svg>
                <span class="font-medium text-foreground">Add Vehicle</span>
            </a>

            <a href="{{ route('it-officer.managestorage') }}" class="flex items-center gap-3 p-4 bg-secondary/50 hover:bg-secondary rounded-lg transition-colors">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1z"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                </svg>
                <span class="font-medium text-foreground">Add Storage</span>
            </a>
        </div>
    </div>
</div>
