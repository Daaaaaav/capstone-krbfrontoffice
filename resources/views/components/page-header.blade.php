{{-- Page Header Component --}}
@props([
    'title',
    'subtitle' => null,
])

<div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-yellow-300 shadow-2xl">
    <div class="pointer-events-none absolute inset-0 opacity-10">
        <div class="absolute top-0 -right-4 w-24 h-24 bg-yellow-300 rounded-full blur-xl"></div>
        <div class="absolute bottom-0 -left-4 w-16 h-16 bg-yellow-300 rounded-full blur-lg"></div>
    </div>
    <div class="relative z-10 px-6 sm:px-8 py-5 sm:py-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-lg sm:text-xl font-semibold tracking-tight text-yellow-300">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm text-yellow-300/75 mt-0.5">{{ $subtitle }}</p>
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
