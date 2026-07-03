{{-- Page Header Component --}}
@props([
    'title',
    'subtitle' => null,
])

<div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] shadow-2xl" style="color-scheme:normal">
    <div class="pointer-events-none absolute inset-0 opacity-10">
        <div class="absolute top-0 -right-4 w-24 h-24 rounded-full blur-xl" style="background:#fde047"></div>
        <div class="absolute bottom-0 -left-4 w-16 h-16 rounded-full blur-lg" style="background:#fde047"></div>
    </div>
    <div class="relative z-10 px-6 sm:px-8 py-5 sm:py-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-lg sm:text-xl font-semibold tracking-tight" style="color:#fde047 !important">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm mt-0.5" style="color:rgba(253,224,71,0.75) !important">{{ $subtitle }}</p>
                @endif
            </div>
            @if (isset($actions))
                <div class="flex items-center gap-2 shrink-0">
                    {{ $actions }}
                </div>
            @endif
        </div>
    </div>
</div>
