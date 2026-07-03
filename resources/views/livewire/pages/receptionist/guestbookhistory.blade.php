<div class="min-h-screen bg-background" x-data="{ showFilterModal: false }" wire:poll.15s>
    @php
        use Carbon\Carbon;

        if (!function_exists('fmtDate')) {
            function fmtDate($v){
                try { return $v ? Carbon::parse($v)->format('d M Y') : '-'; }
                catch(\Throwable){ return '-'; }
            }
        }

        if (!function_exists('fmtTime')) {
            function fmtTime($v){
                try { return $v ? Carbon::parse($v)->format('H:i') : '-'; }
                catch(\Throwable){
                    if (is_string($v) && preg_match('/^\d{2}:\d{2}/',$v)) {
                        return substr($v,0,5);
                    }
                    return '-';
                }
            }
        }

        $card      = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
        $label     = 'block text-sm font-medium text-gray-700 mb-2';
        $input     = 'w-full h-10 px-3 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition';
        $btnBlk    = 'px-3 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm';
        $chip      = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-gray-100 text-xs';
        $icoAvatar = 'w-10 h-10 bg-[#4E653D] rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0';
    @endphp

    <style>
        :root { color-scheme: light; }
        select, option { color:#111827 !important; background:#ffffff !important; -webkit-text-fill-color:#111827 !important; }
        option:checked { background:#e5e7eb !important; color:#111827 !important; }
    </style>

    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Flash Messages --}}
        @if (session('success') || session('error'))
            <div class="max-w-3xl">
                @if (session('success'))
                    <div class="mb-2 rounded-md border border-success/30 bg-success/5 px-4 py-3 text-sm text-success">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-2 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        @endif

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.guestbook_history_title') }}"
            subtitle="{{ __('app.guestbook_history_subtitle') }}">
            <x-slot:actions>
                <button type="button" wire:click="$toggle('withTrashed')" class="flex items-center gap-2 group focus:outline-none">
                    <div class="relative flex items-center">
                        <div class="w-9 h-5 rounded-full transition-colors {{ $withTrashed ? 'bg-primary' : 'bg-border' }}"></div>
                        <div class="absolute left-[3px] w-3.5 h-3.5 rounded-full bg-white shadow transition-transform {{ $withTrashed ? 'translate-x-4' : '' }}"></div>
                    </div>
                    <span class="text-sm font-medium text-muted-foreground group-hover:text-foreground transition-colors">{{ __('app.show_deleted') }}</span>
                </button>
                <button type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-secondary text-secondary-foreground text-xs font-medium border border-border hover:bg-secondary/80 md:hidden transition"
                        @click="showFilterModal = true">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    <span>{{ __('app.filter') ?? 'Filter' }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- MAIN LAYOUT GRID --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            {{-- LEFT: LIST CARD (3 Columns) --}}
            <section class="{{ $card }} md:col-span-3">
                {{-- Header: tabs + view mode toggle --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.visit_list') }}</h3>
                            <p class="text-xs text-gray-500">
                                {{ __('app.visit_history_subtitle') }}
                            </p>
                        </div>

                        {{-- Tabs + View Mode Toggle --}}
                        <div class="flex items-center gap-3 self-start sm:self-auto">


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

                    {{-- Officer Badge Filter Status --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs mt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($petugasFilter)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30 font-medium">
                                    <x-heroicon-o-user class="w-3.5 h-3.5"/>
                                    <span>{{ __('app.officer') }}: {{ $petugasFilter }}</span>
                                    <button type="button" class="ml-1 hover:text-white font-bold" wire:click="clearPetugasFilter">×</button>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('app.search') }}</label>
                            <div class="relative">
                                <input type="text"
                                       class="{{ $input }} pl-9"
                                       placeholder="{{ __('app.search') }}..."
                                       wire:model.live.debounce.300ms="q">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <div class="relative">
                                <input type="date"
                                       class="{{ $input }} pl-9"
                                       wire:model.live="filter_date">
                                <x-heroicon-o-calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.sort') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: @entangle('dateMode').live,
                                    options: [
                                        { id: 'semua', label: '{{ __('app.sort_default') }}' },
                                        { id: 'terbaru', label: '{{ __('app.sort_newest') }}' },
                                        { id: 'terlama', label: '{{ __('app.sort_oldest') }}' }
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

                {{-- LIST AREA --}}

                    {{-- TAB: RIWAYAT KUNJUNGAN (DONE) --}}
                    @if($entries->isEmpty())
                        <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                            <x-heroicon-o-user-group class="w-8 h-8 mx-auto text-gray-300 mb-2"/>
                            {{ __('app.no_data') }}
                        </div>
                    @else
                        <div class="px-4 sm:px-6 py-5 bg-gray-50/30">
                            @if($viewMode === 'card')
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($entries as $e)
                                        @php
                                            $rowNo = ($entries->firstItem() ?? 1) + $loop->index;
                                            $stateKey = $e->deleted_at ? 'trash' : 'ok';
                                            $avatarChar = strtoupper(substr($e->name ?? 'G', 0, 1));
                                        @endphp
                                        <div wire:key="entry-card-{{ $e->guestbook_id }}-{{ $stateKey }}"
                                             class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition {{ $e->deleted_at ? 'opacity-60 bg-gray-50/50' : '' }}">
                                             
                                            <div class="flex items-start gap-4">
                                                    {{-- Avatar/Initial --}}
                                                    <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>

                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-3 min-w-0 mb-1">
                                                            <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                                {{ $e->name }}
                                                            </h4>
                                                            <div class="flex-shrink-0 flex items-center gap-2">
                                                                @if($e->deleted_at)
                                                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 flex-shrink-0">
                                                                        {{ strtoupper(__('app.deleted')) }}
                                                                    </span>
                                                                @endif
                                                                {{-- QR status badge --}}
                                                                @if($e->qr_token)
                                                                    @php
                                                                        $qrBadge = match($e->qr_status ?? 'pending') {
                                                                            'ongoing'   => ['bg-blue-50 text-blue-700 border-blue-100',   '&#128203; Sedang Berkunjung'],
                                                                            'completed' => ['bg-gray-100 text-gray-600 border-gray-200', '&#10003; Selesai'],
                                                                            default     => ['bg-amber-50 text-amber-700 border-amber-100','&#9201; Menunggu Scan'],
                                                                        };
                                                                    @endphp
                                                                    <span class="inline-flex items-center text-[10px] border px-2 py-0.5 rounded-full font-semibold {{ $qrBadge[0] }}">{!! $qrBadge[1] !!}</span>
                                                                    <span class="inline-flex items-center gap-1 text-[10px] text-gray-500 font-medium">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                                        {{ $e->visitor_count ?? 0 }} org
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @if($e->phone_number)
                                                            <p class="text-xs text-gray-500 font-mono">{{ $e->phone_number }}</p>
                                                        @endif

                                                {{-- Middle: Details --}}
                                                <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2 mt-3">
                                                    @if($e->instansi)
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-building-office class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span class="truncate">{{ __('app.institution') }}: <span class="font-semibold text-gray-900">{{ $e->instansi }}</span></span>
                                                        </div>
                                                    @endif
                                                    @if($e->keperluan)
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-500 shrink-0"/>
                                                            <span class="truncate">{{ __('app.visit_purpose') }}: <span class="font-semibold text-gray-900">{{ $e->keperluan }}</span></span>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- BOTTOM LEFT: Time and Officer --}}
                                                <div class="text-[12px] text-gray-600 space-y-2 mt-2">
                                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 bg-gray-50 border border-gray-100 rounded-lg p-2">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <x-heroicon-o-calendar class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                            <span class="truncate font-medium text-gray-700">{{ fmtDate($e->date) }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                            <span class="truncate font-medium text-emerald-600">{{ fmtTime($e->jam_in) }}</span>
                                                            <span class="text-gray-400 font-medium">-</span>
                                                            <span class="truncate font-medium text-rose-600">{{ fmtTime($e->jam_out) }}</span>
                                                        </div>
                                                        @if($e->petugas_penjaga)
                                                            <div class="col-span-2 flex items-center gap-1.5 min-w-0 pt-1 border-t border-gray-200/50 mt-1">
                                                                <x-heroicon-o-user class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                                <span class="truncate font-medium text-gray-600">{{ __('app.officer') }}: <span class="text-gray-900 font-semibold">{{ $e->petugas_penjaga }}</span></span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            </div>

                                            <div class="pt-3 border-t border-gray-100 mt-4 flex items-end justify-between">
                                                <div class="flex flex-col gap-1.5 mr-auto">
                                                    <span class="text-[11px] text-gray-500">No. {{ $rowNo }}</span>
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        @if($e->deleted_at)
                                                            <span class="inline-flex items-center text-[10px] text-rose-700 bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-full font-semibold">{{ strtoupper(__('app.deleted')) }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex gap-1.5 font-medium shrink-0">
                                                    <button wire:click="openEdit({{ $e->guestbook_id }})"
                                                            wire:loading.attr="disabled"
                                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition shadow-sm">
                                                        {{ __('app.edit') }}
                                                    </button>
                                                    @if(!$e->deleted_at)
                                                        <button wire:click="confirmDelete({{ $e->guestbook_id }}, '{{ str_replace('\'', '', $e->name) }}', false)"
                                                                wire:loading.attr="disabled"
                                                                class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none transition">{{ __('app.delete') }}</button>
                                                    @else
                                                        <button wire:click="restore({{ $e->guestbook_id }})"
                                                                wire:loading.attr="disabled"
                                                                class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-[#4E653D] border border-emerald-200 hover:bg-emerald-100 focus:outline-none transition">
                                                            {{ __('app.restore') }}
                                                        </button>
                                                        <button wire:click="confirmDelete({{ $e->guestbook_id }}, '{{ str_replace('\'', '', $e->name) }}', true)"
                                                                wire:loading.attr="disabled"
                                                                class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-100 text-rose-800 hover:bg-rose-200 focus:outline-none transition">{{ __('app.delete') }}</button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- Table View --}}
                                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 bg-gray-50/50">
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.name_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.institution_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.purpose_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.date_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.check_in_out_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.officer_col') }}</th>
                                                <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.status') }}</th>
                                                <th class="h-10 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach ($entries as $e)
                                                @php
                                                    $rowNo = ($entries->firstItem() ?? 1) + $loop->index;
                                                    $stateKey = $e->deleted_at ? 'trash' : 'ok';
                                                @endphp
                                                <tr wire:key="entry-{{ $e->guestbook_id }}-{{ $stateKey }}"
                                                    class="hover:bg-gray-50/50 transition-colors {{ $e->deleted_at ? 'opacity-60 bg-gray-50/20' : '' }}">
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-400 text-xs font-mono">
                                                        {{ $rowNo }}
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 ">
                                                        <div class="flex items-center gap-2.5">
                                                            <div class="w-7 h-7 rounded-full bg-[#4E653D] text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                                                {{ strtoupper(substr($e->name ?? 'G', 0, 1)) }}
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="font-semibold text-gray-900 truncate">{{ $e->name }}</p>
                                                                @if($e->phone_number)
                                                                    <p class="text-xs text-gray-400 font-mono">{{ $e->phone_number }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-600 truncate font-medium">
                                                        {{ $e->instansi ?? '-' }}
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-600 truncate font-medium">
                                                        {{ $e->keperluan ?? '-' }}
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-900 font-medium whitespace-nowrap">
                                                        {{ fmtDate($e->date) }}
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-500 whitespace-nowrap">
                                                        <span class="text-emerald-600 font-semibold">{{ fmtTime($e->jam_in) }}</span>
                                                        <span class="mx-1 text-gray-300">–</span>
                                                        <span class="text-rose-600 font-semibold">{{ fmtTime($e->jam_out) }}</span>
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 text-gray-900 font-semibold">
                                                        {{ $e->petugas_penjaga }}
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0 ">
                                                        <div class="flex flex-col justify-center">
                                                            @if($e->deleted_at)
                                                                <span class="inline-flex items-center text-[10px] text-rose-700 bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-full font-semibold">{{ strtoupper(__('app.deleted')) }}</span>
                                                            @endif
                                                            {{-- QR status --}}
                                                            @if($e->qr_token)
                                                                @php
                                                                    $qrBadgeT = match($e->qr_status ?? 'pending') {
                                                                        'ongoing'   => 'bg-blue-50 text-blue-700 border-blue-100',
                                                                        'completed' => 'bg-gray-100 text-gray-600 border-gray-200',
                                                                        default     => 'bg-amber-50 text-amber-700 border-amber-100',
                                                                    };
                                                                    $qrLabelT = match($e->qr_status ?? 'pending') {
                                                                        'ongoing'   => 'Berkunjung',
                                                                        'completed' => 'Selesai',
                                                                        default     => 'QR: Pending',
                                                                    };
                                                                @endphp
                                                                <span class="mt-1 inline-flex items-center text-[10px] border px-2 py-0.5 rounded-full font-semibold {{ $qrBadgeT }}">{{ $qrLabelT }}</span>
                                                                <span class="block text-[10px] text-gray-400 mt-0.5">{{ $e->visitor_count ?? 0 }} org</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="h-12 px-4 py-0">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <button wire:click="openEdit({{ $e->guestbook_id }})"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="openEdit({{ $e->guestbook_id }})"
                                                                    class="p-1.5 rounded-lg text-gray-500 hover:text-white hover:bg-[#4E653D] transition-colors"
                                                                    title="{{ __('app.edit') }}">
                                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                            </button>
                                                            @if(!$e->deleted_at)
                                                                <button wire:click="confirmDelete({{ $e->guestbook_id }}, '{{ str_replace('\'', '', $e->name) }}', false)"
                                                                        wire:loading.attr="disabled"
                                                                        class="p-1.5 rounded-lg text-gray-500 hover:text-rose-700 hover:bg-rose-50 transition-colors"
                                                                        title="{{ __('app.delete') }}">
                                                                    <x-heroicon-o-trash class="w-4 h-4" />
                                                                </button>
                                                            @else
                                                                <button wire:click="restore({{ $e->guestbook_id }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="restore({{ $e->guestbook_id }})"
                                                                        class="p-1.5 rounded-lg text-gray-500 hover:text-[#4E653D] hover:bg-emerald-50 transition-colors"
                                                                        title="{{ __('app.restore') }}">
                                                                    <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                                                                </button>
                                                                <button wire:click="confirmDelete({{ $e->guestbook_id }}, '{{ str_replace('\'', '', $e->name) }}', true)"
                                                                        wire:loading.attr="disabled"
                                                                        class="p-1.5 rounded-lg text-gray-500 hover:text-rose-700 hover:bg-rose-100 transition-colors"
                                                                        title="{{ __('app.delete_permanent') }}">
                                                                    <x-heroicon-o-x-circle class="w-4 h-4" />
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


                {{-- PAGINATION --}}
                <div class="px-4 sm:px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="w-full">
                            {{ $entries->onEachSide(1)->links() }}
                    </div>
                </div>
            </section>

            {{-- RIGHT: SIDEBAR (OFFICER FILTER) (1 Column) --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                <section class="{{ $card }}">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_officer') ?? 'Filter by Officer' }}</p>
                    </div>

                    <div class="p-4 space-y-4 bg-white">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ __('app.officer') ?? 'Officer' }}</label>
                            <div class="px-1 py-1 max-h-80 overflow-y-auto">
                                {{-- All Officers --}}
                                <button type="button"
                                        wire:click="clearPetugasFilter"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors mb-1.5
                                            {{ is_null($petugasFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">All</span>
                                        <span>{{ __('app.all_officers') }}</span>
                                    </span>
                                </button>

                                {{-- Each Officer option --}}
                                <div class="mt-2 space-y-1.5">
                                    @forelse($petugasOptions as $p)
                                        @php $active = !is_null($petugasFilter) && $petugasFilter === $p; @endphp
                                        <button type="button"
                                                wire:click="selectPetugas('{{ $p }}')"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                                    {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                            <span class="flex items-center gap-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                                    {{ substr($p, 0, 2) }}
                                                </span>
                                                <span class="truncate font-medium">{{ $p }}</span>
                                            </span>
                                        </button>
                                    @empty
                                        <p class="text-[11px] text-gray-400 text-center py-4">{{ __('app.no_data') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </main>

        {{-- EDIT MODAL (Matching premium modal style from booking history) --}}
        @if ($showEdit)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
                 x-data x-on:keydown.escape.window="$wire.closeEdit()">
                {{-- Backdrop with blur --}}
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-300" wire:click="closeEdit"></div>

                {{-- Modal container --}}
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden max-h-[90vh] flex flex-col transform transition-all duration-300 scale-100">
                    {{-- Header --}}
                    <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                                <x-heroicon-o-pencil class="w-4 h-4 text-[#CDDEA7]" />
                            </div>
                            <h3 class="text-base font-bold tracking-tight">{{ __('app.edit_entry_title') }}</h3>
                        </div>
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeEdit">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="p-6 overflow-y-auto flex-1 space-y-4">
                        <form wire:submit.prevent="saveEdit" class="space-y-4">
                            {{-- Nama --}}
                            <div>
                                <label for="edit_name" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.guest_name_label') }} <span class="text-rose-500">*</span></label>
                                <input type="text" id="edit_name" class="{{ $input }}" wire:model="edit.name">
                                @error('edit.name') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- No HP --}}
                            <div>
                                <label for="edit_phone_number" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.no_hp_label') }}</label>
                                <input type="text" id="edit_phone_number" class="{{ $input }}" wire:model="edit.phone_number">
                                @error('edit.phone_number') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- Instansi --}}
                            <div>
                                <label for="edit_instansi" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.institution_col') }}</label>
                                <input type="text" id="edit_instansi" class="{{ $input }}" wire:model="edit.instansi">
                                @error('edit.instansi') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- Keperluan --}}
                            <div>
                                <label for="edit_keperluan" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.purpose_col') }} <span class="text-rose-500">*</span></label>
                                <textarea id="edit_keperluan" rows="3" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition-all resize-none" wire:model="edit.keperluan"></textarea>
                                @error('edit.keperluan') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- Petugas Penjaga --}}
                            <div>
                                <label for="edit_petugas_penjaga" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.petugas_label') }} <span class="text-rose-500">*</span></label>
                                <input type="text" id="edit_petugas_penjaga" class="{{ $input }}" wire:model="edit.petugas_penjaga">
                                @error('edit.petugas_penjaga') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- Visitor Count --}}
                            <div>
                                <label for="edit_visitor_count" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.visitors_count') ?? 'Visitor Count' }}</label>
                                <input type="number" id="edit_visitor_count" min="0" class="{{ $input }}" wire:model.defer="edit.visitor_count">
                                @error('edit.visitor_count') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>

                            {{-- Date / Jam In / Jam Out --}}
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label for="edit_date" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.date_col') }} <span class="text-rose-500">*</span></label>
                                    <input type="date" id="edit_date" class="{{ $input }}" wire:model="edit.date">
                                    @error('edit.date') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="edit_jam_in" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.jam_masuk_label') }} <span class="text-rose-500">*</span></label>
                                    <input type="time" id="edit_jam_in" class="{{ $input }}" wire:model="edit.jam_in">
                                    @error('edit.jam_in') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="edit_jam_out" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">{{ __('app.jam_keluar_label') }}</label>
                                    <input type="time" id="edit_jam_out" class="{{ $input }}" wire:model="edit.jam_out">
                                    @error('edit.jam_out') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Footer actions --}}
                            <div class="pt-5 border-t border-gray-200 flex items-center justify-end gap-3 bg-gray-50/50 -mx-6 -mb-6 p-4">
                                <button type="button"
                                        class="h-9 px-4 rounded-lg bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition text-xs font-semibold"
                                        wire:click="closeEdit">{{ __('app.cancel') }}</button>
                                <button type="submit"
                                        class="h-9 px-4 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition shadow-sm flex items-center gap-1.5"
                                        wire:loading.attr="disabled" wire:target="saveEdit">
                                    <span wire:loading.remove wire:target="saveEdit" class="flex items-center gap-1.5">
                                        <x-heroicon-o-check class="w-3.5 h-3.5" />
                                        {{ __('app.save_changes') }}
                                    </span>
                                    <span wire:loading wire:target="saveEdit" class="flex items-center gap-1.5">
                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ __('app.saving') }}</span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

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
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_officer') ?? 'Filter by Officer' }}</p>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" @click="showFilterModal = false">✕</button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto flex-1 bg-white">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 flex items-center gap-1.5">{{ __('app.officer') ?? 'Officer' }}</h4>

                        <button type="button"
                                wire:click="clearPetugasFilter"
                                @click="showFilterModal = false"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors mb-2
                                    {{ is_null($petugasFilter) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                            <span class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                    {{ __('app.all') ?? 'All' }}
                                </span>
                                <span>{{ __('app.all_officers') }}</span>
                            </span>
                        </button>

                        <div class="mt-2 space-y-1.5">
                            @forelse($petugasOptions as $p)
                                @php $active = !is_null($petugasFilter) && $petugasFilter === $p; @endphp
                                <button type="button"
                                        wire:click="selectPetugas('{{ $p }}')"
                                        @click="showFilterModal = false"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                            {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                            {{ substr($p, 0, 2) }}
                                        </span>
                                        <span class="truncate font-medium">{{ $p }}</span>
                                    </span>
                                </button>
                            @empty
                                <p class="text-[11px] text-gray-400 text-center py-4">{{ __('app.no_data') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-gray-200 bg-gray-50">
                    <button type="button"
                            class="w-full h-10 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition-colors shadow-sm"
                            @click="showFilterModal = false">
                        {{ __('app.apply_close') ?? 'Apply & Close' }}
                    </button>
                </div>
            </div>
        </div>

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
                        <h3 class="font-bold tracking-tight text-base">{{ __('app.delete_verification') ?? 'Delete Verification' }}</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="$set('showDeleteModal', false)">✕</button>
                </div>
                <div class="p-6 text-center bg-white">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">
                            {{ $isForceDelete ? __('app.delete_permanent_confirm') : __('app.delete_entry_confirm') }}
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
                                <x-heroicon-o-arrow-path class="animate-spin h-3.5 w-3.5 text-white"/>
                                {{ __('app.delete') }}...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
</div>
