@php
    $card  = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    $label = 'block text-sm font-medium text-gray-700 mb-2';
    $input = 'w-full h-10 px-3 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition';
    $chip  = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-gray-100 text-xs';
    $icoAvatar = 'w-10 h-10 bg-[#4E653D] rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0';
    $btn   = 'px-4 py-2 text-xs font-medium rounded-lg text-white focus:outline-none focus:ring-2 transition shadow-sm';
@endphp

<div class="min-h-screen bg-background" wire:poll.2s="tick">
    <main class="px-4 sm:px-6 py-6 space-y-6">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.room_approval_title') }}"
            subtitle="{{ __('app.room_approval_sub') }}">
            <x-slot:actions>
                <div class="flex items-center gap-1 bg-secondary p-1 rounded-lg border border-border">
                    <button type="button"
                            wire:click="setViewMode('card')"
                            class="p-1.5 rounded-md transition-all {{ $viewMode === 'card' ? 'bg-background text-foreground shadow-sm border border-border/40' : 'text-muted-foreground hover:text-foreground' }}"
                            title="Card View">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </button>
                    <button type="button"
                            wire:click="setViewMode('table')"
                            class="p-1.5 rounded-md transition-all {{ $viewMode === 'table' ? 'bg-background text-foreground shadow-sm border border-border/40' : 'text-muted-foreground hover:text-foreground' }}"
                            title="Table View">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
            </x-slot:actions>
        </x-page-header>

        {{-- PENDING --}}
        <section class="{{ $card }}">
            <div class="px-6 py-4 border-b border-gray-200 bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.pending_approval') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('app.pending_approval_sub') }}</p>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-5 bg-gray-50/30">
                @if($viewMode === 'card')
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @forelse ($pending as $m)
                            @php $id = $m['id']; @endphp
                            <div wire:key="p-{{ $id }}"
                                 class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                
                                <div class="flex items-start gap-4">
                                    {{-- 1. Avatar/Initial --}}
                                    <div class="{{ $icoAvatar }} mt-0.5">
                                        {{ strtoupper(substr($m['meeting_title'] ?? 'M',0,1)) }}
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        {{-- 2. TOP ROW --}}
                                        <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                            <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                {{ $m['meeting_title'] }}
                                            </h4>
                                            <div class="flex-shrink-0 flex items-center gap-2">
                                                {{-- ID Chip --}}
                                                <span class="text-[11px] px-2 py-0.5 rounded-full border border-gray-300 text-gray-700 bg-gray-50 flex-shrink-0 font-mono">
                                                        {{ $loop->iteration }}
                                                    </span>
                                            </div>
                                        </div>
                                        
                                        {{-- 3. MIDDLE SECTION --}}
                                        <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                            <div class="flex flex-wrap items-center gap-4">
                                                <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $m['date'] }}
                                                </span>
                                                <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ $m['time'] }}–{{ $m['time_end'] }}
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-gray-100">
                                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                    <span class="font-medium text-gray-700">{{ $m['room'] }}</span>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        {{-- 4. BOTTOM LEFT --}}
                                        <div class="text-[12px] text-gray-600 space-y-1">
                                            <p>{{ __('app.participants') }}: <span class="font-medium text-gray-800">{{ $m['participants'] }}</span></p>
                                        </div>
                                    </div>
                                </div>
                                                               {{-- 5. BOTTOM ACTIONS (Auto-Approve Notice) --}}
                                <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-600 border border-amber-500/20">
                                        {{ __('app.pending') }}
                                    </span>
                                    <span class="text-[11px] text-muted-foreground italic">
                                        Auto-approves at start time
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-12 text-gray-500 text-sm bg-white border border-dashed border-gray-200 rounded-xl">{{ __('app.no_pending_requests') }}</div>
                        @endforelse
                    </div>
                @else
                    {{-- Premium Table Layout --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                    <th class="px-6 py-3.5">#</th>
                                    <th class="px-6 py-3.5">{{ __('app.meeting_title_col') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.room') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.participants') }}</th>
                                    <th class="px-6 py-3.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($pending as $m)
                                    @php $id = $m['id']; @endphp
                                    <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                        <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="h-12 px-6 py-4 font-semibold text-gray-900">{{ $m['meeting_title'] }}</td>
                                        <td class="h-12 px-6 py-0 ">
                                            <div class="flex justify-end">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 text-xs font-semibold text-gray-700">
                                                    {{ $m['room'] }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="h-12 px-6 py-4 font-medium">{{ $m['date'] }}</td>
                                        <td class="h-12 px-6 py-4 font-mono text-xs">{{ $m['time'] }}–{{ $m['time_end'] }}</td>
                                        <td class="h-12 px-6 py-4 font-medium text-gray-800">{{ $m['participants'] }}</td>
                                        <td class="h-12 px-6 py-4">
                                            <div class="flex justify-end">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border bg-amber-500/10 text-amber-600 border-amber-500/20">
                                                    {{ __('app.pending') }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-12 text-gray-500 text-sm">{{ __('app.no_pending_requests') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($pending->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        {{ $pending->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </section>

        {{-- ONGOING --}}
        <section class="{{ $card }}">
            <div class="px-6 py-4 border-b border-gray-200 bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 bg-amber-600 rounded-full"></div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.ongoing_meetings') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('app.ongoing_meetings_sub') }}</p>
                    </div>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-5 bg-gray-50/30">
                @if($viewMode === 'card')
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @forelse ($ongoing as $m)
                            @php $id = $m['id']; @endphp
                            <div wire:key="o-{{ $id }}"
                                 class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                
                                <div class="flex items-start gap-4">
                                    {{-- 1. Avatar/Initial --}}
                                    <div class="{{ $icoAvatar }} mt-0.5">
                                        {{ strtoupper(substr($m['meeting_title'] ?? 'M',0,1)) }}
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        {{-- 2. TOP ROW --}}
                                        <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                            <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                {{ $m['meeting_title'] }}
                                            </h4>
                                            <div class="flex-shrink-0 flex items-center gap-2">
                                                {{-- ID Chip --}}
                                                <span class="text-[11px] px-2 py-0.5 rounded-full border border-gray-300 text-gray-700 bg-gray-50 flex-shrink-0 font-mono">
                                                        {{ $loop->iteration }}
                                                    </span>
                                            </div>
                                        </div>
                                        
                                        {{-- 3. MIDDLE SECTION --}}
                                        <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                            <div class="flex flex-wrap items-center gap-4">
                                                <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $m['date'] }}
                                                </span>
                                                <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ $m['time'] }}–{{ $m['time_end'] }}
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-gray-100">
                                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                    <span class="font-medium text-gray-700">{{ $m['room'] }}</span>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        {{-- 4. BOTTOM LEFT --}}
                                        <div class="text-[12px] text-gray-600 space-y-1">
                                            <p>{{ __('app.participants') }}: <span class="font-medium text-gray-800">{{ $m['participants'] }}</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-12 text-gray-500 text-sm bg-white border border-dashed border-gray-200 rounded-xl">{{ __('app.no_ongoing_meetings') }}</div>
                        @endforelse
                    </div>
                @else
                    {{-- Premium Table Layout --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                    <th class="px-6 py-3.5">#</th>
                                    <th class="px-6 py-3.5">{{ __('app.meeting_title_col') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.room') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                    <th class="px-6 py-3.5">{{ __('app.participants') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($ongoing as $m)
                                    @php $id = $m['id']; @endphp
                                    <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                        <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="h-12 px-6 py-4 font-semibold text-gray-900">{{ $m['meeting_title'] }}</td>
                                        <td class="h-12 px-6 py-0 ">
                                            <div class="flex justify-end">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 text-xs font-semibold text-gray-700">
                                                    {{ $m['room'] }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="h-12 px-6 py-4 font-medium">{{ $m['date'] }}</td>
                                        <td class="h-12 px-6 py-4 font-mono text-xs">{{ $m['time'] }}–{{ $m['time_end'] }}</td>
                                        <td class="h-12 px-6 py-4 font-medium text-gray-800">{{ $m['participants'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-12 text-gray-500 text-sm">{{ __('app.no_ongoing_meetings') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($ongoing->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        {{ $ongoing->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </section>
    </main>
</div>
