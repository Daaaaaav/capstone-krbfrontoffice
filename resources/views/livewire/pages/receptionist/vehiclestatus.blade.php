<div class="min-h-screen bg-background" wire:poll.5000ms.keep-alive x-data="{ showFilterModal: false }">
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
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.vehicle_status_title') }}"
            subtitle="{{ __('app.vehicle_status_sub') }}">
            <x-slot:actions>
                <button type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-secondary text-secondary-foreground text-xs font-medium border border-border hover:bg-secondary/80 md:hidden transition"
                        @click="showFilterModal = true">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    <span>{{ __('app.filter') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

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
                                @foreach(['pending'=>__('app.pending'),'approved'=>__('app.approved'),'on_progress'=>__('app.on_progress')] as $key=>$lbl)
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
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: @entangle('sortFilter').live,
                                    options: [
                                        { id: 'recent', label: '{{ __('app.sort_default') }}' },
                                        { id: 'oldest', label: '{{ __('app.sort_oldest_first') }}' },
                                        { id: 'nearest', label: '{{ __('app.sort_nearest') }}' }
                                    ],
                                    get items() {
                                        const q = this.search.toLowerCase().trim();
                                        return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const found = this.options.find(i => i.id === this.selectedId);
                                        return found ? found.label : '';
                                    },
                                    select(id) {
                                        this.selectedId = id;
                                        this.open = false;
                                    }
                                }"
                                x-init="
                                    if (!selectedId) selectedId = 'recent';
                                    $watch('selectedId', () => { search = ''; });
                                "
                                class="relative"
                                @click.outside="open = false"
                            >
                                <div class="relative">
                                    <input
                                        type="text"
                                        x-model="search"
                                        @focus="open = true"
                                        @input="open = true"
                                        @keydown.escape="open = false"
                                        @keydown.enter.prevent="items.length === 1 && select(items[0].id)"
                                        autocomplete="off"
                                        :placeholder="selectedLabel || '{{ __('app.sort_default') }}'"
                                        class="{{ $input }} pr-8 cursor-pointer"
                                        :class="{ 'placeholder-gray-900': selectedId, 'placeholder-gray-400': !selectedId }"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                                <ul
                                    x-show="open && items.length > 0"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg text-sm"
                                    style="display:none"
                                >
                                    <template x-for="item in items" :key="item.id">
                                        <li
                                            @click="select(item.id)"
                                            :class="selectedId === item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                            class="px-3.5 py-2.5 transition-colors"
                                            x-text="item.label"
                                        ></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- LIST BODY --}}
                {{-- ── MANAGER PRIORITY VEHICLE BOOKINGS ── --}}
                @if(isset($priorityVehicleBookings) && $priorityVehicleBookings->isNotEmpty())
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b-2 border-blue-200 bg-blue-50/40">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-700">Manager Priority Vehicle Bookings</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">{{ $priorityVehicleBookings->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 pb-4">
                        @foreach($priorityVehicleBookings as $pvb)
                        @php
                            $pvbBadge = match($pvb->status) {
                                'approved'   => 'bg-emerald-100 text-emerald-700',
                                'pending_receipt','pending_cancellation' => 'bg-amber-100 text-amber-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <div wire:key="priority-veh-{{ $pvb->id }}" class="bg-white border border-blue-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
                            <div class="w-9 h-9 rounded-lg bg-blue-500/15 flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                            </div>
                            <div class="flex-1 min-w-0 space-y-0.5">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $pvb->vehicle?->name ?? '—' }}{{ $pvb->vehicle?->plate_number ? ' ('.$pvb->vehicle->plate_number.')' : '' }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $pvb->borrower_name }} &bull;
                                    {{ $pvb->start_at?->format('d M Y H:i') }} – {{ $pvb->end_at?->format('H:i') }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">{{ $pvb->purpose }}</p>
                                <p class="text-[11px] text-blue-600 font-medium">By: {{ $pvb->manager?->full_name ?? '—' }}</p>
                                @if($pvb->status === 'pending_cancellation')
                                    <p class="text-[11px] text-orange-600">Awaiting cancellation approval for booking #{{ $pvb->cancels_booking_id }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full {{ $pvbBadge }}">
                                {{ $pvb->statusLabel() }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

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
                                    $statusColors = [
                                        'pending'      => ['bg'=>'bg-amber-100','text'=>'text-amber-800','label'=>__('app.pending')],
                                        'approved'     => ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.approved')],
                                        'on_progress'  => ['bg'=>'bg-blue-100','text'=>'text-blue-800','label'=>__('app.on_progress')],
                                        'returned'     => ['bg'=>'bg-indigo-100','text'=>'text-indigo-800','label'=>__('app.returned')],
                                        'rejected'     => ['bg'=>'bg-rose-100','text'=>'text-rose-800','label'=>__('app.rejected')],
                                        'completed'    => ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.completed')],
                                        'late_return'  => ['bg'=>'bg-blue-100','text'=>'text-blue-800','label'=>__('app.on_progress')],
                                    ];
                                    $statusStyle = $statusColors[$b->status] ?? ['bg'=>'bg-gray-100','text'=>'text-gray-800','label'=>ucfirst($b->status)];
                                    $overdue = $b->status === 'late_return' ? $this->overdueDuration($b) : null;
                                @endphp

                                {{-- START: MODIFIED VEHICLE BOOKING CARD DESIGN --}}
                                <div wire:key="booking-{{ $b->vehiclebooking_id }}"
                                     class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                    
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
                                                        {{-- Late return overdue marker on badge row --}}
                                                        @if($b->status === 'late_return')
                                                            @php $overdue = $this->overdueDuration($b); @endphp
                                                            @if($overdue)
                                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full flex-shrink-0">
                                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                                    +{{ $overdue }} late
                                                                </span>
                                                            @endif
                                                        @endif
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
                                                    <div class="flex flex-col gap-y-1.5 mt-1">
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-calendar class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span class="text-gray-500 text-xs w-9">Start</span>
                                                            <span>{{ strtolower(\Carbon\Carbon::parse($b->start_at)->format('d M Y')) }} - {{ \Carbon\Carbon::parse($b->start_at)->format('H.i') }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-clock class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span class="text-gray-500 text-xs w-9">End</span>
                                                            <span>{{ strtolower(\Carbon\Carbon::parse($b->end_at)->format('d M Y')) }} - {{ \Carbon\Carbon::parse($b->end_at)->format('H.i') }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- 4. BOTTOM LEFT: Created Timestamp --}}
                                                <div class="text-[12px] text-gray-600 space-y-2">
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
                                    
                                    {{-- 5. BOTTOM ACTIONS (Horizontally aligned and right justified) --}}
                                    <div class="pt-3 border-t border-gray-100 flex justify-end gap-3 items-center">
                                        <span class="text-[11px] text-gray-500 mr-auto">No. {{ ($bookings->firstItem() ?? 1) + $loop->index }}</span>

                                        <button type="button"
                                                wire:click.stop="showDetails({{ $b->vehiclebooking_id }})"
                                                class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                            Detail
                                        </button>

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
                                        @elseif($b->status === 'on_progress' || $b->status === 'late_return')
                                            {{-- Overdue badge (only when past end_at) --}}
                                            @php $overdue = $this->overdueDuration($b); @endphp
                                            @if($overdue)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                    +{{ $overdue }} late
                                                </span>
                                            @endif
                                            {{-- Mark Completed Button --}}
                                            <button type="button"
                                                    wire:click.stop="markReturned({{ $b->vehiclebooking_id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="markReturned({{ $b->vehiclebooking_id }})"
                                                    class="px-4 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm">
                                                {{ __('app.mark_done') }}
                                            </button>
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
                                            <th class="px-6 py-3.5">Start Time</th>
                                            <th class="px-6 py-3.5">End Time</th>
                                            <th class="px-6 py-3.5">{{ __('app.actions') }}</th>
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
                                                $rowNo = ($bookings->firstItem() ?? 1) + $loop->index;
                                            @endphp
                                            <tr data-booking-id="{{ $b->vehiclebooking_id }}"
                                                class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $rowNo }}</td>
                                                <td class="h-12 px-6 py-4 font-semibold text-gray-900">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <div class="w-7 h-7 bg-[#4E653D]/10 rounded flex items-center justify-center text-[#4E653D] font-bold text-xs shrink-0">
                                                            {{ substr($vehicleName, 0, 2) }}
                                                        </div>
                                                        <span>{{ $vehicleName }}</span>
                                                    </div>
                                                </td>
                                                <td class="h-12 px-6 py-0 ">{{ $b->borrower_name ?? '—' }}</td>
                                                <td class="h-12 px-6 py-4 max-w-xs truncate font-medium text-gray-950" title="{{ $b->purpose }}">{{ $b->purpose ?? '—' }}</td>
                                                <td class="h-12 px-6 py-4 font-medium whitespace-nowrap text-xs">{{ strtolower(\Carbon\Carbon::parse($b->start_at)->format('d M Y')) }} - {{ \Carbon\Carbon::parse($b->start_at)->format('H.i') }}</td>
                                                <td class="h-12 px-6 py-4 font-medium whitespace-nowrap text-xs">{{ strtolower(\Carbon\Carbon::parse($b->end_at)->format('d M Y')) }} - {{ \Carbon\Carbon::parse($b->end_at)->format('H.i') }}</td>
                                                <td class="h-12 px-6 py-4">
                                                    <div class="flex items-center justify-end gap-2 font-medium">
                                                        <button type="button" wire:click.stop="showDetails({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                                                            Detail
                                                        </button>
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
                                                            <button type="button" wire:click.stop="markReturned({{ $b->vehiclebooking_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition">
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
                                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('app.no_data_filter') }}</td>
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
                        <div class="w-full">
                            {{ $bookings->links() }}
                        </div>
                    </div>
                @endif
            </section>

            {{-- SIDEBAR: vehicle filter --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                <section class="{{ $card }}">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_vehicle') }}</p>
                    </div>

                    <div class="p-4 space-y-4 bg-white">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ __('app.vehicle') }}</label>
                            <div class="px-1 py-1 max-h-80 overflow-y-auto">
                                <button type="button"
                                        wire:click="clearVehicleFilter"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors mb-1.5
                                            {{ is_null($vehicleFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">All</span>
                                        <span>{{ __('app.all_vehicles') }}</span>
                                    </span>
                                </button>

                                <div class="mt-2 space-y-1.5">
                                    @forelse($vehicles as $v)
                                        @php
                                            $vLabel = $v->name ?? $v->plate_number ?? ('#'.$v->vehicle_id);
                                            $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                                        @endphp
                                        <button type="button"
                                                wire:click="selectVehicle({{ $v->vehicle_id }})"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                                    {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                            <span class="flex items-center gap-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                                    {{ substr($vLabel, 0, 2) }}
                                                </span>
                                                <span class="truncate font-medium">{{ $vLabel }}</span>
                                            </span>
                                        </button>
                                    @empty
                                        <p class="text-xs text-gray-500">{{ __('app.no_vehicle_data_filter') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- MOBILE FILTER MODAL --}}
        <div x-show="showFilterModal" class="fixed inset-0 z-50 md:hidden flex items-end" x-cloak style="display: none;">
            <div x-show="showFilterModal" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showFilterModal = false"></div>
            <div x-show="showFilterModal" 
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="relative w-full bg-white rounded-t-2xl shadow-2xl max-h-[85vh] overflow-hidden flex flex-col border-t border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50/50">
                    <div>
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">{{ __('app.filter_by_vehicle') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_vehicle_history') }}</p>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-200 transition" @click="showFilterModal = false">✕</button>
                </div>

                <div class="p-5 space-y-5 overflow-y-auto flex-1 bg-white">
                    {{-- All vehicles option --}}
                    <button type="button"
                            wire:click="clearVehicleFilter"
                            @click="showFilterModal = false"
                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors
                                {{ is_null($vehicleFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">All</span>
                            <span>{{ __('app.all_vehicles') }}</span>
                        </span>
                    </button>

                    <div class="space-y-1.5">
                        @foreach($vehicles as $v)
                            @php
                                $vLabel = $v->name ?? $v->plate_number ?? ('#'.$v->vehicle_id);
                                $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                            @endphp
                            <button type="button"
                                    wire:click="selectVehicle({{ $v->vehicle_id }})"
                                    @click="showFilterModal = false"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">
                                        {{ substr($vLabel, 0, 2) }}
                                    </span>
                                    <span class="truncate">{{ $vLabel }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
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
             class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
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
                 class="relative z-10 w-full max-w-3xl bg-white rounded-2xl border border-gray-200 shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">

                {{-- Header --}}
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <svg class="w-4 h-4 text-[#CDDEA7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold tracking-tight">
                                {{ __('app.detail') }} Booking #{{ $selectedBooking->vehiclebooking_id }}
                            </h3>
                            <p class="text-xs text-[#CDDEA7]/80 mt-0.5">
                                {{ $selectedBooking->purpose }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition">✕</button>
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
         class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4"
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
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
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
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <x-heroicon-o-x-circle class="w-4 h-4 text-[#CDDEA7]" />
                        </div>
                        <h3 class="text-base font-bold tracking-tight">
                            {{ __('app.reject_booking_title') }} #{{ $rejectId }}
                        </h3>
                    </div>
                    <button type="button"
                            @click="$wire.cancelReject()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition">✕</button>
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

{{-- ═══════════════════════════════════════════════════════════════════════
     Priority Vehicle Booking — Notification Bell & Approval Modals
     (inside root div — Livewire requires exactly one root element)
     ═══════════════════════════════════════════════════════════════════════ --}}

{{-- Floating bell button (shows only when there are unread vehicle notifications) --}}
@if($vehicleNotifCount > 0)
<div class="fixed top-20 right-6 z-[80]"
     x-data="{ open: @entangle('showNotifPanel').live }">
    <button wire:click="toggleNotifPanel"
        class="relative flex items-center justify-center w-11 h-11 rounded-2xl bg-amber-500 text-white shadow-xl hover:bg-amber-600 transition focus:outline-none focus:ring-2 focus:ring-amber-500/40"
        title="Priority Booking Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
        </svg>
        <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 flex items-center justify-center rounded-full bg-red-600 text-white text-[10px] font-bold shadow">
            {{ $vehicleNotifCount }}
        </span>
    </button>
</div>
@endif

{{-- Notification Panel --}}
@if($showNotifPanel)
<div class="fixed inset-0 z-[90]" wire:click.self="closeNotifPanel">
    <div class="absolute top-20 right-6 w-80 sm:w-96 bg-card border border-border rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-border bg-muted/30 flex items-center justify-between">
            <p class="text-sm font-semibold text-foreground">Priority Vehicle Notifications</p>
            <button wire:click="closeNotifPanel" class="text-muted-foreground hover:text-foreground transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="max-h-72 overflow-y-auto divide-y divide-border/60">
            @forelse($vehicleNotifs as $n)
            <div class="px-4 py-3 hover:bg-muted/30 transition {{ !$n->is_read ? 'bg-primary/5' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-foreground">{{ $n->title }}</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5 line-clamp-2">{{ $n->message }}</p>
                        <p class="text-[10px] text-muted-foreground/60 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @if($n->isPendingAction())
                    <button wire:click="openPriorityApprovalModal({{ $n->id }})"
                        class="shrink-0 px-3 py-1.5 text-[11px] font-semibold rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition">
                        Review
                    </button>
                    @elseif($n->action_taken)
                    <span class="shrink-0 text-[11px] font-semibold {{ $n->action_taken === 'approved' ? 'text-emerald-600' : 'text-red-500' }}">
                        {{ ucfirst($n->action_taken) }}
                    </span>
                    @endif
                </div>
            </div>
            @empty
            <div class="px-4 py-8 text-center text-muted-foreground text-xs">No notifications.</div>
            @endforelse
        </div>
    </div>
</div>
@endif

{{-- Priority Vehicle Approval Modal --}}
@if($showPriorityApprovalModal && $priorityApprovalBookingId)
@php
    $pvb = \App\Models\PriorityVehicleBooking::with(['vehicle','department','manager','cancelledBooking'])->find($priorityApprovalBookingId);
@endphp
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-foreground">Priority Vehicle Booking — Action Required</p>
                <p class="text-xs text-muted-foreground mt-0.5">A manager has requested cancellation of a pending booking.</p>
            </div>
        </div>

        @if($pvb)
        <div class="bg-muted/40 rounded-xl p-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-muted-foreground">Vehicle:</span>
                <span class="font-semibold">{{ $pvb->vehicle?->name ?? '—' }} {{ $pvb->vehicle?->plate_number ? '('.$pvb->vehicle->plate_number.')' : '' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Borrower:</span>
                <span class="font-semibold">{{ $pvb->borrower_name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Schedule:</span>
                <span class="font-semibold">{{ $pvb->start_at?->format('d M Y H:i') }} – {{ $pvb->end_at?->format('H:i') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Requested by:</span>
                <span class="font-semibold">{{ $pvb->manager?->full_name ?? '—' }}</span>
            </div>
            @if($pvb->cancelledBooking)
            <div class="mt-2 pt-2 border-t border-border space-y-1">
                <p class="text-xs font-semibold text-orange-600">Booking to cancel (currently pending):</p>
                <p class="text-xs text-muted-foreground">#{{ $pvb->cancelledBooking->vehiclebooking_id }} — {{ $pvb->cancelledBooking->borrower_name }} · {{ $pvb->cancelledBooking->start_at?->format('d M H:i') }} – {{ $pvb->cancelledBooking->end_at?->format('H:i') }}</p>
            </div>
            @endif
        </div>
        @endif

        <p class="text-sm text-foreground">
            <strong>Approve</strong> to cancel the conflicting pending booking and grant priority, or
            <strong>Deny</strong> to keep the original booking.
        </p>

        <div class="flex flex-col sm:flex-row gap-2 pt-1">
            <button wire:click="approvePriorityVehicle"
                class="flex-1 inline-flex items-center justify-center h-10 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">
                Approve &amp; Cancel Conflict
            </button>
            <button wire:click="denyPriorityVehicle"
                class="flex-1 inline-flex items-center justify-center h-10 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                Deny Request
            </button>
            <button wire:click="closePriorityApprovalModal"
                class="flex-1 inline-flex items-center justify-center h-10 text-xs font-semibold rounded-lg border border-border bg-card text-foreground hover:bg-muted transition">
                Later
            </button>
        </div>
    </div>
</div>
@endif
</div>{{-- end root Livewire div --}}
