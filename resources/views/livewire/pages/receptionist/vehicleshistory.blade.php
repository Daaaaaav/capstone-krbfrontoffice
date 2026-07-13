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

<div class="min-h-screen bg-gray-50" x-data="{ showFilterModal: false }">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Flash Messages (Replaced by Toast) --}}

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.vehicle_history_title') }}"
            subtitle="{{ $statusTab === 'rejected' ? __('app.vehicle_history_sub_rej') : __('app.vehicle_history_sub_done') }}">
            <x-slot:actions>
                <button type="button" wire:click="$toggle('withTrashed')" class="flex items-center gap-2 group focus:outline-none">
                    <div class="relative flex items-center">
                        <div class="w-9 h-5 rounded-full transition-colors {{ $withTrashed ? 'bg-primary' : 'bg-border' }}"></div>
                        <div class="absolute left-[3px] w-3.5 h-3.5 rounded-full bg-white shadow transition-transform {{ $withTrashed ? 'translate-x-4' : '' }}"></div>
                    </div>
                    <span class="text-sm font-medium transition-colors" style="color:#CDDEA7 !important">{{ __('app.show_deleted') }}</span>
                </button>
                <button type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-secondary text-secondary-foreground text-xs font-medium border border-border hover:bg-secondary/80 md:hidden transition"
                        @click="showFilterModal = true">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    <span>{{ __('app.filter') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- MAIN GRID --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

            {{-- LIST --}}
            <section class="{{ $card }} md:col-span-3">

                {{-- Header --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('app.vehicle_history_title') }}</h3>
                            <p class="text-xs text-gray-500">
                                {{ $statusTab === 'rejected'
                                    ? __('app.vehicle_history_sub_rej')
                                    : __('app.vehicle_history_sub_done') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            {{-- Tabs --}}
                            <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium">
                                <button type="button" wire:click="$set('statusTab','done')"
                                    class="px-3.5 py-1 rounded-full transition {{ $statusTab === 'done' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                    {{ __('app.done') }}
                                </button>
                                <button type="button" wire:click="$set('statusTab','rejected')"
                                    class="px-3.5 py-1 rounded-full transition {{ $statusTab === 'rejected' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                    {{ __('app.rejected') }}
                                </button>
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

                    {{-- Filter Indicator --}}
                    <div class="flex flex-wrap items-center gap-2 text-xs mt-1">
                        @if(!is_null($vehicleFilter))
                            @php $activeVehicle = $vehicleMap[$vehicleFilter] ?? 'Unknown'; @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30 shadow-sm">
                                Vehicle: {{ $activeVehicle }}
                                <button type="button" class="ml-1 hover:text-white" wire:click="$set('vehicleFilter', null)">×</button>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Filters --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('app.search') }}</label>
                            <div class="relative">
                                <input type="text" class="{{ $input }} pl-9"
                                    placeholder="{{ __('app.search_vehicle_ph') }}"
                                    wire:model.live.debounce.400ms="q">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="m21 21-4.3-4.3M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <input type="date" wire:model.live="selectedDate" class="{{ $input }}">
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

                {{-- LIST BODY – 2 column bento style --}}
                {{-- ── MANAGER PRIORITY VEHICLE HISTORY ── --}}
                @if(isset($priorityVehicleHistory) && $priorityVehicleHistory->isNotEmpty())
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b-2 border-blue-200 bg-blue-50/40">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-700">
                            Manager Priority Bookings — {{ $statusTab === 'done' ? 'Approved' : 'Rejected/Denied' }}
                        </span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">{{ $priorityVehicleHistory->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 pb-4">
                        @foreach($priorityVehicleHistory as $pvb)
                        @php
                            $pvhBadge = $statusTab === 'done'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-rose-100 text-rose-700';
                        @endphp
                        <div wire:key="priority-vhist-{{ $pvb->id }}" class="bg-white border border-blue-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
                            <div class="w-9 h-9 rounded-lg bg-blue-500/15 flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                            </div>
                            <div class="flex-1 min-w-0 space-y-0.5">
                                <p class="text-sm font-semibold text-gray-900 truncate">
                                    {{ $pvb->vehicle?->name ?? '—' }}{{ $pvb->vehicle?->plate_number ? ' ('.$pvb->vehicle->plate_number.')' : '' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $pvb->borrower_name }} &bull;
                                    {{ $pvb->start_at?->format('d M Y H:i') }} – {{ $pvb->end_at?->format('H:i') }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">{{ $pvb->purpose }}</p>
                                <p class="text-[11px] text-blue-600 font-medium">By: {{ $pvb->manager?->full_name ?? '—' }}</p>
                                @if($pvb->rejection_reason)
                                    <p class="text-[11px] text-rose-500 italic">{{ $pvb->rejection_reason }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full {{ $pvhBadge }}">
                                {{ $pvb->statusLabel() }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($bookings->isEmpty())
                    <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                        {{ __('app.no_history_filter') }}
                    </div>
                @else
                <div class="px-4 sm:px-6 py-5">
                    @if($viewMode === 'card')
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @foreach($bookings as $b)
                                @php
                                    $vehicleName = $vehicleMap[$b->vehicle_id] ?? 'Unknown';
                                    $avatarChar = strtoupper(substr($vehicleName,0,1));
                                    $isRejected = $b->status === 'rejected';
                                    $isTrashed  = method_exists($b, 'trashed') ? $b->trashed() : false;
                                    $statusStyle = $isRejected
                                        ? ['bg'=>'bg-rose-100','text'=>'text-rose-800','label'=>__('app.rejected')]
                                        : ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.completed')];
                                @endphp
                                
                                {{-- START: MODIFIED VEHICLE HISTORY CARD DESIGN --}}
                                <div wire:key="history-{{ $b->vehiclebooking_id }}"
                                    class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                    
                                    <div class="flex items-start gap-4">
                                        {{-- 1. Avatar/Initial on the left --}}
                                        <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>
                                        
                                        <div class="flex-1 min-w-0">
                                            {{-- 2. TOP ROW: Title, Status, ID --}}
                                            <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                    {{ $b->purpose ? ucfirst($b->purpose) : __('app.vehicle_book') }}
                                                </h4>
                                                <div class="flex-shrink-0 flex items-center gap-2">
                                                    {{-- Status Badge --}}
                                                    <span class="text-[11px] px-2 py-0.5 rounded-full flex-shrink-0 {{ $statusStyle['bg'] }} {{ $statusStyle['text'] }}">
                                                        {{ $statusStyle['label'] }}
                                                    </span>
                                                    @if($isTrashed)
                                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-200 text-gray-800 border border-gray-300 flex-shrink-0">
                                                            {{ __('app.deleted') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- 3. MIDDLE SECTION: Vehicle, Date, Time --}}
                                            <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                
                                                {{-- Vehicle Name Chip --}}
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-gray-100">
                                                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7" />
                                                        </svg>
                                                        <span class="font-medium text-gray-700">{{ $vehicleName }}</span>
                                                    </span>
                                                </div>

                                                {{-- Dates and Times --}}
                                                <div class="flex flex-wrap items-center gap-4">
                                                    <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        {{ fmtDate($b->start_at) }}
                                                    </span>
                                                    <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        {{ fmtTime($b->start_at) }}–{{ fmtTime($b->end_at) }}
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- 4. BOTTOM LEFT: Borrower & Notes/Timestamp --}}
                                            <div class="text-[12px] text-gray-600 space-y-1">
                                                @if(!empty($b->borrower_name))
                                                    <p>{{ __('app.borrower_label') }}: <span class="font-medium text-gray-800">{{ $b->borrower_name }}</span></p>
                                                @endif
                                                <span class="inline-block text-[10px] text-gray-500 mt-1">
                                                    {{ __('app.created_label') }}: {{ optional($b->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                                </span>
                                            </div>

                                            {{-- Rejected Note --}}
                                            @if($isRejected && !empty($b->notes))
                                                <div class="mt-2 text-xs text-rose-700 bg-rose-50 border border-rose-100 rounded-lg p-2">
                                                    <span class="font-medium">{{ __('app.reject_reason') }}:</span> {{ $b->notes }}
                                                </div>
                                            @endif
                                            
                                        </div>
                                    </div>

                                    {{-- 5. BOTTOM ACTIONS (Horizontally aligned and right justified) --}}
                                    <div class="pt-3 border-t border-gray-100 flex justify-end gap-3 items-center">
                                        <span class="text-[11px] text-gray-500 mr-auto">No. {{ ($bookings->firstItem() ?? 1) + $loop->index }}</span>
                                        
                                        {{-- Actions based on Trashed Status --}}
                                        @if(!$isTrashed)
                                            <button type="button"
                                                class="px-3 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 transition shadow-sm"
                                                wire:click="openEdit({{ $b->vehiclebooking_id }})">
                                                {{ __('app.edit') }}
                                            </button>
                                            <button type="button"
                                                class="px-3 py-2 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 transition"
                                                wire:click="confirmDelete({{ $b->vehiclebooking_id }}, '{{ str_replace('\'', '', $b->purpose ?? __('app.vehicle_book')) }}')">
                                                {{ __('app.delete') }}
                                            </button>
                                        @else
                                            <button type="button"
                                                class="px-4 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 transition shadow-sm"
                                                wire:click="restore({{ $b->vehiclebooking_id }})">
                                                {{ __('app.restore') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                {{-- END: MODIFIED VEHICLE HISTORY CARD DESIGN --}}
                            @endforeach
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50/50">
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">#</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">{{ __('app.borrower') }}</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">{{ __('app.vehicle') }}</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">{{ __('app.purpose') }} / {{ __('app.destination') }}</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">{{ __('app.date') }} & {{ __('app.time') }}</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500">{{ __('app.status') }}</th>
                                        <th class="h-10 px-4 text-xs font-semibold text-gray-500">{{ __('app.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($bookings as $b)
                                        @php
                                            $vehicleName = $vehicleMap[$b->vehicle_id] ?? 'Unknown';
                                            $isRejected = $b->status === 'rejected';
                                            $isTrashed  = method_exists($b, 'trashed') ? $b->trashed() : false;
                                            $statusStyle = $isRejected
                                                ? ['bg'=>'bg-rose-100','text'=>'text-rose-800','label'=>__('app.rejected')]
                                                : ['bg'=>'bg-emerald-100','text'=>'text-emerald-800','label'=>__('app.completed')];
                                        @endphp
                                        <tr wire:key="history-row-{{ $b->vehiclebooking_id }}" class="hover:bg-gray-50/50 transition {{ $isTrashed ? 'opacity-60' : '' }}">
                                            <td class="h-12 px-4 py-0 text-gray-400 text-xs font-mono">{{ $loop->iteration }}</td>
                                            <td class="h-12 px-4 py-0 font-medium">
                                                <div class="font-medium text-gray-900">{{ $b->borrower_name ?? '—' }}</div>
                                            </td>
                                            <td class="h-12 px-4 py-0 text-gray-600 font-medium">{{ $vehicleName }}</td>
                                            <td class="h-12 px-4 py-0 text-gray-600">
                                                <div class="md:max-w-[200px] truncate" title="{{ $b->purpose }}">
                                                    {{ $b->purpose ? ucfirst($b->purpose) : '—' }}
                                                </div>
                                            </td>
                                            <td class="h-12 px-4 py-0 whitespace-nowrap text-gray-600">
                                                <span class="font-medium text-gray-800">{{ fmtDate($b->start_at) }}</span>
                                                <span class="text-xs text-gray-400 block">{{ fmtTime($b->start_at) }} – {{ fmtTime($b->end_at) }}</span>
                                            </td>
                                            <td class="h-12 px-4 py-0 ">
                                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $statusStyle['bg'] }} {{ $statusStyle['text'] }}">
                                                        {{ $statusStyle['label'] }}
                                                    </span>
                                                    @if($isTrashed)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                            {{ __('app.deleted') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($isRejected && !empty($b->notes))
                                                    <span class="block text-[11px] text-rose-600 md:max-w-[180px] truncate mt-0.5" title="{{ $b->notes }}">{{ __('app.reason') }}: {{ $b->notes }}</span>
                                                @endif
                                            </td>
                                            <td class="h-12 px-4 py-0">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if(!$isTrashed)
                                                        <button type="button"
                                                            class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition shadow-sm"
                                                            wire:click="openEdit({{ $b->vehiclebooking_id }})">
                                                            {{ __('app.edit') }}
                                                        </button>
                                                        <button type="button"
                                                            class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition"
                                                            wire:click="confirmDelete({{ $b->vehiclebooking_id }}, '{{ str_replace('\'', '', $b->purpose ?? __('app.vehicle_book')) }}')">
                                                            {{ __('app.delete') }}
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                            class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition shadow-sm"
                                                            wire:click="restore({{ $b->vehiclebooking_id }})">
                                                            {{ __('app.restore') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @endif

                {{-- Pagination --}}
                @if(method_exists($bookings, 'links'))
                    <div class="px-4 sm:px-6 py-5 bg-gray-50 border-t border-gray-200 rounded-b-2xl">
                        <div class="w-full">
                            {{ $bookings->links() }}
                        </div>
                    </div>
                @endif
            </section>

            {{-- SIDEBAR --}}
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
                                        wire:click="$set('vehicleFilter', null)"
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
                                            $vLabel = $v->name ?? $v->plate_number ?? '#'.$v->vehicle_id;
                                            $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                                        @endphp
                                        <button type="button"
                                                wire:click="$set('vehicleFilter', {{ $v->vehicle_id }})"
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
    </main>

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
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.click_to_filter') }}</p>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-200 transition" @click="showFilterModal = false">✕</button>
                </div>

                <div class="p-5 space-y-5 overflow-y-auto flex-1 bg-white">
                    <button type="button" wire:click="$set('vehicleFilter', null)" @click="showFilterModal = false"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors {{ is_null($vehicleFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">{{ __('app.all') }}</span>
                            <span>{{ __('app.all_vehicles') }}</span>
                        </span>
                    </button>

                    <div class="space-y-1.5">
                        @forelse($vehicles as $v)
                            @php
                                $vLabel = $v->name ?? $v->plate_number ?? '#'.$v->vehicle_id;
                                $active = !is_null($vehicleFilter) && (int)$vehicleFilter === (int)$v->vehicle_id;
                            @endphp

                            <button type="button"
                                wire:click="$set('vehicleFilter', {{ $v->vehicle_id }})"
                                @click="showFilterModal = false"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-300 text-[11px]">
                                        {{ substr($vLabel,0,2) }}
                                    </span>
                                    <span class="truncate">{{ $vLabel }}</span>
                                </span>
                            </button>
                        @empty
                            <p class="text-xs text-gray-500">{{ __('app.no_vehicle_data_filter') }}</p>
                        @endforelse
                    </div>

                </div>
            </div>
        </div>

    {{-- ===== EDIT MODAL ===== --}}
    @if($showEdit)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-300" wire:click="$set('showEdit', false)"></div>
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden max-h-[90vh] flex flex-col transform transition-all duration-300 scale-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <x-heroicon-o-pencil class="w-4 h-4 text-[#CDDEA7]" />
                        </div>
                        <h3 class="text-base font-bold tracking-tight">{{ __('app.edit') }}</h3>
                    </div>
                    <button wire:click="$set('showEdit', false)" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition">
                        <x-heroicon-o-x-mark class="w-4 h-4"/>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    @php
                        $mi = 'w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition-all';
                        $ml = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5';
                    @endphp
                    <div>
                        <label class="{{ $ml }}">{{ __('app.borrower_label') ?? 'Borrower Name' }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="edit.borrower_name" class="{{ $mi }}">
                        @error('edit.borrower_name') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $ml }}">{{ __('app.start') ?? 'Start' }} <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" wire:model="edit.start_at" class="{{ $mi }}">
                            @error('edit.start_at') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $ml }}">{{ __('app.end') ?? 'End' }} <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" wire:model="edit.end_at" class="{{ $mi }}">
                            @error('edit.end_at') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.purpose') }}</label>
                        <input type="text" wire:model="edit.purpose" class="{{ $mi }}">
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.destination') }}</label>
                        <input type="text" wire:model="edit.destination" class="{{ $mi }}">
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.reason') ?? 'Notes' }}</label>
                        <textarea wire:model="edit.notes" class="{{ $mi }} py-2.5 h-20 resize-none"></textarea>
                    </div>

                    {{-- Vehicle Logs (Status Timeline) Section --}}
                    @if(count($statusLogs) > 0)
                        <div class="mt-6 border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                Status Logs
                            </h4>
                            <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-100 border-b border-gray-200 text-gray-600 uppercase font-semibold">
                                        <tr>
                                            <th class="px-3 py-2">Step</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="px-3 py-2">Logged At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-700">
                                        @foreach($statusLogs as $index => $log)
                                            @php
                                                $bgClass = match($log['type']) {
                                                    'success' => 'bg-emerald-100 text-emerald-800',
                                                    'danger'  => 'bg-rose-100 text-rose-800',
                                                    'warning' => 'bg-amber-100 text-amber-800',
                                                    'primary' => 'bg-blue-100 text-blue-800',
                                                    default   => 'bg-gray-200 text-gray-800',
                                                };
                                            @endphp
                                            <tr class="hover:bg-white transition-colors">
                                                <td class="px-3 py-2 font-medium">Log {{ $index + 1 }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex items-center gap-1 text-[10px] {{ $bgClass }} px-1.5 py-0.5 rounded-full font-bold uppercase tracking-wider">
                                                        {{ $log['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap">
                                                    {{ \Carbon\Carbon::parse($log['time'])->format('d M Y, H:i') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                Status Logs
                            </h4>
                            <p class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg border border-gray-100 text-center">No timeline data available.</p>
                        </div>
                    @endif

                    {{-- Footer actions --}}
                    <div class="pt-5 border-t border-gray-200 flex items-center justify-end gap-3 bg-gray-50/50 -mx-6 -mb-6 p-4 mt-6">
                        <button type="button"
                                class="h-9 px-4 rounded-lg bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition text-xs font-semibold"
                                wire:click="$set('showEdit', false)">
                            {{ __('app.cancel') }}
                        </button>
                        <button type="button"
                                class="h-9 px-4 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition shadow-sm flex items-center gap-1.5"
                                wire:loading.attr="disabled" wire:target="saveEdit" wire:click="saveEdit">
                            <span wire:loading.remove wire:target="saveEdit" class="flex items-center gap-1.5">
                                <x-heroicon-o-check class="w-3.5 h-3.5" />
                                {{ __('app.save') }}
                            </span>
                            <span wire:loading wire:target="saveEdit" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('app.saving') ?? 'Saving...' }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- DELETE MODAL --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" wire:click="$set('showDeleteModal', false)"></div>
            <div class="relative w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-2xl overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center border border-rose-500/30">
                            <x-heroicon-o-trash class="w-4 h-4 text-rose-400" />
                        </div>
                        <h3 class="font-bold tracking-tight text-base">Delete Alert</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="$set('showDeleteModal', false)">✕</button>
                </div>
                <div class="p-6 text-center bg-white">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                        {{ $isForceDelete ? __(`app.delete_permanent_confirm`) : __(`app.delete_vehicle_confirm`) ?? 'Hapus Data?' }}
                    </h3>
                    <p class="text-sm text-gray-500">{{ __('app.are_you_sure_delete') }}</p>
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200 text-sm font-medium text-gray-700">
                        {{ $deletingSummary }}
                    </div>
                </div>
                <div class="border-t border-gray-200 px-6 py-4 flex items-center justify-end gap-3 bg-gray-50">
                    <button type="button" wire:click="$set('showDeleteModal', false)"
                        class="h-9 px-4 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition inline-flex items-center gap-1.5 text-xs font-semibold">
                        {{ __('app.cancel') }}
                    </button>
                    <button type="button" wire:click="executeDelete" wire:loading.attr="disabled"
                        class="h-9 px-4 rounded-lg bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 transition shadow-sm inline-flex items-center gap-1.5 disabled:opacity-60">
                        <span wire:loading.remove wire:target="executeDelete">{{ __('app.delete') }}</span>
                        <span wire:loading wire:target="executeDelete" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            {{ __('app.delete') }}...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>