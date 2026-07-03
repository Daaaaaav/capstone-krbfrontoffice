<div>
    <main class="max-w-3xl mx-auto py-6 space-y-6">

        {{-- HERO BANNER --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#CDDEA7]/10 rounded-xl flex items-center justify-center backdrop-blur-sm border border-[#CDDEA7]/20">
                            <x-heroicon-o-question-mark-circle class="w-6 h-6 text-[#CDDEA7]"/>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold text-[#CDDEA7]">{{ __('app.help_title') }}</h2>
                            <p class="text-sm text-[#CDDEA7]/80 mt-1">
                                {{ __('app.help_find_answers') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== SEARCH ===== --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.search_questions') }}</label>
            <div class="relative">
                <input type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('app.search_questions') }}..."
                    class="w-full h-10 pl-9 pr-9 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 bg-white transition text-sm">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                @if($search)
                    <button wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                        ✕
                    </button>
                @endif
            </div>
        </div>

        {{-- ===== FAQ LIST ===== --}}
        @if(count($filteredFaqs) > 0)
            <div class="space-y-3" x-data="{ open: null }">
                @foreach($filteredFaqs as $index => $faq)
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden transition hover:border-gray-300">
                        <button
                            @click="open === {{ $index }} ? open = null : open = {{ $index }}"
                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                            <div class="flex items-start gap-4">
                                <span class="mt-0.5 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#4E653D]/10 text-[#4E653D] border border-[#4E653D]/20 shrink-0">
                                    {{ $faq['category'] }}
                                </span>
                                <span class="text-sm font-medium text-gray-900 leading-snug">{{ $faq['question'] }}</span>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 shrink-0 ml-4 transition-transform duration-300"
                                :class="open === {{ $index }} ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open === {{ $index }}" x-collapse
                            class="px-6 pb-5 text-sm text-gray-600 leading-relaxed border-t border-gray-100 pt-4 bg-gray-50/50">
                            {{ $faq['answer'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm px-6 py-12 text-center">
                <x-heroicon-o-magnifying-glass class="w-10 h-10 text-gray-300 mx-auto mb-3" />
                <p class="text-gray-500 text-sm">{{ __('app.no_results_for') }} "<span class="font-medium text-gray-950">{{ $search }}</span>".</p>
                <button wire:click="$set('search', '')" class="mt-3 text-sm text-[#4E653D] font-medium hover:text-[#354C2B] underline hover:no-underline transition">
                    {{ __('app.clear_search') }}
                </button>
            </div>
        @endif

        {{-- ===== CONTACT CARD ===== --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl p-6 sm:p-8">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10">
                <h3 class="text-base font-semibold text-white mb-1">{{ __('app.still_need_help') }}</h3>
                <p class="text-sm text-[#CDDEA7]/80 mb-4">
                    {{ __('app.still_need_help_sub') }}
                </p>
                <div class="flex flex-wrap gap-3 text-sm">
                    <a href="mailto:davina.superadmin@gmail.com"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#CDDEA7] text-[#4A2F24] rounded-lg font-semibold hover:bg-white hover:text-gray-900 transition shadow-sm">
                        <x-heroicon-o-envelope class="w-4 h-4 shrink-0" />
                        <span>{{ __('app.email_admin') }}</span>
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>

