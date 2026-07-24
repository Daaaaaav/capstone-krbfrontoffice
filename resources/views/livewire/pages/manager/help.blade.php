<div>
    <main class="max-w-3xl mx-auto py-8 space-y-8">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.help_title') }}"
            subtitle="{{ __('app.help_find_answers') }}" />

        {{-- ===== SEARCH ===== --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm">
            <div class="relative flex items-center">
                <div class="absolute left-3 text-[#9aaa8a]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35m1.6-5.65a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                </div>
                <input type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('app.search_questions') }}"
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-[#c4d4b4] text-[#2d3a24]
                           placeholder-[#9aaa8a] focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                @if($search)
                    <button wire:click="$set('search', '')"
                        class="absolute right-3 text-[#9aaa8a] hover:text-[#4E653D] transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- ===== FAQ LIST ===== --}}
        @if(count($filteredFaqs) > 0)
            <div class="space-y-3" x-data="{ open: null }">
                @foreach($filteredFaqs as $index => $faq)
                    <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                        <button
                            @click="open === {{ $index }} ? open = null : open = {{ $index }}"
                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-[#f0f4eb] transition">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-[#eef1e8] text-[#5a6e4a] shrink-0">
                                    {{ $faq['category'] }}
                                </span>
                                <span class="text-sm font-medium text-[#2d3a24]">{{ $faq['question'] }}</span>
                            </div>
                            <svg class="w-5 h-5 text-[#9aaa8a] shrink-0 ml-4 transition-transform"
                                :class="open === {{ $index }} ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open === {{ $index }}" x-collapse
                            class="px-6 pb-5 text-sm text-[#5a6e4a] leading-relaxed border-t border-[#e4edd8] pt-4">
                            {{ $faq['answer'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm px-6 py-12 text-center">
                <svg class="w-10 h-10 text-[#b5c4a5] mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
                <p class="text-[#7a8f6a] text-sm">{{ __('app.no_results_for') }} "<span class="font-medium">{{ $search }}</span>".</p>
                <button wire:click="$set('search', '')" class="mt-3 text-sm text-[#4E653D] underline hover:text-[#2d3a24]">
                    {{ __('app.clear_search') }}
                </button>
            </div>
        @endif

        {{-- ===== CONTACT CARD ===== --}}
        <div class="bg-gray-900 text-white rounded-2xl px-6 py-6 shadow-sm">
            <h3 class="text-base font-semibold mb-1">{{ __('app.still_need_help') }}</h3>
            <p class="text-sm text-gray-300 mb-4">
                {{ __('app.still_need_help_sub') }}
            </p>
            <div class="flex flex-wrap gap-3 text-sm">
                <a href="mailto:helpadminkrb@gmail.com"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-900 rounded-xl font-medium hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    {{ __('app.email_admin') }}
                </a>
            </div>
        </div>

    </main>
</div>
