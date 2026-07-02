@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Storage;

    if (!function_exists('fmtDate')) {
        function fmtDate($v)
        {
            try {
                return $v ? Carbon::parse($v)->format('d M Y') : '—';
            } catch (\Throwable) {
                return '—';
            }
        }
    }

    if (!function_exists('fmtTime')) {
        function fmtTime($v)
        {
            try {
                return $v ? Carbon::parse($v)->format('H.i') : '—';
            } catch (\Throwable) {
                if (is_string($v)) {
                    if (preg_match('/^\d{2}:\d{2}/', $v))
                        return str_replace(':', '.', substr($v, 0, 5));
                    if (preg_match('/^\d{2}\.\d{2}/', $v))
                        return substr($v, 0, 5);
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

<div class="min-h-screen bg-gray-50" wire:poll.1000ms.keep-alive>
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
                            <x-heroicon-o-archive-box class="w-6 h-6 text-[#CDDEA7]"/>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold">{{ __('app.docpac_status_title') }}</h2>
                        <p class="text-sm text-[#CDDEA7]/80">{{ __('app.docpac_status_sub') }}</p>
                        </div>
                    </div>

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

        {{-- MAIN LAYOUT: LEFT (ITEMS LIST) + RIGHT (SIDEBAR) --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
            {{-- LEFT: ITEMS LIST CARD --}}
            <section class="{{ $card }} md:col-span-3">
                {{-- Header: title + tabs + type scope --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.items_list') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('app.items_list_sub') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        {{-- Staging Tabs: Pending vs Stored --}}
                        <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium shrink-0">
                            <button type="button" wire:click="setTab('pending')" 
                                class="px-3.5 py-1 rounded-full transition {{ $activeTab === 'pending' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.tab_pending') }}
                            </button>
                            <button type="button" wire:click="setTab('stored')" 
                                class="px-3.5 py-1 rounded-full transition {{ $activeTab === 'stored' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.tab_stored') }}
                            </button>
                        </div>

                        {{-- Type Scope Tabs --}}
                        <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium shrink-0">
                            <button type="button" wire:click="$set('type', 'all')" 
                                class="px-3.5 py-1 rounded-full transition {{ $type === 'all' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.tab_all') }}
                            </button>
                            <button type="button" wire:click="$set('type', 'document')" 
                                class="px-3.5 py-1 rounded-full transition {{ $type === 'document' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.type_document') }}
                            </button>
                            <button type="button" wire:click="$set('type', 'package')" 
                                class="px-3.5 py-1 rounded-full transition {{ $type === 'package' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.type_package') }}
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

                {{-- Advanced Filter Badge Under Title --}}
                <div class="px-4 sm:px-6 py-2 border-b border-gray-200/60 bg-gray-50/20 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        @if(trim($filterSender) !== '')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                <x-heroicon-o-user class="w-3.5 h-3.5"/>
                                <span>From: {{ $filterSender }}</span>
                                <button type="button" class="ml-1 hover:text-white" wire:click="$set('filterSender', '')">×</button>
                            </span>
                        @endif

                        @if(trim($filterReceiver) !== '')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                <x-heroicon-o-user class="w-3.5 h-3.5"/>
                                <span>To: {{ $filterReceiver }}</span>
                                <button type="button" class="ml-1 hover:text-white" wire:click="$set('filterReceiver', '')">×</button>
                            </span>
                        @endif

                        @if($userId)
                            @php
                                $activeUser = $users->firstWhere('user_id', $userId);
                                $activeUserLabel = $activeUser ? $activeUser->full_name : 'Unknown';
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                <x-heroicon-o-user-circle class="w-3.5 h-3.5"/>
                                <span>Officer: {{ $activeUserLabel }}</span>
                                <button type="button" class="ml-1 hover:text-white" wire:click="$set('userId', null)">×</button>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Filters (search, date, order) --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 bg-gray-50/30">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('app.search') }}</label>
                            <div class="relative">
                                <input type="text" class="{{ $input }} pl-9"
                                    placeholder="{{ __('app.search_item_sender') }}" wire:model.live="q">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('app.date_label') }}</label>
                            <div class="relative">
                                <input type="date" class="{{ $input }} pl-9" wire:model.live="selectedDate">
                                <x-heroicon-o-calendar-days class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('app.sort_label') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: @entangle('dateMode').live,
                                    options: [
                                        { id: 'semua', label: '{{ __('app.sort_default_opt') }}' },
                                        { id: 'terbaru', label: '{{ __('app.sort_newest_opt') }}' },
                                        { id: 'terlama', label: '{{ __('app.sort_oldest_opt') }}' }
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
                                    if (!selectedId) selectedId = 'semua';
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
                                        :placeholder="selectedLabel || '{{ __('app.sort_default_opt') }}'"
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

                {{-- LIST (2-COLUMN GRID LAYOUT) --}}
                <div class="px-4 sm:px-6 py-5 bg-gray-50/50">
                    @if($viewMode === 'card')
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            
                            {{-- PENDING TAB --}}
                            @if($activeTab === 'pending')
                                @forelse($pending as $row)
                                    @php
                                        $avatarChar = strtoupper(substr($row->item_name ?? 'P', 0, 1));
                                        $rowNo = ($pending->firstItem() ?? 1) + $loop->index;
                                    @endphp

                                    <div wire:key="pend-{{ $row->delivery_id }}"
                                        class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 hover:shadow-sm hover:border-gray-300 transition">
                                        
                                        <div class="flex items-start gap-4">
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>

                                                <div class="min-w-0 flex-1 space-y-1">
                                                    {{-- TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $row->item_name }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            {{-- Type Badge --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border border-gray-300 text-gray-700 bg-gray-50 flex-shrink-0 font-medium uppercase">
                                                                {{ __('app.type_' . $row->type) }}
                                                            </span>
                                                            {{-- Status Badge --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full flex-shrink-0 bg-amber-100 text-amber-800">
                                                                {{ __('app.tab_pending') }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- Senders & Receiver information --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        @if($row->nama_pengirim)
                                                            <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                                <span class="truncate">{{ __('app.sender') }}: <span class="font-semibold">{{ $row->nama_pengirim }}</span></span>
                                                            </div>
                                                        @endif
                                                        @if($row->nama_penerima)
                                                            <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                                <span class="truncate">{{ __('app.receiver') }}: <span class="font-semibold">{{ $row->nama_penerima }}</span></span>
                                                            </div>
                                                        @endif
                                                        @if($row->image)
                                                            <div class="pt-1 mt-1 border-t border-dashed border-gray-100">
                                                                <button type="button"
                                                                    x-data
                                                                    @click="$dispatch('open-lightbox', { src: '{{ route('delivery.image', basename($row->image)) }}' })"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-[#4E653D] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition"
                                                                    title="Lihat foto penuh">
                                                                    <x-heroicon-o-photo class="w-3.5 h-3.5"/>
                                                                    Lihat Bukti Foto
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- BOTTOM LEFT: Date details --}}
                                                    <div class="text-[12px] text-gray-600 space-y-2 mt-2">
                                                @if($row->created_at)
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-500">
                                                        <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                        <span>{{ __('app.received_label') }}: <span class="font-medium text-gray-700">{{ fmtDate($row->created_at) }} · {{ fmtTime($row->created_at) }}</span></span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- BOTTOM ACTIONS --}}
                                        <div class="pt-3 border-t border-gray-100 flex justify-end gap-3 items-center">
                                            <span class="text-[11px] text-gray-500 mr-auto">No. {{ $rowNo }}</span>
                                            <div class="flex gap-2">
                                                <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                                    wire:loading.attr="disabled"
                                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-50 text-gray-700 border border-gray-300 hover:bg-gray-100 focus:ring-2 focus:ring-gray-900/10 focus:outline-none transition shadow-sm">
                                                    Edit
                                                </button>
                                                <button type="button" wire:click="storeItem({{ $row->delivery_id }})"
                                                    wire:loading.attr="disabled"
                                                    class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:ring-2 focus:ring-[#4E653D]/20 focus:outline-none transition shadow-sm">
                                                    {{ __('app.store_action') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full py-16 text-center text-gray-500 text-sm bg-white border border-dashed border-gray-200 rounded-xl">
                                        <x-heroicon-o-document-text class="w-8 h-8 mx-auto text-gray-300 mb-2"/>
                                        {{ __('app.no_data_pending') }}
                                    </div>
                                @endforelse
                            @endif

                            {{-- STORED TAB --}}
                            @if($activeTab === 'stored')
                                @forelse($stored as $row)
                                    @php
                                        $avatarChar = strtoupper(substr($row->item_name ?? 'S', 0, 1));
                                        $rowNo = ($stored->firstItem() ?? 1) + $loop->index;
                                        $dir = $storedDirections[$row->delivery_id] ?? 'taken';
                                        $dirLabel = $dir === 'deliver' ? __('app.deliver') : __('app.taken');
                                        $actionLabel = $dir === 'deliver' ? __('app.delivered') : __('app.taken');
                                    @endphp

                                    <div wire:key="stor-{{ $row->delivery_id }}"
                                        class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 hover:shadow-sm hover:border-gray-300 transition">
                                        
                                        <div class="flex items-start gap-4">
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>

                                                <div class="min-w-0 flex-1 space-y-1">
                                                    {{-- TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $row->item_name }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            {{-- Type Badge --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border border-gray-300 text-gray-700 bg-gray-50 flex-shrink-0 font-medium uppercase">
                                                                {{ __('app.type_' . $row->type) }}
                                                            </span>
                                                            {{-- Status Badge --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full flex-shrink-0 bg-blue-100 text-blue-800">
                                                                {{ __('app.tab_stored') }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- Senders & Receiver information --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        @if($row->nama_pengirim)
                                                            <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                                <span class="truncate">{{ __('app.sender') }}: <span class="font-semibold">{{ $row->nama_pengirim }}</span></span>
                                                            </div>
                                                        @endif
                                                        @if($row->nama_penerima)
                                                            <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                                <span class="truncate">{{ __('app.receiver') }}: <span class="font-semibold">{{ $row->nama_penerima }}</span></span>
                                                            </div>
                                                        @endif
                                                        @if($row->image)
                                                            <div class="pt-1 mt-1 border-t border-dashed border-gray-100">
                                                                <button type="button"
                                                                    x-data
                                                                    @click="$dispatch('open-lightbox', { src: '{{ route('delivery.image', basename($row->image)) }}' })"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-[#4E653D] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition"
                                                                    title="Lihat foto penuh">
                                                                    <x-heroicon-o-photo class="w-3.5 h-3.5"/>
                                                                    Lihat Bukti Foto
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- BOTTOM LEFT: Direction info --}}
                                                    <div class="text-[12px] text-gray-600 space-y-2 mt-2">
                                                        <div class="flex items-center gap-1.5 text-[10px] text-gray-500">
                                                            <x-heroicon-o-arrow-up-right class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                            <span>{{ __('app.direction_label') }}: <span class="font-semibold text-gray-700 uppercase tracking-wide">{{ $dirLabel }}</span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- BOTTOM ACTIONS --}}
                                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-3">
                                            <span class="text-[11px] text-gray-500 mr-auto">No. {{ $rowNo }}</span>
                                            <div class="flex gap-2">
                                                <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                                    wire:loading.attr="disabled"
                                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-50 text-gray-700 border border-gray-300 hover:bg-gray-100 focus:ring-2 focus:ring-gray-900/10 focus:outline-none transition shadow-sm">
                                                    Edit
                                                </button>
                                                <button type="button" wire:click="finalizeItem({{ $row->delivery_id }})"
                                                    wire:loading.attr="disabled"
                                                    class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:ring-2 focus:ring-[#4E653D]/20 focus:outline-none transition shadow-sm">
                                                    {{ $actionLabel }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full py-16 text-center text-gray-500 text-sm bg-white border border-dashed border-gray-200 rounded-xl">
                                        <x-heroicon-o-document-text class="w-8 h-8 mx-auto text-gray-300 mb-2"/>
                                        {{ __('app.no_data_stored') }}
                                    </div>
                                @endforelse
                            @endif
                        </div>
                    @else
                        {{-- TABLE VIEW MODE --}}
                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                        <th class="px-6 py-3.5">#</th>
                                        <th class="px-6 py-3.5">{{ __('app.item_name') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.type') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.sender') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.receiver') }}</th>
                                        @if($activeTab === 'pending')
                                            <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                        @else
                                            <th class="px-6 py-3.5">{{ __('app.direction_label') }}</th>
                                        @endif
                                        <th class="px-6 py-3.5 text-right">{{ __('app.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @if($activeTab === 'pending')
                                        @forelse($pending as $row)
                                            @php
                                                $rowNo = ($pending->firstItem() ?? 1) + $loop->index;
                                            @endphp
                                            <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400">#{{ $rowNo }}</td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-lg overflow-hidden shrink-0 border border-gray-200 bg-[#4E653D] flex items-center justify-center text-white text-xs font-semibold">
                                                            @if($row->image)
                                                                <button type="button"
                                                                    x-data
                                                                    @click="$dispatch('open-lightbox', { src: '{{ route('delivery.image', basename($row->image)) }}' })"
                                                                    class="w-full h-full block focus:outline-none">
                                                                    <img src="{{ route('delivery.image', basename($row->image)) }}" class="w-full h-full object-cover" alt="Bukti foto">
                                                                </button>
                                                            @else
                                                                {{ strtoupper(substr($row->item_name ?? 'P', 0, 1)) }}
                                                            @endif
                                                        </div>
                                                        <div class="font-semibold text-gray-900">{{ $row->item_name }}</div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium uppercase border border-gray-200 text-gray-700 bg-gray-50">
                                                        {{ __('app.type_' . $row->type) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">{{ $row->nama_pengirim ?? '—' }}</td>
                                                <td class="px-6 py-4 font-medium">{{ $row->nama_penerima ?? '—' }}</td>
                                                <td class="px-6 py-4 text-xs text-gray-500">
                                                    {{ fmtDate($row->created_at) }} {{ fmtTime($row->created_at) }}
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition shadow-sm">
                                                            {{ __('app.edit') }}
                                                        </button>
                                                        <button type="button" wire:click="storeItem({{ $row->delivery_id }})"
                                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition shadow-sm">
                                                            {{ __('app.store_action') }}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('app.no_data_pending') }}</td>
                                            </tr>
                                        @endforelse
                                    @else
                                        @forelse($stored as $row)
                                            @php
                                                $rowNo = ($stored->firstItem() ?? 1) + $loop->index;
                                                $dir = $storedDirections[$row->delivery_id] ?? 'taken';
                                                $dirLabel = $dir === 'deliver' ? __('app.deliver') : __('app.taken');
                                                $actionLabel = $dir === 'deliver' ? __('app.delivered') : __('app.taken');
                                            @endphp
                                            <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400">#{{ $rowNo }}</td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-lg overflow-hidden shrink-0 border border-gray-200 bg-[#4E653D] flex items-center justify-center text-white text-xs font-semibold">
                                                            @if($row->image)
                                                                <button type="button"
                                                                    x-data
                                                                    @click="$dispatch('open-lightbox', { src: '{{ route('delivery.image', basename($row->image)) }}' })"
                                                                    class="w-full h-full block focus:outline-none">
                                                                    <img src="{{ route('delivery.image', basename($row->image)) }}" class="w-full h-full object-cover" alt="Bukti foto">
                                                                </button>
                                                            @else
                                                                {{ strtoupper(substr($row->item_name ?? 'S', 0, 1)) }}
                                                            @endif
                                                        </div>
                                                        <div class="font-semibold text-gray-900">{{ $row->item_name }}</div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium uppercase border border-gray-200 text-gray-700 bg-gray-50">
                                                        {{ __('app.type_' . $row->type) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">{{ $row->nama_pengirim ?? '—' }}</td>
                                                <td class="px-6 py-4 font-medium">{{ $row->nama_penerima ?? '—' }}</td>
                                                <td class="px-6 py-4">
                                                    @if($dir === 'deliver')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-semibold border border-blue-200 text-blue-700 bg-blue-50">
                                                            {{ $dirLabel }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-semibold border border-emerald-200 text-emerald-700 bg-emerald-50">
                                                            {{ $dirLabel }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition shadow-sm">
                                                            {{ __('app.edit') }}
                                                        </button>
                                                        <button type="button" wire:click="finalizeItem({{ $row->delivery_id }})"
                                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition shadow-sm">
                                                            {{ $actionLabel }}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('app.no_data_stored') }}</td>
                                            </tr>
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Pagination --}}
                <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-white">
                    <div class="w-full">
                        @if($activeTab === 'pending')
                            {{ $pending->onEachSide(1)->links() }}
                        @else
                            {{ $stored->onEachSide(1)->links() }}
                        @endif
                    </div>
                </div>
            </section>

            {{-- RIGHT: SIDEBAR (DESKTOP / TABLET) --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                {{-- Filter by Sender/Receiver & User --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-visible">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Filter by sender, receiver, or officer</p>
                    </div>

                    <div class="p-4 space-y-4 bg-white">
                        {{-- Sender Filter --}}
                        <div class="space-y-1">
                            <label class="{{ $label }}">{{ __('app.sender') }}</label>
                            <div class="relative">
                                <input type="text"
                                    class="{{ $input }} pl-9"
                                    placeholder="Search by sender name..."
                                    wire:model.live="filterSender">
                                <x-heroicon-o-user class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        {{-- Receiver Filter --}}
                        <div class="space-y-1">
                            <label class="{{ $label }}">{{ __('app.receiver') }}</label>
                            <div class="relative">
                                <input type="text"
                                    class="{{ $input }} pl-9"
                                    placeholder="Search by receiver name..."
                                    wire:model.live="filterReceiver">
                                <x-heroicon-o-user class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        {{-- User / Officer Combobox --}}
                        <div class="space-y-1">
                            <label class="{{ $label }}">{{ __('app.officer') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: null,
                                    get items() {
                                        const q = this.search.toLowerCase().trim();
                                        return @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name])->values()->toArray()).filter(i =>
                                            !q || i.label.toLowerCase().includes(q)
                                        );
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('userId', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('userId', null);
                                    }
                                }"
                                x-init="
                                    $watch('$wire.userId', val => {
                                        this.selectedId = val || null;
                                        if (!val) { search = ''; }
                                        else {
                                            const found = @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name])->values()->toArray()).find(i => i.id == val);
                                            if (found) search = found.label;
                                        }
                                    });
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
                                        @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                        autocomplete="off"
                                        placeholder="{{ __('app.all_users') }}"
                                        class="{{ $input }} pr-8"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                                        <button x-show="search" type="button" @click.stop="clear()" class="text-gray-400 hover:text-gray-700">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="fill-current h-4 w-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
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
                                            @click="select(item.id, item.label)"
                                            :class="selectedId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                            class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                            x-text="item.label"
                                        ></li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                    {{ __('app.no_data') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    {{-- MOBILE FILTER MODAL --}}
    @if($showFilterModal)
        <div class="fixed inset-0 z-50 md:hidden flex items-end">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" wire:click="closeFilterModal"></div>
            <div class="relative w-full bg-white rounded-t-2xl shadow-2xl max-h-[85vh] overflow-hidden flex flex-col border-t border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Filter by sender, receiver, or officer</p>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" wire:click="closeFilterModal">✕</button>
                </div>

                <div class="p-5 space-y-5 overflow-y-auto flex-1">
                    {{-- Sender Filter (Mobile) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.sender') }}</label>
                        <div class="relative">
                            <input type="text"
                                class="{{ $input }} pl-9"
                                placeholder="Search by sender name..."
                                wire:model.live="filterSender">
                            <x-heroicon-o-user class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                        </div>
                    </div>

                    {{-- Receiver Filter (Mobile) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.receiver') }}</label>
                        <div class="relative">
                            <input type="text"
                                class="{{ $input }} pl-9"
                                placeholder="Search by receiver name..."
                                wire:model.live="filterReceiver">
                            <x-heroicon-o-user class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                        </div>
                    </div>

                    {{-- User / Officer Combobox (Mobile) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.officer') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: null,
                                get items() {
                                    const q = this.search.toLowerCase().trim();
                                    return @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name])->values()->toArray()).filter(i =>
                                        !q || i.label.toLowerCase().includes(q)
                                    );
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.selectedId = id;
                                    $wire.set('userId', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.selectedId = null;
                                    $wire.set('userId', null);
                                }
                            }"
                            x-init="
                                $watch('$wire.userId', val => {
                                    this.selectedId = val || null;
                                    if (!val) { search = ''; }
                                    else {
                                        const found = @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name])->values()->toArray()).find(i => i.id == val);
                                        if (found) search = found.label;
                                    }
                                });
                            "
                            class="relative"
                            @click.outside="open = false"
                        >
                            <div class="relative">
                                <input type="text" x-model="search" @focus="open = true" @input="open = true"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                    autocomplete="off" placeholder="{{ __('app.all_users') }}"
                                    class="{{ $input }} pr-8">
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                                    <button x-show="search" type="button" @click.stop="clear()" class="text-gray-400 hover:text-gray-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    <svg class="fill-current h-4 w-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                </div>
                            </div>
                            <ul x-show="open && items.length > 0"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute z-30 mt-1 w-full max-h-44 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg text-sm"
                                style="display:none">
                                <template x-for="item in items" :key="item.id">
                                    <li @click="select(item.id, item.label)"
                                        :class="selectedId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.label"></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                {{ __('app.no_data') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-gray-200 bg-gray-50">
                    <button type="button" class="w-full h-10 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] focus:ring-2 focus:ring-[#4E653D]/20 transition shadow-sm"
                        wire:click="closeFilterModal">
                        {{ __('app.apply_close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- EDIT MODAL --}}
    @if($showEdit)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="$set('showEdit', false)"></div>
            <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            <x-heroicon-o-pencil class="w-4 h-4 text-primary" />
                        </div>
                        <h3 class="font-bold text-foreground text-base tracking-tight">{{ __('app.edit') }}</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition" wire:click="$set('showEdit', false)">✕</button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.item_name') }}</label>
                        <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition" wire:model.defer="edit.item_name">
                        @error('edit.item_name') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.sender_name') }}</label>
                            <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition" wire:model.defer="edit.nama_pengirim">
                            @error('edit.nama_pengirim') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.receiver_name') }}</label>
                            <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition" wire:model.defer="edit.nama_penerima">
                            @error('edit.nama_penerima') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Photo Upload --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Bukti Foto</label>

                        {{-- Current image preview --}}
                        @if($editCurrentImage && !$editPhoto)
                            <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30 mb-2">
                                <img src="{{ route('delivery.image', basename($editCurrentImage)) }}"
                                    class="w-14 h-14 rounded-lg object-cover border border-border shrink-0 cursor-pointer"
                                    x-data
                                    @click="$dispatch('open-lightbox', { src: '{{ route('delivery.image', basename($editCurrentImage)) }}' })"
                                    title="Klik untuk lihat penuh"
                                    alt="Foto saat ini">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-foreground">Foto saat ini</p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">Upload foto baru untuk mengganti</p>
                                </div>
                            </div>
                        @elseif(!$editCurrentImage && !$editPhoto)
                            <p class="text-xs text-muted-foreground italic mb-1.5">Belum ada foto</p>
                        @endif

                        {{-- New photo preview --}}
                        @if($editPhoto)
                            <div class="flex items-center gap-3 p-3 rounded-lg border border-emerald-200 bg-emerald-50 mb-2">
                                <img src="{{ $editPhoto->temporaryUrl() }}"
                                    class="w-14 h-14 rounded-lg object-cover border border-emerald-300 shrink-0"
                                    alt="Foto baru">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-emerald-700">Foto baru dipilih</p>
                                    <p class="text-[11px] text-emerald-600 mt-0.5">Akan mengganti foto lama saat disimpan</p>
                                </div>
                            </div>
                        @endif

                        <input type="file"
                            wire:model="editPhoto"
                            accept="image/*"
                            class="w-full text-xs text-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition cursor-pointer">
                        <div wire:loading wire:target="editPhoto" class="text-xs text-muted-foreground mt-1">Mengunggah...</div>
                        @error('editPhoto') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/10">
                    <button type="button" wire:click="$set('showEdit', false)"
                        class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                        <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                        <span>{{ __('app.cancel') }}</span>
                    </button>
                    <button type="button" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit,editPhoto"
                        class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm inline-flex items-center gap-1.5 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveEdit,editPhoto">{{ __('app.save_changes') }}</span>
                        <span wire:loading wire:target="saveEdit,editPhoto" class="flex items-center gap-1.5">
                            <x-heroicon-o-arrow-path class="animate-spin h-3.5 w-3.5 text-primary-foreground"/>
                            {{ __('app.saving') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- IMAGE LIGHTBOX --}}
    <div
        x-data="{ open: false, src: '' }"
        @open-lightbox.window="open = true; src = $event.detail.src"
        @keydown.escape.window="open = false"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        @click.self="open = false"
        style="display:none">
        <button type="button" @click="open = false"
            class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img :src="src" alt="Bukti foto" class="max-w-full max-h-[90vh] rounded-xl shadow-2xl object-contain">
    </div>
</div>