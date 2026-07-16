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

    <div class="fixed bottom-6 right-6 z-[70]">
        <button
            x-data
            x-on:click="$dispatch('openChatModal')"
            class="bg-primary hover:bg-primary/90 text-primary-foreground p-3.5 rounded-2xl shadow-xl shadow-primary/10 hover:shadow-primary/20 transition-all duration-300 hover:scale-105 hover:-translate-y-1 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
            aria-label="Open AI assistant"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </button>
    </div>

    @include('livewire.components.partials.footer')
    @livewire('components.ui.toast', [], 'layout-toast')


    @livewireScripts
</body>

</html>