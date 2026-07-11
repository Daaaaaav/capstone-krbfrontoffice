@php
use Illuminate\Support\Facades\Auth;

$user = Auth::user();

$fullName = trim($user->full_name ?? 'User');
$parts = preg_split('/\s+/', $fullName);
$firstInitial = strtoupper(substr($parts[0] ?? 'U', 0, 1));
$lastInitial = strtoupper(substr($parts[count($parts)-1] ?? '', 0, 1));
$initials = $firstInitial . $lastInitial;

//sidebar brand
$authUser = Auth::user()?->loadMissing('company');
$brandName = $authUser?->company?->company_name ?? 'Kebun Raya Bogor';
$brandLogo = $authUser?->company?->image ?: asset('images/logo/kebun-raya-bogor.png');

$invertStyle = 'filter: brightness(0) invert(1);';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'App' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/kebun-raya-bogor.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
    @livewireStyles
</head>

<body class="min-h-screen bg-background text-foreground font-sans"
    x-data="{ sidebarCollapsed: true }"
    :style="sidebarCollapsed ? '--sbw: 4.5rem' : '--sbw: 16rem'"
    :class="sidebarCollapsed ? 'sidebar-is-collapsed' : 'sidebar-is-expanded'"
>
    {{-- Mobile header only (<lg) --}}
    <flux:header class="lg:hidden bg-sidebar border-b border-sidebar-border">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <div class="font-semibold text-sidebar-foreground tracking-wide font-sans">Kebun Raya Bogor</div>

        <flux:spacer />

        {{-- Language Toggle (Mobile Header) --}}
        @php $isEnHeader = app()->getLocale() === 'en'; @endphp
        <div x-data="{ open: false }" class="relative shrink-0">
            <button @click.stop="open = !open" type="button"
                class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold text-sidebar-foreground hover:bg-sidebar-accent border border-sidebar-border/50 transition-all">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                <span>{{ $isEnHeader ? 'EN' : 'ID' }}</span>
            </button>
            <div x-show="open" x-transition @click.outside="open = false"
                class="absolute right-0 top-full mt-1.5 w-36 rounded-xl bg-sidebar border border-sidebar-border shadow-xl z-[9999] overflow-hidden"
                style="display:none;">
                <a href="{{ route('lang.switch', 'en') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ $isEnHeader ? 'bg-sidebar-accent font-semibold text-sidebar-foreground' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground' }}">
                    <span>🇬🇧</span><span>{{ __('app.english') }}</span>
                    @if($isEnHeader)<svg class="w-3.5 h-3.5 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                </a>
                <a href="{{ route('lang.switch', 'id') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ !$isEnHeader ? 'bg-sidebar-accent font-semibold text-sidebar-foreground' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground' }}">
                    <span>🇮🇩</span><span>{{ __('app.indonesian') }}</span>
                    @if(!$isEnHeader)<svg class="w-3.5 h-3.5 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                </a>
            </div>
        </div>

        <flux:dropdown position="top" align="start">
            <flux:profile avatar-text="{{ strtoupper($initials) }}" />
            <flux:menu>
                <flux:menu.radio.group>
                    <flux:menu.radio checked>{{ $fullName }}</flux:menu.radio>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.item
                    icon="arrow-right-start-on-rectangle"
                    as="button"
                    type="submit"
                    form="logout-form">
                    Logout
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{-- Form logout tersembunyi (di luar dropdown) --}}
    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <div class="flex min-h-screen lg:min-h-0">
        {{-- Sidebar (always full height) --}}
        @include('livewire.components.partials.receptionist.sidebar')

        {{-- Main Content Wrapper --}}
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden bg-background relative">
            {{-- Mobile header only (<lg) --}}
            <header class="lg:hidden flex items-center justify-between bg-sidebar border-b border-sidebar-border px-4 py-3 shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="mobileMenuOpen = true" class="text-sidebar-foreground hover:bg-white/10 p-1.5 rounded-lg transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="font-semibold text-sidebar-foreground tracking-wide font-sans truncate min-w-0">Kebun Raya Bogor</div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('notifications.index') }}" class="relative flex items-center justify-center w-8 h-8 rounded-lg text-sidebar-foreground hover:bg-sidebar-accent border border-sidebar-border/50 transition-all focus:outline-none">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                        <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-red-500 rounded-full border border-[#2a1f1a]"></span>
                    </a>

                    {{-- Language Toggle (Mobile Header) --}}
                    @php $isEnHeader = app()->getLocale() === 'en'; @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button @click.stop="open = !open" type="button"
                            class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold text-sidebar-foreground hover:bg-sidebar-accent border border-sidebar-border/50 transition-all focus:outline-none">
                            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            <span>{{ $isEnHeader ? 'EN' : 'ID' }}</span>
                        </button>
                        <div x-show="open" x-transition @click.outside="open = false"
                            class="absolute right-0 top-full mt-1.5 w-36 rounded-xl bg-sidebar border border-sidebar-border shadow-xl z-[9999] overflow-hidden"
                            style="display:none;">
                            <a href="{{ route('lang.switch', 'en') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ $isEnHeader ? 'bg-sidebar-accent font-semibold text-sidebar-foreground' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground' }}">
                                <span>🇬🇧</span><span>{{ __('app.english') }}</span>
                            </a>
                            <a href="{{ route('lang.switch', 'id') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ !$isEnHeader ? 'bg-sidebar-accent font-semibold text-sidebar-foreground' : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-foreground' }}">
                                <span>🇮🇩</span><span>{{ __('app.indonesian') }}</span>
                            </a>
                        </div>
                    </div>

                    {{-- Profile Dropdown Mobile --}}
                    <div x-data="{ openProfile: false }" class="relative">
                        <button @click.stop="openProfile = !openProfile" class="w-8 h-8 rounded-full bg-sidebar-accent flex items-center justify-center text-sidebar-foreground font-bold text-sm hover:ring-2 hover:ring-sidebar-border transition-all focus:outline-none">
                            {{ strtoupper($initials) }}
                        </button>
                        <div x-show="openProfile" x-transition @click.outside="openProfile = false" style="display:none;" class="absolute right-0 top-full mt-1.5 w-48 bg-[#2a1f1a] border border-white/10 rounded-xl shadow-2xl z-[9999] overflow-hidden">
                            <div class="px-4 py-2.5 border-b border-white/10">
                                <p class="text-sm font-semibold text-white truncate">{{ $fullName }}</p>
                                <p class="text-[10px] text-white/40 mt-0.5">Receptionist</p>
                            </div>
                            <button type="submit" form="logout-form" class="w-full text-left px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors flex items-center gap-2.5">
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
            </header>

            {{-- Main Scrollable Area --}}
            <main class="flex-1 overflow-y-auto overflow-x-hidden relative animate-fade-in-up transition-[padding] duration-300 ease-in-out" :style="isMobile ? 'padding-left: 0;' : (sidebarLocked ? 'padding-left: 280px;' : 'padding-left: 64px;')">
                <div class="w-full h-full px-4 sm:px-6 lg:px-8 py-4 lg:py-0
                            [&_.container]:max-w-none [&_.container]:mx-0 [&_.container]:px-0">

                {{-- Premium Top Header Bar --}}
                <header class="hidden lg:flex items-center justify-between py-4 border-b border-border/80 mb-6 select-none">
                    <div class="flex items-center gap-3">
                        {{-- Breadcrumbs Component --}}
                        @include('components.breadcrumbs')
                    </div>

                    {{-- Right side: notification bell + language toggle + date badge --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ route('notifications.index') }}" class="relative flex items-center justify-center w-8 h-8 rounded-lg bg-secondary/80 border border-border text-foreground hover:bg-accent transition-all duration-200">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                            <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-red-500 rounded-full border border-background"></span>
                        </a>

                        {{-- Language Toggle --}}
                        <div class="relative" x-data="{ open: false }">
                            @php $isEn = app()->getLocale() === 'en'; @endphp
                            <button @click.stop="open = !open" type="button"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-secondary/80 border border-border text-foreground hover:bg-accent">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                </svg>
                                <span>{{ $isEn ? 'EN' : 'ID' }}</span>
                                <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div x-show="open" x-transition @click.outside="open = false"
                                class="absolute right-0 top-full mt-1.5 w-36 rounded-xl bg-card border border-border shadow-xl z-[9999] overflow-hidden"
                                style="display:none;">
                                <a href="{{ route('lang.switch', 'en') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ $isEn ? 'bg-accent font-semibold text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
                                    <span>🇬🇧</span><span>{{ __('app.english') }}</span>
                                    @if($isEn)<svg class="w-3.5 h-3.5 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                                </a>
                                <a href="{{ route('lang.switch', 'id') }}" class="flex items-center gap-2.5 px-3 py-2.5 text-sm transition-colors {{ !$isEn ? 'bg-accent font-semibold text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
                                    <span>🇮🇩</span><span>{{ __('app.indonesian') }}</span>
                                    @if(!$isEn)<svg class="w-3.5 h-3.5 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>@endif
                                </a>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-muted-foreground/80 bg-secondary/80 border border-border px-3 py-1.5 rounded-xl shadow-xs">
                            {{ now()->locale(app()->getLocale())->translatedFormat('l, d M Y') }}
                        </span>
                    </div>
                </header>

                {{ $slot ?? '' }}
                @yield('content')

            </div>
        </main>
    </div>


    @livewire('components.ui.toast')

    @livewireScripts
    @fluxScripts
    @vite('resources/js/app.js')

    {{-- Refresh CSRF token periodically so long-lived pages don't get 419 Page Expired --}}
    <script>
        (function () {
            function refreshCsrf() {
                fetch('/csrf-token-refresh', { method: 'GET', credentials: 'same-origin' })
                    .then(r => r.ok ? r.json() : null)
                    .then(data => {
                        if (!data || !data.token) return;
                        // Update the meta tag
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.setAttribute('content', data.token);
                        // Update all hidden _token inputs on the page
                        document.querySelectorAll('input[name="_token"]').forEach(el => el.value = data.token);
                        // Update Livewire's CSRF header
                        if (window.Livewire) {
                            Livewire.navigate && document.dispatchEvent(new CustomEvent('livewire:csrf-refresh'));
                        }
                    })
                    .catch(() => {});
            }
            // Refresh every 30 minutes
            setInterval(refreshCsrf, 30 * 60 * 1000);
        })();
    </script>
</body>

</html>