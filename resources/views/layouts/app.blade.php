<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'App' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/kebun-raya-bogor.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen font-sans animate-fade-in-up" data-theme="light">

    @livewire('components.partials.navbar', [], 'layout-navbar')

    <main class="w-full pt-9 pb-4 px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
    
    @livewire('components.ui.chat-modal', [], 'layout-chat-modal')

    @livewire('booking.quick-book-modal')
    @livewire('booking.quick-vehicle-book-modal')

    {{-- Chatbot FAB --}}
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2"
         x-data="{ show: false }"
         x-on:keydown.escape.window="show = false">

        {{-- Tooltip label (appears on hover) --}}
        <div x-show="show"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-sidebar text-white text-xs font-semibold px-3 py-1.5 rounded-xl shadow-lg border border-white/10 whitespace-nowrap"
             style="display: none;">
            KRB Assistant
        </div>

        {{-- FAB button --}}
        <button type="button"
                x-on:mouseenter="show = true"
                x-on:mouseleave="show = false"
                x-on:click="$dispatch('openChatModal'); show = false"
                class="w-14 h-14 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground flex items-center justify-center shadow-xl transition-all duration-200 hover:scale-105 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                aria-label="Open AI assistant">
            {{-- Chat icon --}}
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
    </div>

    @include('livewire.components.partials.footer')
    @livewire('components.ui.toast', [], 'layout-toast')


    @livewireScripts
</body>

</html>