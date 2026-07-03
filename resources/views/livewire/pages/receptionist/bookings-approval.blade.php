<div class="min-h-screen bg-background" wire:poll.1000ms.keep-alive x-data="{ showFilterModal: false }">
    @php
    use Carbon\Carbon;
    use App\Models\Requirement; // ADDED: Required for the temporary bug workaround

    if (!function_exists('fmtDate')) {
        function fmtDate($v) {
            try { return $v ? Carbon::parse($v)->format('d M Y') : '—'; }
            catch (\Throwable) { return '—'; }
        }
    }
    if (!function_exists('fmtTime')) {
        function fmtTime($v) {
            try { return $v ? Carbon::parse($v)->format('H:i') : '—'; }
            catch (\Throwable) {
                if (is_string($v)) {
                    if (preg_match('/^\d{2}:\d{2}/', $v)) return substr($v,0,5);
                    if (preg_match('/^\d{2}\.\d{2}/', $v)) return str_replace('.',':', substr($v,0,5));
                }
                return '—';
            }
        }
    }
    if (!function_exists('canRejectBooking')) {
        /**
         * Check if a booking can still be rejected.
         * Returns false if less than 30 minutes remain before meeting start.
         */
        function canRejectBooking($booking) {
            try {
                $tz = 'Asia/Jakarta';
                $now = Carbon::now($tz);
                $dateVal = $booking->date;
                $timeVal = $booking->start_time;

                // Build start datetime
                if (is_string($timeVal) && preg_match('/^\d{4}-\d{2}-\d{2}/', $timeVal)) {
                    $start = Carbon::parse($timeVal, $tz);
                } elseif (is_string($dateVal) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:/', $dateVal)) {
                    $start = Carbon::parse($dateVal, $tz);
                    if (is_string($timeVal) && preg_match('/^\d{2}:\d{2}/', $timeVal)) {
                        $start = $start->setTimeFromTimeString($timeVal);
                    }
                } else {
                    $start = Carbon::parse(trim($dateVal . ' ' . ($timeVal ?: '00:00:00')), $tz);
                }

                // If less than 30 minutes remain before start, cannot reject
                return $now->diffInMinutes($start, false) >= 30;
            } catch (\Throwable) {
                return true; // Allow rejection if we can't parse the time
            }
        }
    }

    /** @var int|null $roomFilterId */
    $roomFilterId = $roomFilterId ?? null;

    $card       = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    $label      = 'block text-sm font-medium text-gray-700 mb-2';
    $input      = 'w-full h-10 px-3 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition';
    $textareaInput = 'w-full px-3 py-2 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 bg-white transition'; // New variable for textarea
    $btnBlk     = 'px-3 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm';
    $btnGhost   = 'px-3 py-2 text-xs font-medium rounded-lg bg-[#4A2F24]/10 text-[#4A2F24] border border-[#4A2F24]/20 hover:bg-[#4A2F24]/20 focus:outline-none transition';
    $chip       = 'inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-gray-100 text-xs';
    $icoAvatar = 'w-10 h-10 bg-[#4E653D] rounded-xl flex items-center justify-center text-white font-semibold text-sm shrink-0';
    $detailItem = 'py-3 border-b border-gray-100'; // Added for detail modal
    @endphp

    <style>
        :root { color-scheme: light; }
        select, option { color:#111827 !important; background:#ffffff !important; -webkit-text-fill-color:#111827 !important; }
        option:checked { background:#e5e7eb !important; color:#111827 !important; }
    </style>

    <main class="px-4 sm:px-6 py-6 space-y-6">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.bookings_approval_title') }}"
            subtitle="{{ __('app.bookings_approval_subtitle') }}">
            <x-slot:actions>
                {{-- MOBILE FILTER BUTTON --}}
                <button type="button"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-secondary text-secondary-foreground text-xs font-medium border border-border hover:bg-secondary/80 md:hidden transition"
                    @click="showFilterModal = true">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    <span>{{ __('app.filter') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- MAIN LAYOUT --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            {{-- LEFT: APPROVAL LIST --}}
            <section class="{{ $card }} md:col-span-3">
                {{-- Header + tabs + room scope --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('app.approval_queue') }}</h3>
                            <p class="text-xs text-gray-500">
                                {{ __('app.approval_queue_subtitle') }}
                            </p>
                        </div>

                        {{-- Tabs + View Mode Toggle --}}
                        <div class="flex items-center gap-3 self-start sm:self-auto">
                            {{-- Tabs --}}
                            <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium">
                                <button type="button"
                                    wire:click="setTab('pending')"
                                    class="px-3 py-1 rounded-full transition {{ $activeTab === 'pending' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                    {{ __('app.pending') }}
                                </button>
                                <button type="button"
                                    wire:click="setTab('ongoing')"
                                    class="px-3 py-1 rounded-full transition {{ $activeTab === 'ongoing' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                    {{ __('app.ongoing') }}
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

                    {{-- Room badge + Type scope --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs mt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if(!is_null($roomFilterId))
                                @php $activeRoom = collect($roomsOptions)->firstWhere('id', $roomFilterId); @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                    <x-heroicon-o-building-office class="w-3.5 h-3.5"/>
                                    <span>{{ __('app.room') }}: {{ $activeRoom['label'] ?? __('app.no_data') }}</span>
                                    <button type="button" class="ml-1 hover:text-white" wire:click="clearRoomFilter">&times;</button>
                                </span>
                            @endif
                        </div>

                        <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-[11px] font-medium">
                            <button type="button" wire:click="setTypeScope('all')"
                                class="px-3 py-1 rounded-full {{ $typeScope === 'all' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.all') }}
                            </button>
                            <button type="button" wire:click="setTypeScope('offline')"
                                class="px-3 py-1 rounded-full {{ $typeScope === 'offline' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.offline') }}
                            </button>
                            <button type="button" wire:click="setTypeScope('online')"
                                class="px-3 py-1 rounded-full {{ $typeScope === 'online' ? 'bg-[#4E653D] text-white shadow-sm' : 'text-gray-700' }}">
                                {{ __('app.online') }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Filter bar: Search + Date + Sort --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $label }}">{{ __('app.search') }}</label>
                            <div class="relative">
                                <input type="text" class="{{ $input }} pl-9"
                                    placeholder="{{ __('app.search') }}..."
                                    wire:model.debounce.500ms="q">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <div class="relative flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="date" class="{{ $input }} pl-9" wire:model.live="selectedDate">
                                    <x-heroicon-o-calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                                </div>
                                @if($selectedDate)
                                    <button type="button"
                                        wire:click="clearDate"
                                        title="Clear date filter"
                                        class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition">
                                        <x-heroicon-o-x-mark class="w-4 h-4"/>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.sort') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: $wire.entangle('dateMode').live,
                                    options: [
                                        { id: 'semua', label: '{{ __('app.sort_default') }}' },
                                        { id: 'terbaru', label: '{{ __('app.sort_newest') }}' },
                                        { id: 'terlama', label: '{{ __('app.sort_oldest') }}' }
                                    ],
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return this.options;
                                        return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const found = this.options.find(i => i.id == this.selectedId);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = 'semua';
                                    }
                                }"
                                x-init="
                                    search = selectedLabel;
                                    $watch('selectedId', val => {
                                        search = selectedLabel;
                                    });
                                "
                                class="relative"
                                @click.outside="open = false"
                            >
                                <div class="relative">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="{{ __('app.sort') }}" class="{{ $input }} pr-8">
                                    <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                        <button x-show="search" type="button" @click.stop="clear()" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="fill-current h-4 w-4 text-gray-400 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                    </div>
                                </div>
                                <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg text-sm" style="display:none">
                                    <template x-for="item in items" :key="item.id">
                                        <li @click="select(item.id, item.label)" :class="selectedId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-700 hover:bg-gray-100 cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.label"></li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-500" style="display:none">{{ __('app.no_data') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $list = $activeTab === 'pending' ? $pending : $ongoing;
                    $googleConnected = $googleConnected ?? false;
                    $zoomConfigured = $zoomConfigured ?? false;
                @endphp

                {{-- PENDING TAB (MODIFIED FOR IMAGE DESIGN) --}}
                @if($activeTab === 'pending')
                    @if($list->isEmpty())
                        <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                            {{ __('app.no_pending_filter') }}
                        </div>
                    @else
                        <div class="px-4 sm:px-6 py-5">
                            @if($viewMode === 'card')
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($list as $b)
                                        @php
                                            $isOnline   = in_array($b->booking_type, ['online_meeting','onlinemeeting']);
                                            $isRoomType = in_array($b->booking_type, ['bookingroom','meeting']);
                                            $avatarChar = strtoupper(substr($b->meeting_title ?? '—', 0, 1));

                                            $platform = $b->online_meeting_platform
                                                        ?? $b->platform
                                                        ?? $b->meeting_platform
                                                        ?? ($isOnline ? 'Online Meeting' : null);

                                            $meetingUrl      = $b->online_meeting_url ?? null;
                                            $meetingCode     = $b->online_meeting_code ?? null;
                                            $meetingPassword = $b->online_meeting_password ?? null;

                                            $provider = strtolower(str_replace([' ', '-'], '_', (string) $b->online_provider));
                                            $needsGoogleConnect = $isOnline && str_starts_with($provider, 'google') && !$googleConnected;
                                            $needsZoomConfig = $isOnline && !$needsGoogleConnect && !$zoomConfigured;

                                            $requesterName = $b->user?->name
                                                                ?? $b->requester_name
                                                                ?? null;

                                            $requesterDept = $b->user?->department?->department_name
                                                                ?? $b->user?->department?->dept_name
                                                                ?? $b->department_name
                                                                ?? null;
                                        @endphp

                                        {{-- START: MODIFIED CARD DESIGN TO MATCH IMAGE --}}
                                        <div wire:key="pending-{{ $b->bookingroom_id }}"
                                            class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">

                                            <div class="flex items-start gap-4">
                                                {{-- 1. Avatar/Initial on the left --}}
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $b->meeting_title ? $avatarChar : '?' }}</div>

                                                <div class="flex-1 min-w-0">
                                                    {{-- 2. TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $b->meeting_title ?? 'Untitled meeting' }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            {{-- Type (Offline/Online) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border flex-shrink-0 {{ $isOnline ? 'border-emerald-300 text-emerald-700 bg-emerald-50' : 'border-blue-300 text-blue-700 bg-blue-50' }}">
                                                                {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                                                            </span>
                                                            {{-- Status (Pending) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 flex-shrink-0">
                                                                {{ strtoupper($b->status) }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- 3. MIDDLE SECTION: Date, Time, Room --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        <div class="flex items-center gap-5">
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-calendar class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtDate($b->date) }}
                                                            </span>
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtTime($b->start_time) }} &ndash; {{ fmtTime($b->end_time) }}
                                                            </span>
                                                        </div>
                                                        @if($isRoomType)
                                                            {{-- Room/Location Chip (Matches image structure) --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5">
                                                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-gray-500"/>
                                                                <span class="font-medium {{ $b->room?->room_name ? 'text-gray-700' : 'text-rose-600' }}">
                                                                    {{ __('app.room') }}: {{ $b->room?->room_name ?? __('app.not_selected') }}
                                                                </span>
                                                            </span>
                                                        @elseif($isOnline && $platform)
                                                            {{-- Online Platform Chip --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700">
                                                                <x-heroicon-o-folder class="w-3.5 h-3.5 text-emerald-500"/>
                                                                <span class="font-medium">{{ $platform }}</span>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- 4. BOTTOM LEFT: Requester Info --}}
                                                    <div class="text-[12px] text-gray-600">
                                                        @if($requesterName)
                                                            <p class="mb-1">{{ __('app.req_by') }} <span class="font-medium text-gray-800">{{ $requesterName }}</span></p>
                                                        @endif
                                                        @if($requesterDept)
                                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] border border-gray-200">
                                                                {{ $requesterDept }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- 5. Created Timestamp (Placed here to be near Requester info) --}}
                                                    <div class="text-[10px] text-gray-500 mt-2">
                                                        {{ __('app.created') }}: {{ optional($b->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                                    </div>

                                                    {{-- Reject Note (if any) --}}
                                                    @if($b->book_reject)
                                                        <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-2">
                                                            <span class="font-medium">{{ __('app.notes') }}:</span> {{ $b->book_reject }}
                                                        </div>
                                                    @endif
                                                </div>

                                            </div>

                                            {{-- 6. NEW: BOTTOM ACTIONS (Horizontally aligned, matching the image) --}}
                                            @if($needsGoogleConnect || $needsZoomConfig)
                                                <div class="w-full text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-3">
                                                    {{ $needsGoogleConnect ? 'Google belum terhubung. Hubungkan akun Google terlebih dahulu sebelum menyetujui online meeting.' : 'Zoom belum dikonfigurasi. Hubungi admin untuk menyetel ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, dan ZOOM_CLIENT_SECRET.' }}
                                                </div>
                                            @endif
                                            <div class="pt-3 border-t border-gray-100 flex justify-end gap-3">
                                                                              {{-- DETAIL BUTTON (Using ghost style as in the image) --}}
                                                <button type="button"
                                                    wire:click="openDetailModal({{ $b->bookingroom_id }})"
                                                    class="{{ $btnGhost }} px-4 py-2">
                                                    {{ __('app.detail') }}
                                                </button>

                                                {{-- REJECT BUTTON (Red) - disabled if < 30min before meeting --}}
                                                @php $canReject = canRejectBooking($b); @endphp
                                                <button type="button"
                                                    wire:click="openReject({{ $b->bookingroom_id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openReject"
                                                    @if(!$canReject) disabled @endif
                                                    class="px-4 py-2 text-xs font-medium rounded-lg border inline-flex items-center justify-center transition
                                                        {{ $canReject
                                                            ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20'
                                                            : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' }}"
                                                    @if(!$canReject) title="Cannot reject: less than 30 minutes before meeting starts" @endif>
                                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5 inline-block mr-0.5"/>
                                                    {{ __('app.reject') }}
                                                </button>
                                                @if(!$canReject)
                                                    <span class="text-[10px] text-gray-400 italic">Less than 30 min before start</span>
                                                @endif
                                            </div>
                                        </div>
                                        {{-- END: MODIFIED CARD DESIGN TO MATCH IMAGE --}}
                                    @endforeach
                                </div>
                            @else
                                {{-- Pending Table Layout --}}
                                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                                <th class="px-6 py-3.5">#</th>
                                                <th class="px-6 py-3.5">{{ __('app.title_col') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.room_platform') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.requester') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($list as $b)
                                                @php
                                                    $isOnline   = in_array($b->booking_type, ['online_meeting','onlinemeeting']);
                                                    $isRoomType = in_array($b->booking_type, ['bookingroom','meeting']);
                                                    $platform = $b->online_meeting_platform
                                                                ?? $b->platform
                                                                ?? $b->meeting_platform
                                                                ?? ($isOnline ? 'Online Meeting' : null);
                                                    $provider = strtolower(str_replace([' ', '-'], '_', (string) $b->online_provider));
                                                    $needsGoogleConnect = $isOnline && str_starts_with($provider, 'google') && !$googleConnected;
                                                    $needsZoomConfig = $isOnline && !$needsGoogleConnect && !$zoomConfigured;
                                                    $requesterName = $b->user?->name
                                                                        ?? $b->requester_name
                                                                        ?? null;
                                                    $requesterDept = $b->user?->department?->department_name
                                                                        ?? $b->user?->department?->dept_name
                                                                        ?? $b->department_name
                                                                        ?? null;
                                                @endphp
                                                <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                    <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="font-semibold text-gray-900">{{ $b->meeting_title ?? 'Untitled meeting' }}</div>
                                                    </td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        @if($isOnline)
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold uppercase border border-emerald-200">
                                                                {{ $platform ?? 'ONLINE' }}
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold uppercase border border-blue-200">
                                                                {{ $b->room?->room_name ?? __('app.not_selected') }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-medium">{{ fmtDate($b->date) }}</td>
                                                    <td class="h-12 px-6 py-4 font-mono text-xs">{{ fmtTime($b->start_time) }} &ndash; {{ fmtTime($b->end_time) }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        @if($requesterName)
                                                            <div class="font-semibold text-gray-800">{{ $requesterName }}</div>
                                                        @endif
                                                        @if($requesterDept)
                                                            <div class="text-xs text-gray-500">{{ $requesterDept }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4">
                                                        <div class="flex flex-col md:items-end justify-end gap-2">
                                                            @if($needsGoogleConnect || $needsZoomConfig)
                                                                <div class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-2.5 py-2 max-w-sm">
                                                                    {{ $needsGoogleConnect ? 'Google belum terhubung. Hubungkan akun Google terlebih dahulu sebelum menyetujui online meeting.' : 'Zoom belum dikonfigurasi. Hubungi admin untuk menyetel ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, dan ZOOM_CLIENT_SECRET.' }}
                                                                </div>
                                                            @endif
                                                            <div class="flex items-center justify-end gap-2">
                                                                <button type="button"
                                                                    wire:click="openDetailModal({{ $b->bookingroom_id }})"
                                                                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition">
                                                                    {{ __('app.detail') }}
                                                                </button>
                                                                @php $canRejectTbl = canRejectBooking($b); @endphp
                                                                <button type="button"
                                                                    wire:click="openReject({{ $b->bookingroom_id }})"
                                                                    wire:loading.attr="disabled"
                                                                    @if(!$canRejectTbl) disabled @endif
                                                                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg border transition
                                                                        {{ $canRejectTbl
                                                                            ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 focus:outline-none'
                                                                            : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' }}"
                                                                    @if(!$canRejectTbl) title="Cannot reject: less than 30 minutes before meeting starts" @endif>
                                                                    {{ __('app.reject') }}
                                                                </button>
                                                            </div>
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
                @endif

                {{-- ONGOING TAB (Original code remains for ongoing tab) --}}
                @if($activeTab === 'ongoing')
                    @if($list->isEmpty())
                        <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                            {{ __('app.no_ongoing_filter') }}
                        </div>
                    @else
                        <div class="px-4 sm:px-6 py-5">
                            @if($viewMode === 'card')
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($list as $b)
                                        @php
                                            $isOnline   = in_array($b->booking_type, ['online_meeting','onlinemeeting']);
                                            $isRoomType = in_array($b->booking_type, ['bookingroom','meeting']);
                                            $avatarChar = strtoupper(substr($b->meeting_title ?? '—', 0, 1));

                                            $platform = $b->online_meeting_platform
                                                        ?? $b->platform
                                                        ?? $b->meeting_platform
                                                        ?? ($isOnline ? 'Online Meeting' : null);

                                            $meetingUrl      = $b->online_meeting_url ?? null;
                                            $meetingCode     = $b->online_meeting_code ?? null;
                                            $meetingPassword = $b->online_meeting_password ?? null;

                                            $requesterName = $b->user?->name
                                                                ?? $b->requester_name
                                                                ?? null;

                                            $requesterDept = $b->user?->department?->department_name
                                                                ?? $b->user?->department?->dept_name
                                                                ?? $b->department_name
                                                                ?? null;
                                        @endphp

                                        <div wire:key="ongoing-{{ $b->bookingroom_id }}"
                                            class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-sm hover:border-gray-300 transition">
                                            <div class="flex items-start gap-4">
                                                {{-- Avatar/Initial on the left --}}
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $b->meeting_title ? $avatarChar : '?' }}</div>

                                                <div class="flex-1 min-w-0">
                                                    {{-- TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $b->meeting_title ?? 'Untitled meeting' }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border flex-shrink-0 {{ $isOnline ? 'border-emerald-300 text-emerald-700 bg-emerald-50' : 'border-blue-300 text-blue-700 bg-blue-50' }}">
                                                                {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                                                            </span>
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-800 flex-shrink-0">
                                                                {{ strtoupper($b->status) }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- MIDDLE SECTION: Date, Time, Room --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        <div class="flex items-center gap-5">
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-calendar class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtDate($b->date) }}
                                                            </span>
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtTime($b->start_time) }} &ndash; {{ fmtTime($b->end_time) }}
                                                            </span>
                                                        </div>
                                                        @if($isRoomType)
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5">
                                                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-gray-500"/>
                                                                <span class="font-medium text-gray-700">
                                                                    {{ __('app.room') }}: {{ $b->room?->room_name ?? '—' }}
                                                                </span>
                                                            </span>
                                                        @elseif($isOnline && $platform)
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700">
                                                                <x-heroicon-o-folder class="w-3.5 h-3.5 text-emerald-500"/>
                                                                <span class="font-medium">{{ $platform }}</span>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- BOTTOM LEFT: Requester Info --}}
                                                    <div class="text-[12px] text-gray-600">
                                                        @if($requesterName)
                                                            <p class="mb-1">{{ __('app.requested_by') }} <span class="font-medium text-gray-800">{{ $requesterName }}</span></p>
                                                        @endif
                                                        @if($requesterDept)
                                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] border border-gray-200">
                                                                {{ $requesterDept }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Reject Note (if any) --}}
                                                    @if($b->book_reject)
                                                        <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-2">
                                                            <span class="font-medium">{{ __('app.notes') }}:</span> {{ $b->book_reject }}
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- RIGHT: Actions and Timestamp --}}
                                                <div class="text-right shrink-0 space-y-2 pt-0.5">
                                                    <div class="flex flex-col gap-2 justify-end">
                                                        {{-- DETAIL BUTTON --}}
                                                        <button type="button"
                                                            wire:click="openDetailModal({{ $b->bookingroom_id }})"
                                                            class="{{ $btnGhost }}">
                                                            <x-heroicon-o-eye class="w-3.5 h-3.5 inline-block mr-0.5"/>
                                                            {{ __('app.detail') }}
                                                        </button>
                                                    </div>

                                                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-lg bg-gray-50 text-gray-500 border border-gray-200">
                                                        {{ __('app.created') }}: {{ optional($b->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- Ongoing Table Layout --}}
                                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                                <th class="px-6 py-3.5">#</th>
                                                <th class="px-6 py-3.5">{{ __('app.title_col') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.room_platform') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.requester') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($list as $b)
                                                @php
                                                    $isOnline   = in_array($b->booking_type, ['online_meeting','onlinemeeting']);
                                                    $isRoomType = in_array($b->booking_type, ['bookingroom','meeting']);
                                                    $platform = $b->online_meeting_platform
                                                                ?? $b->platform
                                                                ?? $b->meeting_platform
                                                                ?? ($isOnline ? 'Online Meeting' : null);
                                                    $requesterName = $b->user?->name
                                                                        ?? $b->requester_name
                                                                        ?? null;
                                                    $requesterDept = $b->user?->department?->department_name
                                                                        ?? $b->user?->department?->dept_name
                                                                        ?? $b->department_name
                                                                        ?? null;
                                                @endphp
                                                <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                    <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="font-semibold text-gray-900">{{ $b->meeting_title ?? 'Untitled meeting' }}</div>
                                                    </td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        @if($isOnline)
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold uppercase border border-emerald-200">
                                                                {{ $platform ?? 'ONLINE' }}
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold uppercase border border-blue-200">
                                                                {{ $b->room?->room_name ?? '—' }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-medium">{{ fmtDate($b->date) }}</td>
                                                    <td class="h-12 px-6 py-4 font-mono text-xs">{{ fmtTime($b->start_time) }} &ndash; {{ fmtTime($b->end_time) }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        @if($requesterName)
                                                            <div class="font-semibold text-gray-800">{{ $requesterName }}</div>
                                                        @endif
                                                        @if($requesterDept)
                                                            <div class="text-xs text-gray-500">{{ $requesterDept }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button type="button"
                                                                wire:click="openDetailModal({{ $b->bookingroom_id }})"
                                                                class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition">
                                                                {{ __('app.detail') }}
                                                            </button>
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
                @endif

                {{-- PAGINATION --}}
                <div class="px-4 sm:px-6 py-5 bg-gray-50 border-top border-gray-200">
                    <div class="w-full">
                        @if($activeTab === 'pending')
                            {{ $pending->onEachSide(1)->links() }}
                        @else
                            {{ $ongoing->onEachSide(1)->links() }}
                        @endif
                    </div>
                </div>
            </section>

            {{-- RIGHT: SIDEBAR (Rooms) --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                <section class="{{ $card }}">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_room_label') }}</p>
                    </div>

                    <div class="p-4 space-y-4 bg-white">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ __('app.room') }}</label>
                            <div class="px-1 py-1 max-h-80 overflow-y-auto">
                                {{-- All rooms --}}
                                <button type="button"
                                        wire:click="clearRoomFilter"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors mb-1.5
                                            {{ is_null($roomFilterId) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">All</span>
                                        <span>{{ __('app.all_rooms') }}</span>
                                    </span>
                                </button>

                                {{-- Each room --}}
                                <div class="mt-2 space-y-1.5">
                                    @forelse($roomsOptions as $r)
                                        @php $active = !is_null($roomFilterId) && (int)$roomFilterId === (int)$r['id']; @endphp
                                        <button type="button"
                                                wire:click="selectRoom({{ $r['id'] }})"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                                    {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                            <span class="flex items-center gap-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                                    {{ substr($r['label'], 0, 2) }}
                                                </span>
                                                <span class="truncate font-medium">{{ $r['label'] }}</span>
                                            </span>
                                        </button>
                                    @empty
                                        <p class="text-xs text-gray-500">{{ __('app.no_room_data') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- REJECT MODAL (Alasan wajib) --}}
        @if($showRejectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
            role="dialog" aria-modal="true"
            wire:key="reject-modal"
            wire:keydown.escape.window="closeReject">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeReject"></div>

            <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden focus:outline-none transform transition-all duration-300 scale-100" tabindex="-1">
                <form wire:submit.prevent="confirmReject">
                    {{-- Modal Header --}}
                    <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                                <x-heroicon-o-x-circle class="w-4 h-4 text-[#CDDEA7]" />
                            </div>
                            <h3 class="font-bold tracking-tight text-base">{{ __('app.reject_booking_title') }}</h3>
                        </div>
                        <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeReject">✕</button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="p-6 space-y-4">
                        <p class="text-xs text-muted-foreground">{{ __('app.reject_reason_required') }}</p>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.reject_reason_ph') }} <span class="text-destructive">*</span></label>
                            <textarea wire:model.live="rejectReason"
                                rows="4"
                                class="w-full px-3.5 py-2.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                                placeholder="Contoh: Jadwal bentrok dengan rapat lain / Ruangan tidak tersedia"
                                required></textarea>
                            @error('rejectReason')
                            <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5">
                        <button type="button" class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5" wire:click="closeReject" wire:loading.attr="disabled" wire:target="confirmReject">
                            <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                            <span>{{ __('app.cancel') }}</span>
                        </button>
                        <button type="submit"
                            class="h-9 px-4 rounded-lg bg-destructive text-destructive-foreground text-xs font-semibold hover:bg-destructive/95 transition shadow-sm inline-flex items-center gap-1.5"
                            wire:loading.attr="disabled" wire:target="confirmReject">
                            <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                            <span>{{ __('app.reject') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- RESCHEDULE MODAL --}}
        @if($showRescheduleModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeReschedule"></div>

            <div class="relative bg-card border border-border shadow-2xl rounded-2xl w-full max-w-lg overflow-hidden transform transition-all duration-300 scale-100">
                <form wire:submit.prevent="submitReschedule">
                    <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                        <div>
                            <h3 class="font-bold tracking-tight text-base">Reschedule Booking</h3>
                            <p class="text-[11px] text-[#CDDEA7]/80 mt-0.5">
                                {{ __('app.reschedule_reason_required') }}
                            </p>
                        </div>
                        <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeReschedule">✕</button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.new_date') }}</label>
                            <input type="date" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="rescheduleDate" required>
                            @error('rescheduleDate') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.start_time_label') }}</label>
                                <input type="time" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="rescheduleStart" required>
                                @error('rescheduleStart') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.end_time_label') }}</label>
                                <input type="time" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="rescheduleEnd" required>
                                @error('rescheduleEnd') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex flex-col justify-end">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.room') }} ({{ __('app.optional') }})</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    roomId: $wire.entangle('rescheduleRoomId').live,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = @js(collect($roomsOptions)->values()->toArray());
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const list = @js(collect($roomsOptions)->values()->toArray());
                                        const found = list.find(i => i.id == this.roomId);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.roomId = id;
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.roomId = null;
                                    }
                                }"
                                x-init="
                                    search = selectedLabel;
                                    $watch('roomId', val => {
                                        search = selectedLabel;
                                    });
                                "
                                class="relative"
                                @click.outside="open = false"
                            >
                                <div class="relative">
                                    <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="{{ __('app.select_room') }}..." class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-8">
                                    <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                        <button x-show="search" type="button" @click.stop="clear()" class="text-muted-foreground hover:text-foreground">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                    </div>
                                </div>
                                <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm" style="display:none">
                                    <template x-for="item in items" :key="item.id">
                                        <li @click="select(item.id, item.label)" :class="roomId == item.id ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.label"></li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">{{ __('app.no_data') }}</p>
                            </div>
                            @error('rescheduleRoomId') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.reject_reason_ph') }} <span class="text-destructive">*</span></label>
                            <textarea rows="3" class="w-full px-3.5 py-2.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none" wire:model.live="rescheduleReason" required></textarea>
                            @error('rescheduleReason') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5">
                        <button type="button" class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition" wire:click="closeReschedule" wire:loading.attr="disabled" wire:target="submitReschedule">
                            {{ __('app.cancel') }}
                        </button>
                        <button type="submit" class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm" wire:loading.attr="disabled" wire:target="submitReschedule">
                            {{ __('app.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- MOBILE FILTER MODAL --}}
    <div x-show="showFilterModal" class="fixed inset-0 z-40 md:hidden flex items-end" x-cloak style="display: none;">
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
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900">Filter & Recent</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_room_recent') }}</p>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" @click="showFilterModal = false">✕</button>
            </div>

            <div class="p-5 space-y-4 overflow-y-auto flex-1 bg-white">
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-700 mb-2">{{ __('app.filter_by_room_label') }}</h4>

                    <button type="button"
                        wire:click="clearRoomFilter"
                        @click="showFilterModal = false"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors
                            {{ is_null($roomFilterId) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                All
                            </span>
                            <span>{{ __('app.all') }}</span>
                        </span>
                    </button>

                    <div class="mt-2 space-y-1.5">
                        @forelse($roomsOptions as $r)
                            @php
                                $active = !is_null($roomFilterId) && (int) $roomFilterId === (int) $r['id'];
                            @endphp
                            <button type="button"
                                wire:click="selectRoom({{ $r['id'] }})"
                                @click="showFilterModal = false"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                    {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                        {{ substr($r['label'], 0, 2) }}
                                    </span>
                                    <span class="truncate font-medium">{{ $r['label'] }}</span>
                                </span>
                            </button>
                        @empty
                            <p class="text-xs text-gray-500">{{ __('app.no_room_data') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-200 bg-gray-50">
                <button type="button"
                    class="w-full h-10 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition-colors shadow-sm"
                    @click="showFilterModal = false">
                    {{ __('app.apply_close') }}
                </button>
            </div>
        </div>
    </div>

        {{-- BOOKING DETAIL MODAL --}}
        @if ($showDetailModal && $selectedBookingDetail)
        <div
            class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4"
            role="dialog" aria-modal="true"
            wire:key="detail-modal-{{ $selectedBookingDetail->bookingroom_id }}"
            wire:keydown.escape.window="closeDetailModal">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeDetailModal"></div>

            <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden focus:outline-none transform transition-all duration-300 scale-100 flex flex-col max-h-[85vh]" tabindex="-1">

                {{-- Modal Header --}}
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <x-heroicon-o-eye class="w-4 h-4 text-[#CDDEA7]" />
                        </div>
                        <h3 class="font-bold tracking-tight text-base">{{ __('app.detail_booking') }}</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="closeDetailModal">✕</button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-4 overflow-y-auto flex-1">
                    @php
                        $detail = $selectedBookingDetail;
                        $isOnline = in_array($detail->booking_type, ['online_meeting', 'onlinemeeting']);

                        $statusClass = [
                            'approved'  => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                            'pending'   => 'bg-amber-500/10 text-amber-600 border-amber-500/20',
                            'rejected'  => 'bg-rose-500/10 text-rose-600 border-rose-500/20',
                            'completed' => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
                            'cancelled' => 'bg-gray-500/10 text-gray-600 border-gray-500/20',
                        ];
                        $mono = 'text-[10px] font-semibold text-muted-foreground/60 bg-muted/50 border border-border/40 px-2 py-0.5 rounded font-mono uppercase tracking-wider';

                        // Requester: prefer user relation, fall back to stored name fields
                        $requesterName = $detail->user?->full_name
                            ?? $detail->user?->name
                            ?? '—';

                        // Department: prefer the direct department relation, fall back through user's department
                        $departmentName = $detail->department?->department_name
                            ?? $detail->user?->department?->department_name
                            ?? '—';

                        // Booking type human label
                        $bookingTypeLabel = match (strtolower((string) $detail->booking_type)) {
                            'online_meeting', 'onlinemeeting' => 'Online Meeting',
                            'meeting'                         => 'Offline Meeting',
                            'hybrid'                          => 'Hybrid',
                            default                           => ucfirst(str_replace('_', ' ', $detail->booking_type ?? 'Meeting')),
                        };

                        // Requirements: load from pivot relation (already eager-loaded)
                        $requirementsToDisplay = $detail->requirements->isNotEmpty()
                            ? $detail->requirements->pluck('name')->filter()->values()->toArray()
                            : [];

                        // Clean special notes — just show the raw value, no fake-bug detection
                        $specialNotes = trim((string) ($detail->special_notes ?? ''));

                        // "Info dept request" flag
                        $infoRequested = $detail->requestinformation === 'request';
                    @endphp

                    {{-- Title, Status and Type --}}
                    <div class="pb-3 border-b border-border">
                        <h4 class="text-base font-bold text-foreground mb-2 leading-tight">
                            {{ $detail->meeting_title ?? 'Untitled Meeting' }}
                        </h4>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $statusClass[strtolower($detail->status ?? 'cancelled')] ?? 'bg-muted text-muted-foreground border-border' }}">
                                {{ ucfirst(strtolower($detail->status ?? 'unknown')) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                {{ $isOnline ? 'Online' : 'Offline' }}
                            </span>
                            @if ($infoRequested)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-violet-50 text-violet-700 border border-violet-200">
                                <x-heroicon-o-bell-alert class="w-3 h-3" />
                                Info Requested
                            </span>
                            @endif
                            <span class="{{ $mono }}">ID: {{ $detail->bookingroom_id }}</span>
                        </div>
                    </div>

                    <div class="space-y-4">

                        {{-- Requester & Department --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <x-heroicon-o-user class="w-3.5 h-3.5 text-muted-foreground/60" />
                                    <span>{{ __('app.requester') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-foreground">{{ $requesterName }}</p>
                            </div>
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <x-heroicon-o-building-office class="w-3.5 h-3.5 text-muted-foreground/60" />
                                    <span>{{ __('app.department') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-foreground">{{ $departmentName }}</p>
                            </div>
                        </div>

                        {{-- Date & Time --}}
                        <div class="space-y-1 border-t border-border/40 pt-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-calendar class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.booking_time_label') }}</span>
                            </div>
                            <p class="text-sm font-semibold text-foreground">
                                {{ \Illuminate\Support\Carbon::parse($detail->date)->format('d M Y') }}
                                <span class="text-muted-foreground/40 mx-1.5">/</span>
                                {{ \Illuminate\Support\Carbon::parse($detail->start_time)->format('H:i') }} &ndash; {{ \Illuminate\Support\Carbon::parse($detail->end_time)->format('H:i') }}
                            </p>
                        </div>

                        {{-- Attendees + Room/Provider --}}
                        <div class="grid grid-cols-2 gap-4 border-t border-border/40 pt-3">
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <x-heroicon-o-user-group class="w-3.5 h-3.5 text-muted-foreground/60" />
                                    <span>{{ __('app.attendees_count') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-foreground">
                                    {{ $detail->number_of_attendees > 0 ? $detail->number_of_attendees : '—' }}
                                </p>
                            </div>
                            @if (!$isOnline)
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <x-heroicon-o-building-office-2 class="w-3.5 h-3.5 text-muted-foreground/60" />
                                    <span>{{ __('app.meeting_room_label') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-foreground">{{ $detail->room->room_name ?? '—' }}</p>
                            </div>
                            @else
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <x-heroicon-o-swatch class="w-3.5 h-3.5 text-muted-foreground/60" />
                                    <span>{{ __('app.online_provider_label') }}</span>
                                </div>
                                <p class="text-sm font-semibold text-foreground capitalize">
                                    {{ str_replace('_', ' ', $detail->online_provider ?? '—') }}
                                </p>
                            </div>
                            @endif
                        </div>

                        {{-- Requirements --}}
                        @if (!empty($requirementsToDisplay))
                        <div class="p-3 bg-muted/20 border border-border/60 rounded-xl space-y-2 border-t border-border/40 pt-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-check-badge class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.requirements_list') }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($requirementsToDisplay as $reqName)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-background text-foreground border border-border">
                                    {{ $reqName }}
                                </span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Online Specific Details --}}
                        @if ($isOnline)
                        <div class="grid grid-cols-2 gap-4 border-t border-border/40 pt-3">
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.meeting_code_label') }}</div>
                                <p class="text-xs font-semibold text-foreground font-mono bg-muted px-2 py-1 rounded border border-border/40 w-fit">
                                    {{ $detail->online_meeting_code ?: '—' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('app.password') }}</div>
                                <p class="text-xs font-semibold text-foreground font-mono bg-muted px-2 py-1 rounded border border-border/40 w-fit">
                                    {{ $detail->online_meeting_password ?: '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1 border-t border-border/40 pt-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-link class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>Meeting URL</span>
                            </div>
                            @if ($detail->online_meeting_url)
                            <a href="{{ $detail->online_meeting_url }}" target="_blank"
                                class="text-xs font-medium text-primary hover:underline break-all block bg-primary/5 border border-primary/20 p-2.5 rounded-xl">
                                {{ $detail->online_meeting_url }}
                            </a>
                            @else
                            <p class="text-xs text-muted-foreground">—</p>
                            @endif
                        </div>
                        @endif

                        {{-- Reject / Reschedule Note --}}
                        @if ($detail->book_reject)
                        <div class="p-3 bg-amber-500/5 border border-amber-500/20 rounded-xl space-y-1 border-t border-border/40 pt-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 flex items-center gap-1.5">
                                <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                                <span>{{ __('app.reject_reason') }}</span>
                            </div>
                            <p class="text-xs text-amber-800 leading-relaxed whitespace-pre-wrap">{{ $detail->book_reject }}</p>
                        </div>
                        @endif

                        {{-- Special Notes --}}
                        <div class="space-y-1 border-t border-border/40 pt-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5 text-muted-foreground/60" />
                                <span>{{ __('app.special_notes_label') }}</span>
                            </div>
                            <p class="text-xs text-foreground/80 leading-relaxed whitespace-pre-wrap">
                                {{ $specialNotes !== '' ? $specialNotes : '—' }}
                            </p>
                        </div>

                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="border-t border-border px-6 py-4 flex justify-end bg-muted/10">
                    <button wire:click="closeDetailModal" type="button"
                        class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                        <span>{{ __('app.close') }}</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    </main>
</div>
