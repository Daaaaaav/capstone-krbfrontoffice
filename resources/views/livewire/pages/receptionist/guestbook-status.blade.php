<div class="min-h-screen bg-background">
    {{-- Polling trigger: refreshes data every 30s without putting wire:poll on the root element --}}
    <div wire:poll.30s wire:key="guestbook-poll-trigger" class="hidden" aria-hidden="true"></div>
    @php
        use Carbon\Carbon;
        $card      = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
        $label     = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1';
        $input     = 'w-full h-9 sm:h-10 px-3 sm:px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition-all';
        $icoAvatar = 'w-10 h-10 rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0';

        function gbsFmtTime($v) {
            if (!$v) return 'â€”';
            try { return Carbon::parse($v)->format('H:i'); } catch (\Throwable $e) { return is_string($v) ? substr($v, 0, 5) : 'â€”'; }
        }
        function gbsFmtDate($v) {
            if (!$v) return 'â€”';
            try { return Carbon::parse($v)->format('d M Y'); } catch (\Throwable $e) { return 'â€”'; }
        }
    @endphp

    <main class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.guestbook_status_title') }}"
            subtitle="{{ __('app.guestbook_status_subtitle') }}" />



        {{-- Main card --}}
        <div class="{{ $card }}">

            {{-- Card Header & Toggler --}}
            <div class="px-3 sm:px-6 pt-3 sm:pt-4 pb-2 sm:pb-3 border-b border-gray-200 space-y-2 sm:space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.guestbook_status_title') }}</h3>
                        <p class="text-xs text-gray-500">
                            {{ __('app.guestbook_status_subtitle') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 self-start sm:self-auto">
                        @if($petugasFilter)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#4A2F24] text-[#CDDEA7] text-xs font-semibold">
                                <x-heroicon-o-user class="w-3 h-3"/>
                                {{ $petugasFilter }}
                                <button type="button" wire:click="clearPetugasFilter" class="ml-0.5 hover:text-white font-bold">Ã—</button>
                            </span>
                        @endif

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
            </div>

            {{-- Filters --}}
            <div class="px-3 sm:px-6 pt-3 sm:pt-4 pb-2 sm:pb-3 border-b border-gray-200">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 sm:gap-4">
                    <div class="col-span-2 md:col-span-1">
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

            {{-- Card grid --}}
            <div class="p-3 sm:p-6">

                {{-- ===== ACTIVE GUESTS ===== --}}
                @if($activeEntries->isEmpty())
                    <div class="py-16 text-center text-gray-500 text-sm">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-[#4E653D]/10 border border-[#4E653D]/20 flex items-center justify-center">
                            <x-heroicon-o-user-group class="w-7 h-7 text-[#4E653D]"/>
                        </div>
                        <p class="font-semibold text-gray-700">Tidak ada tamu yang aktif</p>
                        <p class="text-xs text-gray-400 mt-1">Belum ada kunjungan yang berlangsung saat ini</p>
                    </div>
                @else
                    @if($viewMode === 'card')
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
                            @foreach($activeEntries as $e)
                                @php
                                    $avatarChar  = strtoupper(substr($e->name ?? 'G', 0, 1));
                                    $scans       = $e->scans()->orderByDesc('scanned_at')->limit(5)->get();
                                    $isScheduled = (bool) ($e->scheduled_by_manager ?? false);
                                    $cardBorder  = $isScheduled ? 'border-violet-300 bg-violet-50/40' : 'border-[#4E653D]/25 bg-white';
                                    $cardHover   = $isScheduled ? 'hover:border-violet-400 hover:shadow-violet-100' : 'hover:border-[#4E653D]/40';
                                    $avatarBg    = $isScheduled ? 'bg-violet-600' : 'bg-[#4E653D]';
                                @endphp
                                <div wire:key="active-{{ $e->guestbook_id }}"
                                     class="{{ $cardBorder }} border rounded-xl p-3 sm:p-4 flex flex-col gap-2 sm:gap-3 hover:shadow-md {{ $cardHover }} transition">
                                    {{-- Scheduled Guest banner --}}
                                    @if($isScheduled)
                                        <div class="flex items-center gap-1.5 -mx-3 sm:-mx-4 -mt-3 sm:-mt-4 px-3 sm:px-4 py-1.5 bg-amber-500 rounded-t-xl">
                                            <svg class="w-3 h-3 text-amber-100 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-50">Scheduled Guest</span>
                                        </div>
                                    @endif
                                    {{-- Header --}}
                                    <div class="flex items-start gap-3">
                                        <div class="{{ $icoAvatar }} {{ $avatarBg }}">{{ $avatarChar }}</div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="font-semibold text-gray-900 truncate">{{ $e->name }}</p>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    {{-- Live elapsed timer --}}
                                                    @php
                                                        $tsMs = null;
                                                        try { $tsMs = Carbon::parse($e->date->format('Y-m-d') . ' ' . $e->jam_in)->getTimestampMs(); } catch (\Throwable $th) {}
                                                    @endphp
                                                    @if($tsMs)
                                                        <span class="elapsed-timer inline-flex items-center gap-1 h-8 px-2.5 rounded-full bg-[#4A2F24] text-white text-[10px] font-bold font-mono" title="Durasi kunjungan" data-start="{{ $tsMs }}">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            <span class="timer-text">00:00:00</span>
                                                        </span>
                                                    @endif
                                                    @if($e->storage_place)
                                                        <div class="w-8 h-8 rounded-full bg-[#4E653D] text-white flex items-center justify-center text-sm font-bold shrink-0 shadow-sm" title="Tempat Penyimpanan">
                                                            {{ $e->storage_place }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($e->instansi)
                                                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $e->instansi }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Details --}}
                                    <div class="space-y-1 text-xs text-gray-600 {{ $isScheduled ? 'bg-amber-50 border-amber-100' : 'bg-gray-50 border-gray-100' }} rounded-lg p-2.5 border">
                                        @if($e->keperluan)
                                            <div class="flex gap-1.5">
                                                <span class="text-gray-400 shrink-0">{{ __('app.visit_purpose_label') }}:</span>
                                                <span class="font-medium text-gray-800 truncate">{{ $e->keperluan }}</span>
                                            </div>
                                        @endif
                                        <div class="flex gap-1.5">
                                            <span class="text-gray-400 shrink-0">{{ $isScheduled ? 'Scheduled' : __('app.check_in_label') }}:</span>
                                            <span class="font-semibold {{ $isScheduled ? 'text-amber-700' : 'text-emerald-700' }}">{{ gbsFmtDate($e->date) }} Â· {{ gbsFmtTime($e->jam_in) }}</span>
                                        </div>
                                        @if($e->email && !$e->qr_status)
                                            <div class="flex gap-1.5">
                                                <span class="text-gray-400 shrink-0">{{ __('app.email') }}:</span>
                                                <span class="font-medium text-gray-700 truncate">{{ $e->email }}</span>
                                            </div>
                                        @endif
                                        <div class="flex gap-1.5">
                                            <span class="text-gray-400 shrink-0">{{ __('app.officer_label') }}:</span>
                                            <span class="font-medium {{ $isScheduled ? 'text-amber-700' : 'text-gray-700' }} truncate">{{ $e->petugas_penjaga }}</span>
                                        </div>
                                    </div>

                                    {{-- QR Info / Recent Scans --}}
                                    @if($e->qr_status === 'ongoing' && $scans->count())
                                        <div class="rounded-lg border border-[#4A2F24]/15 bg-[#4A2F24]/5 px-2.5 py-2 space-y-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-[#4A2F24]/70 mb-1.5">{{ __('app.visitors_present') }}</p>
                                            @foreach($scans as $scan)
                                                <div class="flex items-center gap-2 text-xs">
                                                    <div class="w-5 h-5 rounded-full bg-[#4A2F24]/15 flex items-center justify-center text-[9px] font-bold text-[#4A2F24] shrink-0">
                                                        {{ strtoupper(substr($scan->visitor_name ?? 'G', 0, 1)) }}
                                                    </div>
                                                    <span class="font-medium text-gray-800 truncate">{{ $scan->visitor_name ?? __('app.no_data') }}</span>
                                                    <span class="ml-auto text-[10px] text-gray-400 shrink-0">{{ \Carbon\Carbon::parse($scan->scanned_at)->format('H:i') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($e->qr_token && $e->qr_status !== 'ongoing')
                                        <div class="flex items-center justify-between text-[11px] bg-[#4E653D]/5 rounded-lg px-2.5 py-1.5 border border-[#4E653D]/15">
                                            <div class="flex items-center gap-2 text-[#4E653D]">
                                                <x-heroicon-o-qr-code class="w-3.5 h-3.5 shrink-0"/>
                                                <span class="truncate">{{ __('app.qr_sent_not_scanned') }}</span>
                                            </div>
                                            @if($e->email)
                                                <button wire:click="resendQr({{ $e->guestbook_id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="resendQr({{ $e->guestbook_id }})"
                                                        class="shrink-0 text-[#4A2F24] hover:underline font-semibold ml-2 focus:outline-none flex items-center gap-1">
                                                    <span wire:loading.remove wire:target="resendQr({{ $e->guestbook_id }})">
                                                        {{ __('app.resend_qr') }}
                                                    </span>
                                                    <span wire:loading wire:target="resendQr({{ $e->guestbook_id }})" class="flex items-center gap-0.5">
                                                        <x-heroicon-o-arrow-path class="animate-spin w-3.5 h-3.5" />
                                                        Resending...
                                                    </span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Actions --}}
                                    <div class="pt-2 {{ $isScheduled ? 'border-amber-100' : 'border-gray-100' }} border-t flex items-center justify-end gap-1.5 mt-auto">
                                        <button wire:click="openEdit({{ $e->guestbook_id }})"
                                                class="px-2 py-1.5 sm:px-2.5 text-xs font-semibold rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition focus:outline-none">
                                            <span class="hidden sm:inline">{{ __('app.edit') }}</span>
                                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5 sm:hidden"/>
                                        </button>
                                        @if($e->qrCodes()->count() > 0)
                                            <a href="{{ route('receptionist.guestbook.checkout', $e->guestbook_id) }}"
                                               class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#4A2F24] text-[#CDDEA7] hover:bg-[#3a2319] transition shadow-sm focus:outline-none">
                                                <x-heroicon-o-qr-code class="w-3.5 h-3.5 shrink-0"/>
                                                <span class="hidden sm:inline">Scan Checkout</span>
                                                @if($e->qr_status === 'ongoing')
                                                    <span class="text-[10px] font-bold">({{ $e->scannedQrCount() }}/{{ $e->visitor_count }})</span>
                                                @endif
                                            </a>
                                        @endif
                                        <button wire:click="checkOutNow({{ $e->guestbook_id }})"
                                                wire:confirm="{{ __('app.checkout_confirm') }}"
                                                class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition shadow-sm focus:outline-none">
                                            <x-heroicon-o-arrow-right-start-on-rectangle class="w-3.5 h-3.5 shrink-0"/>
                                            <span class="hidden sm:inline">{{ __('app.checkout_btn') }}</span>
                                        </button>
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
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.check_in_label') }}</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Durasi</th>
                                        <th class="h-10 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.status') }}</th>
                                        <th class="h-10 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('app.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($activeEntries as $e)
                                        @php
                                            $rowNo       = ($activeEntries->firstItem() ?? 1) + $loop->index;
                                            $avatarChar  = strtoupper(substr($e->name ?? 'G', 0, 1));
                                            $scans       = $e->scans()->orderByDesc('scanned_at')->limit(5)->get();
                                            $isScheduled = (bool) ($e->scheduled_by_manager ?? false);
                                        @endphp
                                        <tr wire:key="entry-table-{{ $e->guestbook_id }}" class="{{ $isScheduled ? 'bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-gray-50/50' }} transition-colors">
                                            
                                            <td class="h-12 px-4 py-0 text-gray-400 text-xs font-mono">
                                                {{ $rowNo }}
                                            </td>
                                            
                                            <td class="h-12 px-4 py-0 ">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-7 h-7 rounded-full {{ $isScheduled ? 'bg-amber-500' : 'bg-[#4E653D]' }} text-white flex items-center justify-center text-xs font-semibold shrink-0">
                                                        {{ $avatarChar }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <p class="font-semibold text-gray-900 truncate">{{ $e->name }}</p>
                                                            @if($isScheduled)
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[9px] font-bold uppercase tracking-wide shrink-0">
                                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                                    Sched.
                                                                </span>
                                                            @endif
                                                            @if($e->storage_place)
                                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[#4E653D] text-white text-[10px] font-bold shadow-sm" title="Tempat Penyimpanan">{{ $e->storage_place }}</span>
                                                            @endif
                                                        </div>
                                                        @if($e->email && !$e->qr_status)
                                                            <p class="text-xs text-gray-400 font-mono">{{ $e->email }}</p>
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
                                            
                                            <td class="h-12 px-4 py-0 text-gray-500 whitespace-nowrap">
                                                <div class="flex flex-col">
                                                    <div>
                                                        <span class="text-gray-900 font-medium">{{ gbsFmtDate($e->date) }}</span>
                                                        <span class="mx-1 text-gray-300">Â·</span>
                                                        <span class="font-semibold {{ $isScheduled ? 'text-amber-600' : 'text-emerald-600' }}">{{ gbsFmtTime($e->jam_in) }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-400 mt-0.5 truncate">{{ __('app.officer_label') }}: {{ $e->petugas_penjaga }}</div>
                                                </div>
                                            </td>

                                            {{-- Elapsed time column --}}
                                            <td class="h-12 px-4 py-0">
                                                @php
                                                    $tsMs2 = null;
                                                    try { $tsMs2 = Carbon::parse($e->date->format('Y-m-d') . ' ' . $e->jam_in)->getTimestampMs(); } catch (\Throwable $th) {}
                                                @endphp
                                                @if($tsMs2)
                                                    <span class="elapsed-timer inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#4A2F24] text-white text-[10px] font-bold font-mono" data-start="{{ $tsMs2 }}">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span class="timer-text">00:00:00</span>
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-400">â€”</span>
                                                @endif
                                            </td>
                                            
                                            <td class="h-12 px-4 py-0 ">
                                                <div class="flex flex-col justify-center">
                                                    @if($e->qr_status === 'ongoing' && $scans->count())
                                                        <span class="inline-flex items-center text-[10px] border border-blue-100 bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold">
                                                            Berkunjung ({{ $e->scannedQrCount() }}/{{ $e->visitor_count }})
                                                        </span>
                                                    @elseif($e->qr_token && $e->qr_status !== 'ongoing')
                                                        <div class="flex flex-col gap-1">
                                                            <span class="inline-flex items-center text-[10px] border border-amber-100 bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold">
                                                                QR: Pending ({{ $e->visitor_count ?? 0 }} org)
                                                            </span>
                                                            @if($e->email)
                                                                <button wire:click="resendQr({{ $e->guestbook_id }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="resendQr({{ $e->guestbook_id }})"
                                                                        class="text-[10px] text-[#4A2F24] hover:underline font-semibold focus:outline-none flex items-center gap-1">
                                                                    <span wire:loading.remove wire:target="resendQr({{ $e->guestbook_id }})">Resend QR</span>
                                                                    <span wire:loading wire:target="resendQr({{ $e->guestbook_id }})">Resending...</span>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="inline-flex items-center text-[10px] border border-emerald-100 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-semibold">
                                                            Aktif
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            
                                            <td class="h-12 px-4 py-0">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button wire:click="openEdit({{ $e->guestbook_id }})"
                                                            wire:loading.attr="disabled"
                                                            class="p-1.5 rounded-lg text-gray-500 hover:text-white hover:bg-[#4E653D] transition-colors"
                                                            title="{{ __('app.edit') }}">
                                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                    </button>
                                                    @if($e->qrCodes()->count() > 0)
                                                        <a href="{{ route('receptionist.guestbook.checkout', $e->guestbook_id) }}"
                                                           class="p-1.5 rounded-lg text-[#CDDEA7] bg-[#4A2F24] hover:bg-[#3a2319] transition-colors"
                                                           title="Scan Checkout">
                                                            <x-heroicon-o-qr-code class="w-4 h-4" />
                                                        </a>
                                                    @endif
                                                    <button wire:click="checkOutNow({{ $e->guestbook_id }})"
                                                            wire:confirm="{{ __('app.checkout_confirm') }}"
                                                            class="p-1.5 rounded-lg text-white bg-[#4E653D] hover:bg-[#354C2B] transition-colors"
                                                            title="{{ __('app.checkout_btn') }}">
                                                        <x-heroicon-o-arrow-right-start-on-rectangle class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <div class="mt-5" wire:key="guestbook-pagination">{{ $activeEntries->links() }}</div>
                @endif

            </div>
        </div>

    </main>

    {{-- ===== EDIT MODAL ===== --}}
    <div wire:key="guestbook-edit-modal-wrapper">
    @if($showEdit)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showEdit', false)"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-[#4A2F24] px-6 py-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-[#CDDEA7]">{{ __('app.edit_guest_title') }}</h3>
                    <button wire:click="$set('showEdit', false)" class="text-[#CDDEA7]/60 hover:text-[#CDDEA7]">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    @php
                        $mi = 'w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition-all';
                        $ml = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1';
                    @endphp
                    <div>
                        <label class="{{ $ml }}">{{ __('app.full_name_required') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="edit.name" class="{{ $mi }}">
                        @error('edit.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.email') }}</label>
                        <input type="email" wire:model.defer="edit.email" class="{{ $mi }}">
                        @error('edit.email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.phone') }}</label>
                        <input type="text" wire:model.defer="edit.phone_number" class="{{ $mi }}">
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.institution') }}</label>
                        <input type="text" wire:model.defer="edit.instansi" class="{{ $mi }}">
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.visit_purpose') }}</label>
                        <input type="text" wire:model.defer="edit.keperluan" class="{{ $mi }}">
                    </div>
                    <div>
                        <label class="{{ $ml }}">{{ __('app.officer_label') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="edit.petugas_penjaga" class="{{ $mi }}">
                        @error('edit.petugas_penjaga') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $ml }}">Jumlah Pengunjung <span class="text-rose-500">*</span></label>
                        <input type="number" min="1" max="999" wire:model.defer="edit.visitor_count" class="{{ $mi }}">
                        @error('edit.visitor_count') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="px-6 pb-6 flex justify-end gap-2">
                    <button wire:click="$set('showEdit', false)"
                            class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                        {{ __('app.cancel') }}
                    </button>
                    <button wire:click="saveEdit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition shadow-sm">
                        {{ __('app.save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
    </div>{{-- end wire:key="guestbook-edit-modal-wrapper" --}}

    <script>
        function updateElapsedTimers() {
            document.querySelectorAll('.elapsed-timer').forEach(function(el) {
                var start = parseInt(el.getAttribute('data-start'));
                if (!start) return;
                var diff = Math.max(0, Date.now() - start);
                var totalSec = Math.floor(diff / 1000);
                var h = Math.floor(totalSec / 3600);
                var m = Math.floor((totalSec % 3600) / 60);
                var s = totalSec % 60;
                var text = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                var span = el.querySelector('.timer-text');
                if (span) span.textContent = text;
            });
        }
        updateElapsedTimers();
        setInterval(updateElapsedTimers, 1000);
        // Re-init after Livewire updates the DOM
        if (window.Livewire) {
            document.addEventListener('livewire:navigated', updateElapsedTimers);
            document.addEventListener('livewire:morph:updated', updateElapsedTimers);
        }
    </script>

</div>

