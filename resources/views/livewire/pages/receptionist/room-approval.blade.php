@php
    $card  = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    $label = 'block text-sm font-medium text-gray-700 mb-2';
    $input = 'w-full h-10 px-3 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition';
    $chip  = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-gray-100 text-xs';
    $icoAvatar = 'w-10 h-10 bg-[#4E653D] rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0';
    $btn   = 'px-4 py-2 text-xs font-medium rounded-lg text-white focus:outline-none focus:ring-2 transition shadow-sm';
@endphp

<div class="min-h-screen bg-background">
    <main class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">
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

        {{-- MANAGER PRIORITY ROOM BOOKINGS --}}
        @if(isset($priorityRoomBookings) && $priorityRoomBookings->isNotEmpty())
        <section class="{{ $card }}">
            <div class="px-6 py-4 border-b border-amber-200 bg-amber-50/60">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <div>
                        <h3 class="text-base font-semibold text-amber-800">Manager Priority Room Bookings</h3>
                        <p class="text-xs text-amber-600/80">Priority bookings submitted by managers — click any card to view full details</p>
                    </div>
                    <span class="ml-auto inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500 text-white">
                        {{ $priorityRoomBookings->count() }}
                    </span>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-5 bg-amber-50/20">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($priorityRoomBookings as $pb)
                    @php
                        $pbColor = match($pb->status) {
                            'approved'             => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'pending_receipt'       => 'bg-amber-100 text-amber-700 border-amber-200',
                            'pending_cancellation'  => 'bg-orange-100 text-orange-700 border-orange-200',
                            default                 => 'bg-gray-100 text-gray-600 border-gray-200',
                        };
                        $pbBorder = $pb->status === 'pending_cancellation' ? 'border-orange-300' : 'border-amber-200';
                    @endphp
                    <div wire:key="room-priority-{{ $pb->id }}"
                         wire:click="openPriorityDetail({{ $pb->id }})"
                         class="bg-white border {{ $pbBorder }} rounded-xl p-4 flex items-start gap-3 shadow-sm hover:shadow-md hover:border-amber-300 cursor-pointer transition-all group">
                        {{-- Icon --}}
                        <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/>
                            </svg>
                        </div>
                        {{-- Info --}}
                        <div class="flex-1 min-w-0 space-y-0.5">
                            <p class="text-sm font-semibold text-gray-900 truncate group-hover:text-amber-800 transition-colors">
                                {{ $pb->meeting_title }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $pb->room?->room_name ?? '—' }} &bull;
                                {{ \Carbon\Carbon::parse($pb->date)->format('d M Y') }} &bull;
                                {{ \Carbon\Carbon::parse($pb->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($pb->end_time)->format('H:i') }}
                            </p>
                            <p class="text-[11px] text-amber-600 font-medium">By: {{ $pb->manager?->full_name ?? '—' }}</p>
                            @if($pb->status === 'pending_cancellation')
                                <p class="text-[11px] text-orange-600 flex items-center gap-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    Conflicts with booking #{{ $pb->cancels_booking_id }}
                                </p>
                            @endif
                        </div>
                        {{-- Status + chevron --}}
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <span class="text-[10px] font-bold px-2 py-1 rounded-full border {{ $pbColor }}">
                                {{ $pb->statusLabel() }}
                            </span>
                            <svg class="w-3.5 h-3.5 text-amber-400 group-hover:text-amber-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

    </main>

    {{-- Priority Room Booking Detail Modal --}}
    @if($showPriorityDetailModal && $priorityDetailBooking)
    @php $pb = $priorityDetailBooking; @endphp
    <div class="fixed inset-0 z-[200] flex items-center justify-center p-4" wire:key="room-priority-detail-modal">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="closePriorityDetail"></div>
        <div class="relative w-full max-w-lg bg-white border border-amber-200 rounded-2xl shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-amber-200 bg-amber-50/70">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Priority Room Booking</p>
                        <p class="text-[11px] text-amber-700">Submitted by manager</p>
                    </div>
                </div>
                <button wire:click="closePriorityDetail" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-100 text-gray-500 hover:text-gray-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">
                {{-- Status badge --}}
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</span>
                    @php
                        $badgeClass = match($pb->status) {
                            'approved'            => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'pending_receipt'      => 'bg-amber-100 text-amber-700 border-amber-200',
                            'pending_cancellation' => 'bg-orange-100 text-orange-700 border-orange-200',
                            default                => 'bg-gray-100 text-gray-600 border-gray-200',
                        };
                    @endphp
                    <span class="text-xs font-bold px-3 py-1 rounded-full border {{ $badgeClass }}">
                        {{ $pb->statusLabel() }}
                    </span>
                </div>

                {{-- Booking details grid --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50/50 divide-y divide-gray-100">
                    <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Room</p>
                            <p class="font-semibold text-gray-900">{{ $pb->room?->room_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Meeting Title</p>
                            <p class="font-semibold text-gray-900">{{ $pb->meeting_title ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Date</p>
                            <p class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($pb->date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Time</p>
                            <p class="font-semibold text-gray-900">
                                {{ \Carbon\Carbon::parse($pb->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($pb->end_time)->format('H:i') }}
                            </p>
                        </div>
                        @if($pb->number_of_attendees)
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Attendees</p>
                            <p class="font-semibold text-gray-900">{{ $pb->number_of_attendees }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Requested by</p>
                            <p class="font-semibold text-amber-700">{{ $pb->manager?->full_name ?? '—' }}</p>
                        </div>
                        @if($pb->special_notes)
                        <div class="col-span-2">
                            <p class="text-gray-400 font-medium mb-0.5 uppercase tracking-wider text-[10px]">Special Notes</p>
                            <p class="font-semibold text-gray-900">{{ $pb->special_notes }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Conflict info --}}
                    @if($pb->cancels_booking_id && $pb->cancelledBooking)
                    <div class="px-4 py-3 bg-orange-50/60">
                        <p class="text-[11px] font-semibold text-orange-700 mb-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            Conflicting Booking
                        </p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Booking #</p>
                                <p class="font-semibold text-gray-900">#{{ $pb->cancels_booking_id }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Title</p>
                                <p class="font-semibold text-gray-900">{{ $pb->cancelledBooking->meeting_title ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Rejection reason (if denied) --}}
                @if($pb->rejection_reason)
                <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl bg-red-50 border border-red-200">
                    <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>
                        <p class="text-[11px] font-semibold text-red-700 mb-0.5">Reason for denial</p>
                        <p class="text-xs text-red-600">{{ $pb->rejection_reason }}</p>
                    </div>
                </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50/50 flex justify-end">
                <button wire:click="closePriorityDetail" class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
