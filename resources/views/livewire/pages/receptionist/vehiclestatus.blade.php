<div class="min-h-screen bg-gray-50" wire:poll.5000ms.keep-alive>
    @php
    use Carbon\Carbon;

    if (!function_exists('fmtDate')) {
        function fmtDate($v) {
            try { return $v ? Carbon::parse($v)->format('d M Y') : '—'; }
            catch (\Throwable) { return '—'; }
        }
    } 
    if (!function_exists('fmtTime')) {
        function fmtTime($v) {
            try { return $v ? Carbon::parse($v)->format('H.i') : '—'; }
            catch (\Throwable) {
                if (is_string($v)) {
                    if (preg_match('/^\d{2}:\d{2}/', $v)) return str_replace(':','.', substr($v,0,5));
                    if (preg_match('/^\d{2}\.\d{2}/', $v)) return substr($v,0,5);
                }
                return '—';
            }
        }
    }

    $card  = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    $label = 'block text-sm font-medium text-gray-700 mb-2';
    $input = 'w-full h-10 px-3 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition';
    $chip  = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-gray-100 text-xs';
    $icoAvatar = 'w-10 h-10 bg-[#4E653D] rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0 overflow-hidden relative';
    @endphp

    <style>
        :root { color-scheme: light; }
        select, option { color:#111827 !important; background:#ffffff !important; -webkit-text-fill-color:#111827 !important; }
        option:checked { background:#e5e7eb !important; color:#111827 !important; }
    </style>

    <main class="px-4 sm:px-6 py-6 space-y-6">
        {{-- HERO --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#CDDEA7]/10 rounded-xl flex items-center justify-center backdrop-blur-sm border border-[#CDDEA7]/20">
                            <svg class="w-6 h-6 text-[#CDDEA7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold">{{ __('app.vehicle_status_title') }}</h2>
                            <p class="text-sm text-[#CDDEA7]/80">{{ __('app.vehicle_status_sub') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-[#CDDEA7]/90 cursor-pointer">
                            <input type="checkbox" wire:model.live="includeDeleted"
                                   class="rounded border-[#CDDEA7]/30 bg-[#CDDEA7]/10 focus:ring-[#CDDEA7]/40 text-[#CDDEA7] cursor-pointer">
                            <span>{{ __('app.include_deleted') }}</span>
                        </label>

                        {{-- MOBILE FILTER BUTTON --}}
                        <button type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-[#CDDEA7]/10 text-xs font-medium border border-[#CDDEA7]/30 hover:bg-[#CDDEA7]/20 md:hidden"
                                wire:click="openFilterModal">
                            <x-heroicon-o-funnel class="w-4 h-4"/>
                            <span>{{ __('app.filter') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
            {{-- LIST --}}
            <section class="{{ $card }} md:col-span-3">
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('app.vehicle_bookings_list') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('app.vehicle_bookings_sub') }}</p>
                        </div>

                        {{-- Tabs + View Mode Toggle --}}
                        <div class="flex flex-wrap items-center gap-3 self-start sm:self-auto">
                            {{-- Tabs --}}
                            <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium">
                                @foreach(['pending'=>__('app.pending'),'approved'=>__('app.approved'),'on_progress'=>__('app.on_progress'),'returned'=>__('app.returned')] as $key=>$lbl)
                                    <button type="button"
                                            wire:click="$set('statusTab','{{ $key }}')"
                                            class="px-3.5 py-1 rounded-full transition {{ $statusTab === $key ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                        {{ $lbl }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Layout Toggler --}}
                            <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg shrink-0 border border-gray-200/50">
                                <button type="button" 
                                        wire:click="setViewMode('card')" 
                                        class="p-1.5 rounded-md transition-all {{ $viewMode === 'card' ? 'bg-white text-gray-800 shadow-sm border border-gray-200/40' : 'text-gray-400 hover:text-gray-600' }}"
                                        title="Card View">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </button>
                                <button type="button" 
                                        wire:click="setViewMode('table')" 
                                        class="p-1.5 rounded-md transition-all {{ $viewMode === 'table' ? 'bg-white text-gray-800 shadow-sm border border-gray-200/40' : 'text-gray-400 hover:text-gray-600' }}"
                                        title="Table View">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Vehicle Filter Badge Under Title --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs mt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if(!is_null($vehicleFilter))
                                @php
                                    $activeVeh = $vehicles->firstWhere('vehicle_id', $vehicleFilter);
                                    $activeVehLabel = $activeVeh ? ($activeVeh->name ?? $activeVeh->plate_number) : 'Unknown';
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                    <x-heroicon-o-truck class="w-3.5 h-3.5"/>
                                    <span>Vehicle: {{ $activeVehLabel }}</span>
                                    <button type="button" class="ml-1 hover:text-white" wire:click="clearVehicleFilter">×</button>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-dashed border-gray-300">
                                    <x-heroicon-o-funnel class="w-3.5 h-3.5"/>
                                    <span>{{ __('app.no_vehicle_filter') }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Filters (search, date, order) --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 bg-gray-50/30">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('app.search') }}</label>
                            <div class="relative">
                                <input type="text" class="{{ $input }} pl-9" placeholder="{{ __('app.search') }}..."
                                       wire:model.live.debounce.400ms="q">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <div class="relative">
                                <input type="date" wire:model.live="selectedDate" class="{{ $input }} pl-9">
                                <x-heroicon-o-calendar-days class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.sort') }}</label>
                            <div class="relative">
                                <select wire:model.live="sortFilter" class="{{ $input }} appearance-none pr-8 bg-white">
                                    <option value="recent">{{ __('app.sort_default') }}</option>
                                    <option value="oldest">{{ __('app.sort_oldest_first') }}</option>
                                    <option value="nearest">{{ __('app.sort_nearest') }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- LIST BODY --}}
                @if($bookings->isEmpty())
                    <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                        {{ __('app.no_data_filter') }}
                    </div>
                @else
                    <div class="px-4 sm:px-6 py-5 bg-gray-50/50">
                        @if($viewMode === 'card')
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4"
                                 x-on:booking-rejected.window="
                                    const el = $el.querySelector('[wire\\:key=\'booking-\' + $event.detail.id]') ||
                                               document.querySelector('[wire\\:key=\'booking-' + $event.detail.id + '\']');
                                    if (el) {
                                        el.style.transition = 'opacity 250ms ease, transform 250ms ease';
                                        el.style.opacity = '0';
                                        el.style.transform = 'scale(0.97)';
                                        setTimeout(() => el.remove(), 260);
                                    }
                                 ">
                            @forelse($bookings as $b)
                                @php
                                    $vehicleName = $vehicleMap[$b->vehicle_id] ?? 'Unknown';
                                    $avatarChar  = strtoupper(substr($vehicleName, 0, 1));
                                    $beforeC = $photoCounts[$b->vehiclebooking_id]['before'] ?? 0;
                                    $afterC  = $photoCounts[$b->vehiclebooking_id]['after']  ?? 0;
                                    $statusColors = [
                                        'pending'      => ['bg'=>'bg-amber-100','text'=>'text-amber-800','label'=>__('app.pending')],
                                        'approved'     => ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.approved')],
                                        'on_progress'  => ['bg'=>'bg-blue-100','text'=>'text-blue-800','label'=>__('app.on_progress')],
                                        'returned'     => ['bg'=>'bg-indigo-100','text'=>'text-indigo-800','label'=>__('app.returned')],
                                        'rejected'     => ['bg'=>'bg-rose-100','text'=>'text-rose-800','label'=>__('app.rejected')],
                                        'completed'    => ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.completed')],
                                    ];
                                    $statusStyle = $statusColors[$b->status] ?? ['bg'=>'bg-gray-100','text'=>'text-gray-800','label'=>ucfirst($b->status)];
                                @endphp

                                {{-- START: MODIFIED VEHICLE BOOKING CARD DESIGN --}}
                                <div wire:key="booking-{{ $b->vehiclebooking_id }}"
                                     class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 hover:shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
                                    
                                    <div class="space-y-3">
                                        <div class="flex items-start gap-4">
                                            {{-- 1. Avatar/Initial on the left --}}
                                            <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>
                                            
                                            <div class="flex-1 min-w-0">
                                                {{-- 2. TOP ROW: Title, Status, ID --}}
                                                <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                    <h4 class="font-semibold text-gray-900 text-base truncate pr-2 cursor-pointer"
                                                        wire:click="showDetails({{ $b->vehiclebooking_id }})">
                                                        {{ $b->purpose ? ucfirst($b->purpose) : 'Vehicle Booking' }}
                                                    </h4>
                                                    <div class="flex-shrink-0 flex items-center gap-2">
                                                        {{-- Status Badge --}}
                                                        <span class="text-[11px] px-2 py-0.5 rounded-full flex-shrink-0 {{ $statusStyle['bg'] }} {{ $statusStyle['text'] }}">
                                                            {{ $statusStyle['label'] }}
                                                        </span>
                                                        {{-- ID Chip --}}
                                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-50 text-gray-700 border border-gray-200 flex-shrink-0 font-medium">
                                                            #{{ $b->vehiclebooking_id }}
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- 3. MIDDLE SECTION: Vehicle, Date, Time, Borrower --}}
                                                <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                    {{-- Vehicle and Borrower --}}
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-gray-100 border border-gray-200">
                                                            <x-heroicon-o-truck class="w-3.5 h-3.5 text-gray-500 shrink-0"/>
                                                            <span class="font-medium text-gray-700">{{ $vehicleName }}</span>
                                                        </span>
                                                        
                                                        @if(!empty($b->borrower_name))
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-gray-100 border border-gray-200">
                                                                <x-heroicon-o-user class="w-3.5 h-3.5 text-gray-500 shrink-0"/>
                                                                <span class="font-medium text-gray-700">{{ $b->borrower_name }}</span>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Dates and Times --}}
                                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                                        <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-calendar class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span>{{ fmtDate($b->start_at) }}</span>
                                                        </span>
                                                        <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-clock class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span>{{ fmtTime($b->start_at) }}–{{ fmtTime($b->end_at) }}</span>
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- 4. BOTTOM LEFT: Photo Counts & Created Timestamp --}}
                                                <div class="text-[12px] text-gray-600 space-y-2">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-50 border border-gray-200 text-[11px]">
                                                            Before: <span class="font-semibold text-gray-800 pl-0.5">{{ $beforeC }}</span>
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-50 border border-gray-200 text-[11px]">
                                                            After: <span class="font-semibold text-gray-800 pl-0.5">{{ $afterC }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-500">
                                                        <x-heroicon-o-document-plus class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                        <span>{{ __('app.created') }}: {{ optional($b->created_at)->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</span>
                                                    </div>
                                                </div>

                                                {{-- Rejected Note --}}
                                                @if($b->reject_note && $b->status === 'rejected')
                                                    <div class="mt-2 text-xs text-rose-700 bg-rose-50 border border-rose-100 rounded-lg p-2">
                                                        <span class="font-medium">{{ __('app.reject_reason') }}:</span> {{ $b->reject_note }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 5. BOTTOM ACTIONS (Horizontally aligned and right justified) --}}
                                    <div class="pt-3 border-t border-gray-100 mt-4 flex justify-end gap-3 items-center">
                                        <span class="text-[11px] text-gray-500 mr-auto font-medium">No. {{ ($bookings->firstItem() ?? 1) + $loop->index }}</span>

                                        {{-- Actions based on Status --}}
                                        @if($b->status === 'pending')
                                            {{-- Reject Button (Soft Red Style) --}}
                                            <button type="button"
                                                    wire:click.stop="confirmReject({{ $b->vehiclebooking_id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmReject({{ $b->vehiclebooking_id }})"
                                                    class="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 disabled:opacity-60 transition">
                                                {{ __('app.reject') }}
                                            </button>
 
                                            {{-- Approve Button (Primary Style) --}}
                                            <button type="button"
                                                    wire:click.stop="approve({{ $b->vehiclebooking_id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="approve({{ $b->vehiclebooking_id }})"
                                                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm">
                                                {{ __('app.approve') }}
                                            </button>
                                        @elseif($b->status === 'on_progress')
                                            {{-- Mark Returned Button --}}
                                            <button type="button"
                                                    wire:click.stop="confirmMarkReturned({{ $b->vehiclebooking_id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmMarkReturned({{ $b->vehiclebooking_id }})"
                                                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm">
                                                {{ __('app.mark_returned') }}
                                            </button>
                                        @elseif($b->status === 'returned')
                                            {{-- Mark Done Button (Conditional styling based on after photos) --}}
                                            <div class="flex items-center gap-2">
                                                @if($afterC === 0)
                                                    <span class="text-[11px] text-gray-500">
                                                        {{ __('app.wait_after_photos') }}
                                                    </span>
                                                @endif
                                                <button type="button"
                                                        wire:click.stop="markDone({{ $b->vehiclebooking_id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="markDone({{ $b->vehiclebooking_id }})"
                                                        class="px-4 py-1.5 text-xs font-medium rounded-lg {{ $afterC === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed border border-gray-300' : 'bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 transition shadow-sm' }}"
                                                        @disabled($afterC === 0)>
                                                    {{ __('app.mark_done') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                {{-- END: MODIFIED VEHICLE BOOKING CARD DESIGN --}}
                            @empty
                                <div class="col-span-full text-center text-gray-500 text-sm py-6 bg-white border border-dashed border-gray-200 rounded-xl">
                                    {{ __('app.no_data_filter') }}
                                </div>
                            @endforelse
                            </div>
                        @else
                            {{-- TABLE VIEW MODE --}}
                            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                            <th class="px-6 py-3.5">#</th>
                                            <th class="px-6 py-3.5">{{ __('app.vehicle') }}</th>
                                            <th class="px-6 py-3.5">{{ __('app.borrower') }}</th>
                                            <th class="px-6 py-3.5">{{ __('app.purpose') }}</th>
                                            <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                            <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                            <th class="px-6 py-3.5">Photos</th>
                                            <th class="px-6 py-3.5 text-right">{{ __('app.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100"
                                           x-on:booking-rejected.window="
                                               const row = $el.querySelector('tr[data-booking-id=\'' + $event.detail.id + '\']');
                                               if (row) {
                                                   row.style.transition = 'opacity 250ms ease';
                                                   row.style.opacity = '0';
                                                   setTimeout(() => row.remove(), 260);
                                               }
                                           ">
                                        @forelse($bookings as $b)
                                            @php
                                                $vehicleName = $vehicleMap[$b->vehicle_id] ?? 'Unknown';
                                                $beforeC = $photoCounts[$b->vehiclebooking_id]['before'] ?? 0;
                                                $afterC  = $photoCounts[$b->vehiclebooking_id]['after']  ?? 0;
                                                $rowNo = ($bookings->firstItem() ?? 1) + $loop->index;
                                            @endphp
                                            <tr data-booking-id="{{ $b->vehiclebooking_id }}"
                                                class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400">#{{ $rowNo }}</td>
                                                <td class="px-6 py-4 font-semibold text-gray-900">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-7 h-7 bg-[#4E653D]/10 rounded flex items-center justify-center text-[#4E653D] font-bold text-xs shrink-0">
                                                            {{ substr($vehicleName, 0, 2) }}
                                                        </div>
                                                        <span>{{ $vehicleName }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">{{ $b->borrower_name ?? '—' }}</td>
                                                <td class="px-6 py-4 max-w-xs truncate font-medium text-gray-950" title="{{ $b->purpose }}">{{ $b->purpose ?? '—' }}</td>
                                                <td class="px-6 py-4 font-medium">{{ fmtDate($b->start_at) }}</td>
                                                <td class="px-6 py-4 font-mono text-xs">{{ fmtTime($b->start_at) }}–{{ fmtTime($b->end_at) }}</td>
                                                <td class="px-6 py-4 text-xs text-gray-500">
                                                    <span class="inline-flex gap-1.5">
                                                        <span class="px-1.5 py-0.5 rounded bg-gray-50 border border-gray-200">{{ __('app.borrow_date') }}: {{ $beforeC }}</span>
                                                        <span class="px-1.5 py-0.5 rounded bg-gray-50 border border-gray-200">{{ __('app.return_date') }}: {{ $afterC }}</span>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex items-center justify-end gap-2 font-medium">
                                                        @if($b->status === 'pending')
                                                            <button type="button" wire:click.stop="confirmReject({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition">
                                                                {{ __('app.reject') }}
                                                            </button>
                                                            <button type="button" wire:click.stop="approve({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition">
                                                                {{ __('app.approve') }}
                                                            </button>
                                                        @elseif($b->status === 'on_progress' || $b->status === 'late_return')
                                                            @php $overdueTable = $this->overdueDuration($b); @endphp
                                                            @if($overdueTable)
                                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full mr-1">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                                    +{{ $overdueTable }} late
                                                                </span>
                                                            @endif
                                                            @if(str_contains($b->notes ?? '', '[Late Return]'))
                                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full shadow-sm mr-1">
                                                                    Late Return
                                                                </span>
                                                            @endif
                                                            <button type="button" wire:click.stop="confirmMarkReturned({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition">
                                                                {{ __('app.mark_returned') }}
                                                            </button>
                                                        @elseif($b->status === 'returned')
                                                            <button type="button" wire:click.stop="markDone({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg {{ $afterC === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed border border-gray-300' : 'bg-[#4E653D] text-white hover:bg-[#354C2B] transition' }}"
                                                                @disabled($afterC === 0)>
                                                                {{ __('app.mark_done') }}
                                                            </button>
                                                        @else
                                                            <span class="text-xs text-gray-400 capitalize">{{ str_replace('_', ' ', $b->status) }}</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">{{ __('app.no_data_filter') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Pagination --}}
                @if(method_exists($bookings, 'links'))
                    <div class="px-4 sm:px-6 py-4 bg-white border-t border-gray-200">
                        <div class="flex justify-center">
                            {{ $bookings->links() }}
                        </div>
                    </div>
                @endif
            </section>

            {{-- SIDEBAR: vehicle filter --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                <section class="{{ $card }}">
                    <div class="px-4 py-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('app.filter_by_vehicle') }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ __('app.click_to_filter') }}</p>
                    </div>

                    <div class="px-4 py-3 max-h-64 overflow-y-auto">
                        <button type="button"
                                wire:click="clearVehicleFilter"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium {{ is_null($vehicleFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] shadow-sm' : 'text-gray-800 hover:bg-gray-100' }}">
                            <span class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">All</span>
                                <span>{{ __('app.all_vehicles') }}</span>
                            </span>
                            @if(is_null($vehicleFilter))
                                <span class="text-[10px] uppercase tracking-wide opacity-80">{{ __('app.active') }}</span>
                            @endif
                        </button>

                        <div class="mt-2 space-y-1.5">
                            @forelse($vehicles as $v)
                                @php
                                    $vLabel = $v->name ?? $v->plate_number ?? ('#'.$v->vehicle_id);
                                    $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                                @endphp
                                <button type="button"
                                        wire:click="selectVehicle({{ $v->vehicle_id }})"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] shadow-sm' : 'text-gray-800 hover:bg-gray-100' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">
                                            {{ substr($vLabel, 0, 2) }}
                                        </span>
                                        <span class="truncate">{{ $vLabel }}</span>
                                    </span>
                                    @if($active)
                                        <span class="text-[10px] uppercase tracking-wide opacity-80">{{ __('app.active') }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="text-xs text-gray-500">{{ __('app.no_vehicle_data_filter') }}</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- MOBILE FILTER MODAL --}}
        @if($showFilterModal)
            <div class="fixed inset-0 z-50 md:hidden flex items-end">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeFilterModal"></div>
                <div class="relative w-full bg-card rounded-t-2xl shadow-2xl max-h-[85vh] overflow-hidden flex flex-col border-t border-border">
                    <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-muted/10">
                        <div>
                            <h3 class="text-sm font-semibold tracking-tight text-foreground">{{ __('app.filter_by_vehicle') }}</h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('app.filter_by_vehicle_history') }}</p>
                        </div>
                        <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition" wire:click="closeFilterModal">✕</button>
                    </div>

                    <div class="p-5 space-y-5 overflow-y-auto flex-1 bg-background">
                        {{-- All vehicles option --}}
                        <button type="button"
                                wire:click="clearVehicleFilter"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium
                                    {{ is_null($vehicleFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] shadow-sm' : 'text-gray-800 hover:bg-gray-100' }}">
                            <span class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">All</span>
                                <span>{{ __('app.all_vehicles') }}</span>
                            </span>
                            @if(is_null($vehicleFilter))
                                <span class="text-[10px] uppercase tracking-wide opacity-80">{{ __('app.active') }}</span>
                            @endif
                        </button>

                        <div class="space-y-1.5">
                            @foreach($vehicles as $v)
                                @php
                                    $vLabel = $v->name ?? $v->plate_number ?? ('#'.$v->vehicle_id);
                                    $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                                @endphp
                                <button type="button"
                                        wire:click="selectVehicle({{ $v->vehicle_id }})"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] shadow-sm' : 'text-gray-800 hover:bg-gray-100' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">
                                            {{ substr($vLabel, 0, 2) }}
                                        </span>
                                        <span class="truncate">{{ $vLabel }}</span>
                                    </span>
                                    @if($active)
                                        <span class="text-[10px] uppercase tracking-wide opacity-80">{{ __('app.active') }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-5 border-t border-border bg-muted/10">
                        <button type="button" class="w-full h-10 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition shadow-sm"
                                wire:click="closeFilterModal">
                            {{ __('app.close') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </main>

    {{-- DETAIL MODAL --}}
    @if($showDetailModal && $selectedBooking)
        <div x-data="{ show: @entangle('showDetailModal') }"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display: none;">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeDetailModal"></div>

            {{-- Modal Content --}}
            <div x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative z-10 w-full max-w-3xl bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">

                {{-- Header --}}
                <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#4E653D]/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-[#4E653D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-foreground tracking-tight">
                                {{ __('app.detail') }} Booking #{{ $selectedBooking->vehiclebooking_id }}
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ $selectedBooking->purpose }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition">✕</button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-6 overflow-y-auto flex-1">
                    {{-- Detail Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.borrower') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ $selectedBooking->borrower_name }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.vehicle') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ $vehicleMap[$selectedBooking->vehicle_id] ?? 'N/A' }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.destination_col') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ $selectedBooking->destination ?? 'N/A' }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.purpose_type_col') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ ucfirst($selectedBooking->purpose_type) }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.start_col') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ fmtDate($selectedBooking->start_at) }}, {{ fmtTime($selectedBooking->start_at) }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.end_col') }}</span>
                            <span class="text-sm font-semibold text-foreground">{{ fmtDate($selectedBooking->end_at) }}, {{ fmtTime($selectedBooking->end_at) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-border"></div>

                    {{-- Photos Before --}}
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-foreground mb-3 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{{ __('app.photo_before') }}</span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @forelse($selectedPhotos['before'] as $photo)
                                <div class="group relative rounded-xl border border-border bg-muted/20 overflow-hidden shadow-sm hover:border-primary/30 transition-colors p-2">
                                    <a href="{{ asset('storage/' . $photo->photo_path) }}" target="_blank" class="block rounded-lg overflow-hidden border border-border">
                                        <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Photo Before" class="w-full h-40 object-cover group-hover:scale-[1.02] transition-transform duration-300">
                                    </a>
                                    <span class="text-[11px] text-muted-foreground mt-2 block pl-1 font-medium">
                                        {{ __('app.uploaded_by') }}: {{ $photo->user->full_name ?? 'N/A' }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-muted-foreground col-span-full italic">{{ __('app.no_before_photos') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="border-t border-border"></div>

                    {{-- Photos After --}}
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-foreground mb-3 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            <span>{{ __('app.photo_after') }}</span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @forelse($selectedPhotos['after'] as $photo)
                                <div class="group relative rounded-xl border border-border bg-muted/20 overflow-hidden shadow-sm hover:border-primary/30 transition-colors p-2">
                                    <a href="{{ asset('storage/' . $photo->photo_path) }}" target="_blank" class="block rounded-lg overflow-hidden border border-border">
                                        <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Photo After" class="w-full h-40 object-cover group-hover:scale-[1.02] transition-transform duration-300">
                                    </a>
                                    <span class="text-[11px] text-muted-foreground mt-2 block pl-1 font-medium">
                                        {{ __('app.uploaded_by') }}: {{ $photo->user->full_name ?? 'N/A' }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-muted-foreground col-span-full italic">{{ __('app.no_after_photos') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="border-t border-border px-6 py-4 flex justify-end bg-muted/10">
                    <button type="button"
                            wire:click="closeDetailModal"
                            class="h-9 px-4 rounded-lg bg-[#4A2F24]/10 text-[#4A2F24] border border-[#4A2F24]/20 hover:bg-[#4A2F24]/20 transition inline-flex items-center gap-1.5">
                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                        <span>{{ __('app.close') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
    
    {{-- REJECT RESULT POPUP --}}
    <div x-data="{ show: @entangle('showRejectResult').live }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4"
         style="display: none;">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
             @click="$wire.closeRejectResult()"></div>

        {{-- Popup --}}
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-90"
             class="relative z-10 w-full max-w-sm bg-white border border-gray-200 shadow-2xl rounded-2xl overflow-hidden text-center">

            {{-- Top accent stripe --}}
            <div class="h-1.5 w-full {{ $rejectResultType === 'success' ? 'bg-gradient-to-r from-emerald-500 to-emerald-400' : 'bg-gradient-to-r from-rose-500 to-rose-400' }}"></div>

            <div class="p-8 flex flex-col items-center gap-4">
                {{-- Icon circle --}}
                <div class="w-16 h-16 rounded-full flex items-center justify-center
                    {{ $rejectResultType === 'success'
                        ? 'bg-emerald-50 border-2 border-emerald-200'
                        : 'bg-rose-50 border-2 border-rose-200' }}">
                    @if($rejectResultType === 'success')
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        <svg class="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @endif
                </div>

                {{-- Title --}}
                <div class="space-y-1.5">
                    <h3 class="text-lg font-bold {{ $rejectResultType === 'success' ? 'text-emerald-800' : 'text-rose-800' }}">
                        {{ $rejectResultTitle }}
                    </h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ $rejectResultMessage }}
                    </p>
                </div>

                {{-- Booking ID badge --}}
                @if($rejectResultBookingId)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                        {{ $rejectResultType === 'success'
                            ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                            : 'bg-rose-100 text-rose-700 border border-rose-200' }}">
                        <x-heroicon-o-hashtag class="w-3 h-3"/>
                        Booking #{{ $rejectResultBookingId }}
                    </span>
                @endif

                {{-- Close button --}}
                <button type="button"
                        wire:click="closeRejectResult"
                        class="mt-1 w-full h-10 rounded-xl font-semibold text-sm transition
                            {{ $rejectResultType === 'success'
                                ? 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500/30'
                                : 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-2 focus:ring-rose-500/30' }}">
                    {{ __('app.close') }}
                </button>
            </div>
        </div>
    </div>

    {{-- REJECT MODAL --}}
    <div x-data="{ show: @entangle('showRejectModal').live }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display: none;">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300"
             @click="$wire.cancelReject()"></div>

        {{-- Modal Content --}}
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative z-10 w-full max-w-lg bg-white border border-gray-200 shadow-2xl rounded-2xl overflow-hidden">

            <form wire:submit.prevent="submitReject">
                {{-- Header --}}
                <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-100 flex items-center justify-center">
                            <x-heroicon-o-x-circle class="w-4 h-4 text-rose-700" />
                        </div>
                        <h3 class="text-base font-bold text-gray-900 tracking-tight">
                            {{ __('app.reject_booking_title') }} #{{ $rejectId }}
                        </h3>
                    </div>
                    <button type="button"
                            @click="$wire.cancelReject()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">✕</button>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-4">
                    <p class="text-xs text-gray-500 leading-relaxed">
                        {{ __('app.reject_vehicle_reason') }}
                    </p>

                    <div>
                        <label for="reject-note" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                            {{ __('app.reject_reason_ph') }} <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="reject-note"
                                  wire:model.defer="rejectNote"
                                  rows="4"
                                  placeholder="{{ __('app.reject_reason_example') }}"
                                  class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400 transition-all resize-none @error('rejectNote') border-rose-400 @enderror"
                                  required></textarea>
                        @error('rejectNote')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Footer --}}
                <div class="border-t border-gray-200 px-6 py-4 flex items-center justify-end gap-3 bg-gray-50/30">
                    <button type="button"
                            @click="$wire.cancelReject()"
                            class="h-9 px-4 rounded-lg bg-[#4A2F24]/10 text-[#4A2F24] border border-[#4A2F24]/20 hover:bg-[#4A2F24]/20 transition inline-flex items-center gap-1.5">
                        <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                        <span>{{ __('app.cancel') }}</span>
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="h-9 px-4 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:ring-2 focus:ring-rose-500/20 transition shadow-sm inline-flex items-center gap-1.5">
                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                        <span>{{ __('app.reject_booking_title') }}</span>
                    </button>
                </div>
            </form>
         </div>
    </div>

    {{-- LATE REASON MODAL --}}
    <div x-data="{ show: @entangle('showLateReasonModal').live }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
         style="display: none;">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300" wire:click="closeLateReasonModal"></div>

        {{-- Modal --}}
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative z-10 w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-2xl overflow-hidden flex flex-col">

            {{-- Header --}}
            <div class="px-5 py-4 border-b border-gray-200 bg-red-50 text-red-900 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-red-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold tracking-tight">Late Return (Over 3 Hours)</h3>
                    <p class="text-xs text-red-700/80 mt-0.5">Please provide a reason before completing.</p>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-5">
                <form wire:submit.prevent="submitLateReason">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Reason</label>
                            <textarea wire:model="lateReasonText" rows="3"
                                      class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500/20 shadow-sm"
                                      placeholder="Explain the reason for the late return..." required></textarea>
                            @error('lateReasonText')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="closeLateReasonModal"
                                class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                            Cancel
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition disabled:opacity-50">
                            Submit Reason & Mark Done
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>