@props([
    'brandName' => 'KRB',
])

<aside x-data="{ open: false }" class="fixed inset-y-0 left-0 z-40 w-64 bg-sidebar border-r border-sidebar-border text-sidebar-foreground flex flex-col transition-transform duration-300 lg:translate-x-0 lg:static lg:h-screen" :class="open ? 'translate-x-0' : '-translate-x-full'"
    <div class="h-14 flex items-center justify-between px-6 border-b border-sidebar-border shrink-0">
        <span class="font-semibold text-lg tracking-tight text-sidebar-foreground">{{ $brandName }}</span>
        <button type="button" @click="open = false" class="lg:hidden p-1.5 rounded-md hover:bg-sidebar-accent transition-colors">
            <x-heroicon-o-x-mark class="w-5 h-5 text-sidebar-foreground" />
        </button>
    </div>
    
    <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-4">
        {{ $nav }}
    </nav>

    @if (isset($footer))
        <div class="p-4 border-t border-sidebar-border bg-sidebar-accent/5 shrink-0">
            {{ $footer }}
        </div>
    @endif
</aside>
