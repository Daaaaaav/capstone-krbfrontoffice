<div class="min-h-screen bg-background" wire:poll.1000ms.keep-alive x-data="{ showFilterModal: false }">
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
            try { return $v ? Carbon::parse($v)->format('H.i') : '—'; } // 10.00
            catch (\Throwable) {
                if (is_string($v)) {
                    if (preg_match('/^\d{2}:\d{2}/', $v)) return str_replace(':','.', substr($v,0,5));
                    if (preg_match('/^\d{2}\.\d{2}/', $v)) return substr($v,0,5);
                }
                return '—';
            }
        }
    }

    /** @var int|null $roomFilterId */
    $roomFilterId = $roomFilterId ?? null;

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

    <main class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.booking_history_title') }}"
            subtitle="{{ __('app.booking_history_subtitle') }}">
            <x-slot:actions>
                {{-- Show deleted toggle --}}
                <button type="button" wire:click="$toggle('withTrashed')" class="flex items-center gap-2 group focus:outline-none">
                    <div class="relative flex items-center">
                        <div class="w-9 h-5 rounded-full transition-colors {{ $withTrashed ? 'bg-primary' : 'bg-border' }}"></div>
                        <div class="absolute left-[3px] w-3.5 h-3.5 rounded-full bg-white shadow transition-transform {{ $withTrashed ? 'translate-x-4' : '' }}"></div>
                    </div>
                    <span class="text-sm font-medium transition-colors" style="color:#CDDEA7 !important">{{ __('app.show_deleted') }}</span>
                </button>
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
            {{-- LEFT: HISTORY LIST CARD --}}
            <section class="{{ $card }} md:col-span-3">
                {{-- Header: title + tabs + room badge + type scope --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ __('app.history') }}</h3>
                            <p class="text-xs text-gray-500">
                                {{ __('app.history_subtitle') }}
                            </p>
                        </div>

                        {{-- Tabs + View Mode Toggle --}}
                        <div class="flex items-center gap-3 self-start sm:self-auto">
                            {{-- Tabs --}}
                            <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-xs font-medium">
                                <button type="button"
                                        wire:click="setTab('done')"
                                        class="px-3 py-1 rounded-full transition
                                            {{ $activeTab === 'done'
                                                ? 'bg-[#4E653D] text-white shadow-sm'
                                                : 'text-gray-700 hover:bg-gray-200' }}">
                                    {{ __('app.done') }}
                                </button>
                                <button type="button"
                                        wire:click="setTab('rejected')"
                                        class="px-3 py-1 rounded-full transition
                                            {{ $activeTab === 'rejected'
                                                ? 'bg-[#4E653D] text-white shadow-sm'
                                                : 'text-gray-700 hover:bg-gray-200' }}">
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

                    {{-- Room badge + type scope --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs mt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if(!is_null($roomFilterId))
                                @php $activeRoom = collect($roomsOptions)->firstWhere('id', $roomFilterId); @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] border border-[#4A2F24]/30">
                                    <x-heroicon-o-building-office class="w-3.5 h-3.5"/>
                                    <span>{{ __('app.room') }}: {{ $activeRoom['label'] ?? __('app.no_data') }}</span>
                                    <button type="button" class="ml-1 hover:text-white" wire:click="clearRoomFilter">×</button>
                                </span>
                            @endif
                        </div>

                        <div class="inline-flex items-center bg-gray-100 rounded-full p-1 text-[11px] font-medium">
                            <button type="button"
                                    wire:click="setTypeScope('all')"
                                    class="px-3 py-1 rounded-full transition
                                        {{ $typeScope === 'all'
                                            ? 'bg-[#4E653D] text-white shadow-sm'
                                            : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.all') }}
                            </button>
                            <button type="button"
                                    wire:click="setTypeScope('offline')"
                                    class="px-3 py-1 rounded-full transition
                                        {{ $typeScope === 'offline'
                                            ? 'bg-[#4E653D] text-white shadow-sm'
                                            : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.offline') }}
                            </button>
                            <button type="button"
                                    wire:click="setTypeScope('online')"
                                    class="px-3 py-1 rounded-full transition
                                        {{ $typeScope === 'online'
                                            ? 'bg-[#4E653D] text-white shadow-sm'
                                            : 'text-gray-700 hover:bg-gray-200' }}">
                                {{ __('app.online') }}
                            </button>
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
                                       placeholder="{{ __('app.search_title_ph') }}"
                                       wire:model.live="q">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <div class="relative">
                                <input type="date"
                                       class="{{ $input }} pl-9"
                                       wire:model.live="selectedDate">
                                <x-heroicon-o-calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"/>
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
                             {{-- LIST AREA (grid cards, same style as Approval) --}}

                {{-- ── MANAGER PRIORITY ROOM BOOKINGS SECTION ── --}}
                @php
                    $priorityHistoryList = $activeTab === 'done' ? $priorityRoomHistory : $priorityRoomRejected;
                @endphp
                @if($priorityHistoryList->isNotEmpty())
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-amber-200 bg-amber-50/40">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700">
                            Manager Priority Bookings — {{ $activeTab === 'done' ? 'Approved' : 'Rejected/Denied' }}
                        </span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white">{{ $priorityHistoryList->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($priorityHistoryList as $pb)
                        @php
                            $pbBadge = $activeTab === 'done'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-rose-100 text-rose-700';
                        @endphp
                        <div wire:key="priority-hist-{{ $pb->id }}" class="bg-white border border-amber-200 rounded-xl p-4 flex items-start gap-3 shadow-sm">
                            <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/></svg>
                            </div>
                            <div class="flex-1 min-w-0 space-y-0.5">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $pb->meeting_title }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $pb->room?->room_name ?? '—' }} &bull;
                                    {{ \Carbon\Carbon::parse($pb->date)->format('d M Y') }} &bull;
                                    {{ $pb->start_time }} – {{ $pb->end_time }}
                                </p>
                                <p class="text-[11px] text-amber-600 font-medium">By: {{ $pb->manager?->full_name ?? '—' }}</p>
                                @if($pb->rejection_reason)
                                    <p class="text-[11px] text-rose-500 italic">{{ $pb->rejection_reason }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full {{ $pbBadge }}">
                                {{ $pb->statusLabel() }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- DONE TAB --}}
                @if($activeTab === 'done')
                    @if($doneRows->isEmpty())
                        <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                            {{ __('app.no_data') }}
                        </div>
                    @else
                        <div class="px-4 sm:px-6 py-5">
                            @if($viewMode === 'card')
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($doneRows as $row)
                                        @php
                                            $isOnline   = in_array($row->booking_type, ['onlinemeeting','online_meeting']);
                                            $isRoomType = in_array($row->booking_type, ['bookingroom','meeting']);
                                            $stateKey   = $row->deleted_at ? 'trash' : 'ok';
                                            $avatarChar = strtoupper(substr($row->meeting_title ?? '—', 0, 1));

                                            $platform = $row->online_meeting_platform
                                                        ?? $row->platform
                                                        ?? $row->meeting_platform
                                                        ?? $row->online_provider
                                                        ?? ($isOnline ? 'Online Meeting' : null);

                                            $meetingUrl      = $row->online_meeting_url ?? null;
                                            $meetingCode     = $row->online_meeting_code ?? null;
                                            $meetingPassword = $row->online_meeting_password ?? null;

                                            $requesterName = $row->user?->name
                                                            ?? $row->requester_name
                                                            ?? null;

                                            $requesterDept = $row->user?->department?->department_name
                                                            ?? $row->user?->department?->dept_name
                                                            ?? $row->department_name
                                                            ?? null;
                                        @endphp

                                        {{-- START: MODIFIED HISTORY CARD DESIGN (DONE) --}}
                                        <div wire:key="done-{{ $row->bookingroom_id }}-{{ $stateKey }}"
                                             class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                            
                                            <div class="flex items-start gap-4">
                                                {{-- 1. Avatar/Initial on the left --}}
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    {{-- 2. TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $row->meeting_title ?? '—' }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            {{-- Type (Offline/Online) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border flex-shrink-0 {{ $isOnline ? 'border-emerald-300 text-emerald-700 bg-emerald-50' : 'border-blue-300 text-blue-700 bg-blue-50' }}">
                                                                {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                                                            </span>
                                                            {{-- Status (Done) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-800 flex-shrink-0">
                                                                {{ strtoupper(__('app.done')) }}
                                                            </span>
                                                            @if($row->deleted_at)
                                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 flex-shrink-0">
                                                                    {{ strtoupper(__('app.deleted')) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- 3. MIDDLE SECTION: Date, Time, Room --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        <div class="flex items-center gap-5">
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-calendar class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtDate($row->date) }}
                                                            </span>
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtTime($row->start_time) }}–{{ fmtTime($row->end_time) }}
                                                            </span>
                                                        </div>
                                                        @if($isRoomType)
                                                            {{-- Room/Location Chip --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5">
                                                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-gray-500"/>
                                                                <span class="font-medium text-gray-700">
                                                                    {{ __('app.room') }}: {{ optional($row->room)->room_name ?? '—' }}
                                                                </span>
                                                            </span>
                                                        @elseif($isOnline && $platform)
                                                            {{-- Online Platform Chip --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700">
                                                                <x-heroicon-o-folder class="w-3.5 h-3.5 text-emerald-500"/>
                                                                <span class="font-medium">{{ $platform }}</span>
                                                            </span>
                                                        @endif
                                                        
                                                        {{-- Online link/code (Combined into one section) --}}
                                                        @if($isOnline && ($meetingUrl || $meetingCode || $meetingPassword))
                                                            <div class="flex flex-wrap items-center gap-2 mt-2 pt-1 text-[11px] border-t border-dashed border-gray-100">
                                                                @if($meetingUrl)
                                                                    <a href="{{ $meetingUrl }}" target="_blank"
                                                                       class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] hover:bg-[#352018] shadow-sm">
                                                                        <x-heroicon-o-link class="w-3.5 h-3.5"/>
                                                                        {{ __('app.join_link') }}
                                                                    </a>
                                                                @endif
                                                                @if($meetingCode)
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                                                        {{ __('app.meeting_code_label') }}:
                                                                        <span class="font-mono">{{ $meetingCode }}</span>
                                                                    </span>
                                                                @endif
                                                                @if($meetingPassword)
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                                                        {{ __('app.password') }}:
                                                                        <span class="font-mono">{{ $meetingPassword }}</span>
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- 4. BOTTOM LEFT: Requester Info & Notes --}}
                                                    <div class="text-[12px] text-gray-600 space-y-2">
                                                        @if($requesterName)
                                                            <p>{{ __('app.requested_by') }} <span class="font-medium text-gray-800">{{ $requesterName }}</span></p>
                                                        @endif
                                                        <div class="flex flex-col gap-1 mt-2">
                                                            @if($requesterDept)
                                                                <div>
                                                                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] border border-gray-200 inline-block">
                                                                        {{ $requesterDept }}
                                                                    </span>
                                                                </div>
                                                            @endif
                                                            <div class="flex flex-col gap-1 min-w-0 pt-1 border-t border-gray-200/50 mt-1">
                                                                @if($row->created_at)
                                                                    <div class="flex items-center gap-1.5">
                                                                        <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                                        <span class="truncate font-medium text-[11px] text-gray-600">Created: <span class="text-gray-900 font-semibold">{{ optional($row->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                                    </div>
                                                                @endif
                                                                @if($row->updated_at)
                                                                    <div class="flex items-center gap-1.5">
                                                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                                        <span class="truncate font-medium text-[11px] text-gray-600">Last Edited: <span class="text-gray-900 font-semibold">{{ optional($row->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @if($row->special_notes)
                                                            <div class="mt-2 text-xs text-gray-600 bg-gray-50 border border-gray-100 rounded-lg p-2">
                                                                <span class="font-medium">{{ __('app.notes') }}:</span> {{ $row->special_notes }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            {{-- 5. BOTTOM ACTIONS --}}
                                            <div class="pt-3 border-t border-gray-100 flex justify-end gap-3 items-center">
                                                <span class="text-[11px] text-gray-500 mr-auto">No. {{ $doneRows->firstItem() + $loop->index }}</span>
                                                                          {{-- EDIT BUTTON --}}
                                                <button type="button"
                                                        wire:click="edit({{ $row->bookingroom_id }})"
                                                        wire:loading.attr="disabled"
                                                        class="{{ $btnBlk }} px-4 py-2">
                                                    {{ __('app.edit') }}
                                                </button>

                                                @if(!$row->deleted_at)
                                                    {{-- DELETE BUTTON --}}
                                                    <button type="button"
                                                            wire:click="confirmDelete({{ $row->bookingroom_id }}, '{{ str_replace('\'', '', $row->meeting_title ?? '') }}', false)"
                                                            wire:loading.attr="disabled"
                                                            class="px-4 py-2 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 disabled:opacity-60 transition">
                                                        {{ __('app.delete') }}
                                                    </button>
                                                @else
                                                    {{-- RESTORE BUTTON --}}
                                                    <button type="button"
                                                            wire:click="restore({{ $row->bookingroom_id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="restore"
                                                            class="px-4 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm">
                                                        {{ __('app.restore') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        {{-- END: MODIFIED HISTORY CARD DESIGN (DONE) --}}
                                    @endforeach
                                </div>
                            @else
                                {{-- Done Table Layout --}}
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
                                                <th class="px-6 py-3.5">Created Time</th>
                                                <th class="px-6 py-3.5">Last Edited</th>
                                                <th class="px-6 py-3.5">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($doneRows as $row)
                                                @php
                                                    $isOnline   = in_array($row->booking_type, ['onlinemeeting','online_meeting']);
                                                    $isRoomType = in_array($row->booking_type, ['bookingroom','meeting']);
                                                    $platform = $row->online_meeting_platform
                                                                ?? $row->platform
                                                                ?? $row->meeting_platform
                                                                ?? $row->online_provider
                                                                ?? ($isOnline ? 'Online Meeting' : null);
                                                    $requesterName = $row->user?->name
                                                                    ?? $row->requester_name
                                                                    ?? null;
                                                    $requesterDept = $row->user?->department?->department_name
                                                                    ?? $row->user?->department?->dept_name
                                                                    ?? $row->department_name
                                                                    ?? null;
                                                @endphp
                                                <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                    <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="font-semibold text-gray-900">{{ $row->meeting_title ?? '—' }}</div>
                                                        @if($row->deleted_at)
                                                            <span class="inline-flex items-center text-[10px] text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded font-medium mt-1">{{ __('app.deleted') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="flex justify-end">
                                                            @if($isOnline)
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold uppercase border border-emerald-200">
                                                                    {{ $platform ?? 'ONLINE' }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold uppercase border border-blue-200">
                                                                    {{ $row->room?->room_name ?? 'OFFLINE' }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-medium">{{ fmtDate($row->date) }}</td>
                                                    <td class="h-12 px-6 py-4 font-mono text-xs">{{ fmtTime($row->start_time) }}–{{ fmtTime($row->end_time) }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        @if($requesterName)
                                                            <div class="font-semibold text-gray-800">{{ $requesterName }}</div>
                                                        @endif
                                                        @if($requesterDept)
                                                            <div class="text-xs text-gray-500">{{ $requesterDept }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-mono text-[11px] text-gray-500">
                                                        @if($row->created_at) {{ fmtDate($row->created_at) }} {{ fmtTime($row->created_at) }} @else — @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-mono text-[11px] text-gray-500">
                                                        @if($row->updated_at) {{ fmtDate($row->updated_at) }} {{ fmtTime($row->updated_at) }} @else — @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button type="button"
                                                                    wire:click="edit({{ $row->bookingroom_id }})"
                                                                    wire:loading.attr="disabled"
                                                                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition">
                                                                {{ __('app.edit') }}
                                                            </button>
                                                            @if(!$row->deleted_at)
                                                                <button type="button"
                                                                        wire:click="confirmDelete({{ $row->bookingroom_id }}, '{{ str_replace('\'', '', $row->meeting_title ?? '') }}', false)"
                                                                        wire:loading.attr="disabled"
                                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none transition">
                                                                    {{ __('app.delete') }}
                                                                </button>
                                                            @else
                                                                <button type="button"
                                                                        wire:click="restore({{ $row->bookingroom_id }})"
                                                                        wire:loading.attr="disabled"
                                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition">
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
                @endif

                {{-- REJECTED TAB --}}
                @if($activeTab === 'rejected')
                    @if($rejectedRows->isEmpty())
                        <div class="px-4 sm:px-6 py-14 text-center text-gray-500 text-sm">
                            {{ __('app.no_data') }}
                        </div>
                    @else
                        <div class="px-4 sm:px-6 py-5">
                            @if($viewMode === 'card')
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($rejectedRows as $row)
                                        @php
                                            $isOnline   = in_array($row->booking_type, ['onlinemeeting','online_meeting']);
                                            $isRoomType = in_array($row->booking_type, ['bookingroom','meeting']);
                                            $stateKey   = $row->deleted_at ? 'trash' : 'ok';
                                            $avatarChar = strtoupper(substr($row->meeting_title ?? '—', 0, 1));

                                            $platform = $row->online_meeting_platform
                                                        ?? $row->platform
                                                        ?? $row->meeting_platform
                                                        ?? $row->online_provider
                                                        ?? ($isOnline ? 'Online Meeting' : null);

                                            $meetingUrl      = $row->online_meeting_url ?? null;
                                            $meetingCode     = $row->online_meeting_code ?? null;
                                            $meetingPassword = $row->online_meeting_password ?? null;

                                            $requesterName = $row->user?->name
                                                            ?? $row->requester_name
                                                            ?? null;

                                            $requesterDept = $row->user?->department?->department_name
                                                            ?? $row->user?->department?->dept_name
                                                            ?? $row->department_name
                                                            ?? null;
                                        @endphp

                                        {{-- START: MODIFIED HISTORY CARD DESIGN (REJECTED) --}}
                                        <div wire:key="rej-{{ $row->bookingroom_id }}-{{ $stateKey }}"
                                             class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 flex flex-col h-full justify-between hover:shadow-sm hover:border-gray-300 transition">
                                            
                                            <div class="flex items-start gap-4">
                                                {{-- 1. Avatar/Initial on the left --}}
                                                <div class="{{ $icoAvatar }} mt-0.5">{{ $avatarChar }}</div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    {{-- 2. TOP ROW: Title, Type, Status --}}
                                                    <div class="flex items-center justify-between gap-3 min-w-0 mb-2">
                                                        <h4 class="font-semibold text-gray-900 text-base truncate pr-2">
                                                            {{ $row->meeting_title ?? '—' }}
                                                        </h4>
                                                        <div class="flex-shrink-0 flex items-center gap-2">
                                                            {{-- Type (Offline/Online) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full border flex-shrink-0 {{ $isOnline ? 'border-emerald-300 text-emerald-700 bg-emerald-50' : 'border-blue-300 text-blue-700 bg-blue-50' }}">
                                                                {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                                                            </span>
                                            {{-- Status (Rejected) --}}
                                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-800 flex-shrink-0">
                                                                {{ strtoupper(__('app.rejected')) }}
                                                            </span>
                                                            @if($row->deleted_at)
                                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 flex-shrink-0">
                                                                    {{ strtoupper(__('app.deleted')) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- 3. MIDDLE SECTION: Date, Time, Room --}}
                                                    <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                        <div class="flex items-center gap-5">
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-calendar class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtDate($row->date) }}
                                                            </span>
                                                            <span class="flex items-center gap-1.5 font-medium text-gray-800">
                                                                <x-heroicon-o-clock class="w-4 h-4 text-gray-500"/>
                                                                {{ fmtTime($row->start_time) }}–{{ fmtTime($row->end_time) }}
                                                            </span>
                                                        </div>
                                                        @if($isRoomType)
                                                            {{-- Room/Location Chip --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5">
                                                                <x-heroicon-o-building-office class="w-3.5 h-3.5 text-gray-500"/>
                                                                <span class="font-medium text-gray-700">
                                                                    {{ __('app.room') }}: {{ optional($row->room)->room_name ?? '—' }}
                                                                </span>
                                                            </span>
                                                        @elseif($isOnline && $platform)
                                                            {{-- Online Platform Chip --}}
                                                            <span class="{{ $chip }} text-xs px-2.5 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700">
                                                                <x-heroicon-o-folder class="w-3.5 h-3.5 text-emerald-500"/>
                                                                <span class="font-medium">{{ $platform }}</span>
                                                            </span>
                                                        @endif
                                                        
                                                        {{-- Online link/code (Combined into one section) --}}
                                                        @if($isOnline && ($meetingUrl || $meetingCode || $meetingPassword))
                                                            <div class="flex flex-wrap items-center gap-2 mt-2 pt-1 text-[11px] border-t border-dashed border-gray-100">
                                                                @if($meetingUrl)
                                                                    <a href="{{ $meetingUrl }}" target="_blank"
                                                                       class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#4A2F24] text-[#CDDEA7] hover:bg-[#352018] shadow-sm">
                                                                        <x-heroicon-o-link class="w-3.5 h-3.5"/>
                                                                        {{ __('app.join_link') }}
                                                                    </a>
                                                                @endif
                                                                @if($meetingCode)
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                                                        {{ __('app.meeting_code_label') }}:
                                                                        <span class="font-mono">{{ $meetingCode }}</span>
                                                                    </span>
                                                                @endif
                                                                @if($meetingPassword)
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                                                        {{ __('app.password') }}:
                                                                        <span class="font-mono">{{ $meetingPassword }}</span>
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- 4. BOTTOM LEFT: Requester Info & Reject Reason --}}
                                                    <div class="text-[12px] text-gray-600 space-y-2">
                                                        @if($requesterName)
                                                            <p>{{ __('app.requested_by') }} <span class="font-medium text-gray-800">{{ $requesterName }}</span></p>
                                                        @endif
                                                        <div class="flex flex-col gap-1 mt-2">
                                                            @if($requesterDept)
                                                                <div>
                                                                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] border border-gray-200 inline-block">
                                                                        {{ $requesterDept }}
                                                                    </span>
                                                                </div>
                                                            @endif
                                                            <div class="flex flex-col gap-1 min-w-0 pt-1 border-t border-gray-200/50 mt-1">
                                                                @if($row->created_at)
                                                                    <div class="flex items-center gap-1.5">
                                                                        <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                                        <span class="truncate font-medium text-[11px] text-gray-600">Created: <span class="text-gray-900 font-semibold">{{ optional($row->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                                    </div>
                                                                @endif
                                                                @if($row->updated_at)
                                                                    <div class="flex items-center gap-1.5">
                                                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                                        <span class="truncate font-medium text-[11px] text-gray-600">Last Edited: <span class="text-gray-900 font-semibold">{{ optional($row->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @if($row->book_reject)
                                                            <div class="mt-2 text-xs text-rose-700 bg-rose-50 border border-rose-100 rounded-lg p-2">
                                                                <span class="font-medium">{{ __('app.rejection_reason_label') }}:</span> {{ $row->book_reject }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            {{-- 5. BOTTOM ACTIONS --}}
                                            <div class="pt-3 border-t border-gray-100 flex justify-end gap-3 items-center">
                                                <span class="text-[11px] text-gray-500 mr-auto">No. {{ $rejectedRows->firstItem() + $loop->index }}</span>
                                                {{-- EDIT BUTTON --}}
                                                <button type="button"
                                                        wire:click="edit({{ $row->bookingroom_id }})"
                                                        wire:loading.attr="disabled"
                                                        class="{{ $btnBlk }} px-4 py-2">
                                                    {{ __('app.edit') }}
                                                </button>

                                                @if(!$row->deleted_at)
                                                    {{-- DELETE BUTTON --}}
                                                    <button type="button"
                                                            wire:click="confirmDelete({{ $row->bookingroom_id }}, '{{ str_replace('\'', '', $row->meeting_title ?? '') }}', false)"
                                                            wire:loading.attr="disabled"
                                                            class="px-4 py-2 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500/20 disabled:opacity-60 transition">
                                                        {{ __('app.delete') }}
                                                    </button>
                                                @else
                                                    {{-- RESTORE BUTTON --}}
                                                    <button type="button"
                                                            wire:click="restore({{ $row->bookingroom_id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="restore"
                                                            class="px-4 py-2 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm">
                                                        {{ __('app.restore') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        {{-- END: MODIFIED HISTORY CARD DESIGN (REJECTED) --}}
                                    @endforeach
                                </div>
                            @else
                                {{-- Rejected Table Layout --}}
                                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 bg-gray-50/70">
                                                <th class="px-6 py-3.5">#</th>
                                                <th class="px-6 py-3.5">{{ __('app.title_col') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.room_platform') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.date') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.time') }}</th>
                                                <th class="px-6 py-3.5">{{ __('app.reason') }}</th>
                                                <th class="px-6 py-3.5">Created Time</th>
                                                <th class="px-6 py-3.5">Last Edited</th>
                                                <th class="px-6 py-3.5">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($rejectedRows as $row)
                                                @php
                                                    $isOnline   = in_array($row->booking_type, ['onlinemeeting','online_meeting']);
                                                    $isRoomType = in_array($row->booking_type, ['bookingroom','meeting']);
                                                    $platform = $row->online_meeting_platform
                                                                ?? $row->platform
                                                                ?? $row->meeting_platform
                                                                ?? $row->online_provider
                                                                ?? ($isOnline ? 'Online Meeting' : null);
                                                @endphp
                                                <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                                    <td class="h-12 px-6 py-4 font-mono text-xs font-semibold text-gray-400">{{ $loop->iteration }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="font-semibold text-gray-900">{{ $row->meeting_title ?? '—' }}</div>
                                                        @if($row->deleted_at)
                                                            <span class="inline-flex items-center text-[10px] text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded font-medium mt-1">{{ __('app.deleted') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="flex justify-end">
                                                            @if($isOnline)
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold uppercase border border-emerald-200">
                                                                    {{ $platform ?? 'ONLINE' }}
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold uppercase border border-blue-200">
                                                                    {{ $row->room?->room_name ?? 'OFFLINE' }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-medium">{{ fmtDate($row->date) }}</td>
                                                    <td class="h-12 px-6 py-4 font-mono text-xs">{{ fmtTime($row->start_time) }}–{{ fmtTime($row->end_time) }}</td>
                                                    <td class="h-12 px-6 py-0 ">
                                                        <div class="text-xs text-rose-600 font-medium italic md:truncate md:max-w-xs" title="{{ $row->book_reject }}">
                                                            {{ $row->book_reject ?? __('app.no_reason_provided') }}
                                                        </div>
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-mono text-[11px] text-gray-500">
                                                        @if($row->created_at) {{ fmtDate($row->created_at) }} {{ fmtTime($row->created_at) }} @else — @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4 font-mono text-[11px] text-gray-500">
                                                        @if($row->updated_at) {{ fmtDate($row->updated_at) }} {{ fmtTime($row->updated_at) }} @else — @endif
                                                    </td>
                                                    <td class="h-12 px-6 py-4">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button type="button"
                                                                    wire:click="edit({{ $row->bookingroom_id }})"
                                                                    wire:loading.attr="disabled"
                                                                    class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:outline-none transition">
                                                                {{ __('app.edit') }}
                                                            </button>
                                                            @if(!$row->deleted_at)
                                                                <button type="button"
                                                                        wire:click="confirmDelete({{ $row->bookingroom_id }}, '{{ str_replace('\'', '', $row->meeting_title ?? '') }}', false)"
                                                                        wire:loading.attr="disabled"
                                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none transition">
                                                                    {{ __('app.delete') }}
                                                                </button>
                                                            @else
                                                                <button type="button"
                                                                        wire:click="restore({{ $row->bookingroom_id }})"
                                                                        wire:loading.attr="disabled"
                                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:outline-none transition">
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
                @endif

                {{-- PAGINATION --}}
                <div class="px-4 sm:px-6 py-5 bg-gray-50 border-t border-gray-200">
                    <div class="w-full">
                        @if($activeTab === 'done')
                            {{ $doneRows->onEachSide(1)->links() }}
                        @else
                            {{ $rejectedRows->onEachSide(1)->links() }}
                        @endif
                    </div>
                </div>
            </section>

            {{-- RIGHT: SIDEBAR (ROOM FILTER) --}}
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
                                        @php $active = !is_null($roomFilterId) && (int) $roomFilterId === (int) $r['id']; @endphp
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
    </main>

        {{-- EDIT / CREATE MODAL --}}
        @if($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="$set('showModal', false)"></div>

                {{-- Modal Content --}}
                <div class="relative w-full max-w-2xl bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col">
                    {{-- Header --}}
                    <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                                <x-heroicon-o-pencil class="w-4 h-4 text-[#CDDEA7]" />
                            </div>
                            <h3 class="font-bold tracking-tight text-base">
                                {{ $modalMode === 'create' ? __('app.create') : __('app.edit') }} {{ __('app.history_item') }}
                            </h3>
                        </div>
                        <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="$set('showModal', false)">✕</button>
                    </div>

                    {{-- Body --}}
                    <div class="p-6 space-y-4">
                        @if($modalMode === 'edit' && $editLastEdited)
                            <div class="text-[13px] text-gray-500 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 flex items-center gap-2 mb-2 shadow-sm">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400" />
                                <span>Created: <strong class="text-gray-700">{{ $editCreatedAt ?? '—' }}</strong> | Last Edited: <strong class="text-gray-700">{{ $editLastEdited }}</strong></span>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col justify-end">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.type') }}</label>
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selectedId: $wire.entangle('form.booking_type').live,
                                        options: [
                                            { id: 'meeting', label: '{{ __('app.meeting_option') }}' },
                                            { id: 'online_meeting', label: '{{ __('app.online_meeting_option') }}' }
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
                                            this.selectedId = 'meeting';
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
                                        <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="{{ __('app.type') }}" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-8">
                                        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                            <button x-show="search" type="button" @click.stop="clear()" class="text-muted-foreground hover:text-foreground">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                        </div>
                                    </div>
                                    <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm" style="display:none">
                                        <template x-for="item in items" :key="item.id">
                                            <li @click="select(item.id, item.label)" :class="selectedId == item.id ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.label"></li>
                                        </template>
                                    </ul>
                                    <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">{{ __('app.no_data') }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col justify-end">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.status_label') }}</label>
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selectedId: $wire.entangle('form.status').live,
                                        options: [
                                            { id: 'completed', label: '{{ __('app.done') }}' },
                                            { id: 'rejected', label: '{{ __('app.rejected') }}' }
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
                                            this.selectedId = 'completed';
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
                                        <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="{{ __('app.status_label') }}" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-8">
                                        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                            <button x-show="search" type="button" @click.stop="clear()" class="text-muted-foreground hover:text-foreground">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                        </div>
                                    </div>
                                    <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm" style="display:none">
                                        <template x-for="item in items" :key="item.id">
                                            <li @click="select(item.id, item.label)" :class="selectedId == item.id ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.label"></li>
                                        </template>
                                    </ul>
                                    <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">{{ __('app.no_data') }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.meeting_title_label') }}</label>
                            <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="form.meeting_title">
                            @error('form.meeting_title')
                                <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.date') }}</label>
                                <input type="date" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="form.date">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.start') }}</label>
                                <input type="time" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="form.start_time">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.end') }}</label>
                                <input type="time" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" wire:model.live="form.end_time">
                            </div>
                        </div>

                        @if(in_array($form['booking_type'] ?? null, ['bookingroom','meeting']))
                            <div class="flex flex-col justify-end">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.room_label') }}</label>
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        roomId: $wire.entangle('form.room_id').live,
                                        get items() {
                                            const q = (this.search || '').toLowerCase().trim();
                                            const list = @js(collect($rooms ?? [])->values()->toArray());
                                            if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                            return list.filter(i => !q || i.name.toLowerCase().includes(q));
                                        },
                                        get selectedLabel() {
                                            const list = @js(collect($rooms ?? [])->values()->toArray());
                                            const found = list.find(i => i.id == this.roomId);
                                            return found ? found.name : '';
                                        },
                                        select(id, name) {
                                            this.search = name;
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
                                        <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].name)" autocomplete="off" placeholder="{{ __('app.select_room_ph') }}" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-8">
                                        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                            <button x-show="search" type="button" @click.stop="clear()" class="text-muted-foreground hover:text-foreground">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                        </div>
                                    </div>
                                    <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm" style="display:none">
                                        <template x-for="item in items" :key="item.id">
                                            <li @click="select(item.id, item.name)" :class="roomId == item.id ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.name"></li>
                                        </template>
                                    </ul>
                                    <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">{{ __('app.no_data') }}</p>
                                </div>
                                @error('form.room_id')
                                    <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="flex flex-col justify-end">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.online_provider_label') }}</label>
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selectedId: $wire.entangle('form.online_provider').live,
                                        options: [
                                            { id: 'zoom', label: 'Zoom' },
                                            { id: 'google_meet', label: 'Google Meet' }
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
                                            this.selectedId = 'zoom';
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
                                        <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="{{ __('app.online_provider_label') }}" class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-8">
                                        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                            <button x-show="search" type="button" @click.stop="clear()" class="text-muted-foreground hover:text-foreground">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                        </div>
                                    </div>
                                    <ul x-show="open && items.length > 0" class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm" style="display:none">
                                        <template x-for="item in items" :key="item.id">
                                            <li @click="select(item.id, item.label)" :class="selectedId == item.id ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted cursor-pointer'" class="px-3.5 py-2.5 cursor-pointer transition-colors" x-text="item.label"></li>
                                        </template>
                                    </ul>
                                    <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">{{ __('app.no_data') }}</p>
                                </div>
                                @error('form.online_provider')
                                    <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if(($form['status'] ?? null) === 'rejected')
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.reject_reason') }} <span class="text-destructive">*</span></label>
                                <textarea
                                    class="w-full px-3.5 py-2.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                                    rows="3"
                                    placeholder="Tuliskan alasan penolakan…"
                                    wire:model.live="form.book_reject"></textarea>
                                @error('form.book_reject')
                                    <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.notes') }}</label>
                            <textarea class="w-full px-3.5 py-2.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                                      rows="3"
                                      wire:model.live="form.notes"></textarea>
                        </div>
                    </div>

                    {{-- Status Logs Section --}}
                    @if(count($statusLogs) > 0)
                        <div class="mt-6 border-t border-border pt-5 px-6 pb-6">
                            <h4 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                <x-heroicon-o-clock class="w-4 h-4 text-muted-foreground"/>
                                Status Logs
                            </h4>
                            <div class="bg-muted/10 rounded-xl border border-border overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-muted/20 border-b border-border text-muted-foreground uppercase font-semibold">
                                        <tr>
                                            <th class="px-3 py-2">Step</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="px-3 py-2">Logged At</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50 text-foreground">
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
                                            <tr class="hover:bg-background transition-colors">
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
                        <div class="mt-6 border-t border-border pt-5 px-6 pb-6">
                            <h4 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                                <x-heroicon-o-clock class="w-4 h-4 text-muted-foreground"/>
                                Status Logs
                            </h4>
                            <p class="text-xs text-muted-foreground bg-muted/10 p-3 rounded-lg border border-border text-center">No timeline data available.</p>
                        </div>
                    @endif

                    {{-- Footer --}}
                    <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5">
                        <button type="button"
                                wire:click="$set('showModal', false)"
                                wire:loading.attr="disabled"
                                class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                            <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                            <span>{{ __('app.cancel') }}</span>
                        </button>
                        <button type="button"
                                wire:click="save"
                                wire:loading.attr="disabled"
                                class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm inline-flex items-center gap-1.5">
                            <x-heroicon-o-check class="w-3.5 h-3.5" />
                            <span>{{ __('app.save') }}</span>
                        </button>
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
                    <h3 class="text-sm font-semibold tracking-tight text-gray-900">{{ __('app.filter_and_recent') }}</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_room_recent') }}</p>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" @click="showFilterModal = false">✕</button>
            </div>

            <div class="p-5 space-y-4 overflow-y-auto flex-1 bg-white">
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 flex items-center gap-1.5">{{ __('app.filter_by_room_label') }}</h4>

                    <button type="button"
                            wire:click="clearRoomFilter"
                            @click="showFilterModal = false"
                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-colors
                                {{ is_null($roomFilterId) ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                {{ __('app.all') }}
                            </span>
                            <span>{{ __('app.all_rooms') }}</span>
                        </span>
                    </button>

                    <div class="mt-2 space-y-1.5">
                        @forelse($roomsOptions as $r)
                            @php $active = !is_null($roomFilterId) && (int) $roomFilterId === (int) $r['id']; @endphp
                            <button type="button"
                                    wire:click="selectRoom({{ $r['id'] }})"
                                    @click="showFilterModal = false"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs border transition-colors
                                        {{ $active ? 'bg-[#4A2F24] text-[#CDDEA7] border-[#4A2F24] shadow-sm' : 'bg-white text-gray-800 border-gray-200 hover:bg-gray-50' }}">
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200/60 text-[10px] font-bold">
                                        {{ substr($r['label'], 0, 2) }}
                                    </span>
                                    <span class="truncate">{{ $r['label'] }}</span>
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
                            {{ $isForceDelete ? __(`app.delete_permanent_confirm`) : __(`app.delete_booking_confirm`) }}
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