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
@endphp

<div class="min-h-screen bg-gray-50" wire:poll.1000ms.keep-alive>
    <main class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">
        
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
                            <x-heroicon-o-document-text class="w-6 h-6 text-[#CDDEA7]"/>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold">{{ __('app.docpac_history_title') }}</h2>
                        <p class="text-sm text-[#CDDEA7]/80">{{ __('app.docpac_history_sub') }}</p>
                        </div>
                    </div>

                    {{-- MOBILE FILTER BUTTON --}}
                    <button type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-[#CDDEA7]/10 hover:bg-[#CDDEA7]/20 border border-[#CDDEA7]/20 text-[#CDDEA7] text-xs font-semibold md:hidden transition"
                        wire:click="openFilterModal">
                        <x-heroicon-o-bars-3 class="w-4 h-4"/>
                        <span>{{ __('app.filter') }}</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- MAIN LAYOUT: LEFT (ITEMS LIST) + RIGHT (SIDEBAR) --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
            {{-- LEFT: DONE LIST CARD --}}
            <section class="{{ $card }} md:col-span-3">
                {{-- Header: title + type scope --}}
                <div class="px-4 sm:px-6 pt-4 pb-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('app.completed_items') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('app.completed_items_sub') }}</p>
                    </div>

                    <div class="flex items-center gap-3 self-start sm:self-auto">
                        {{-- Segmented Tabs (All / Document / Package) --}}
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
                            <div class="relative">
                                <select class="{{ $input }} appearance-none pr-8 bg-white" wire:model.live="dateMode">
                                    <option value="semua">{{ __('app.sort_default_opt') }}</option>
                                    <option value="terbaru">{{ __('app.sort_newest_opt') }}</option>
                                    <option value="terlama">{{ __('app.sort_oldest_opt') }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- LIST (2-COLUMN GRID LAYOUT) --}}
                <div class="px-4 sm:px-6 py-5 bg-gray-50/50">
                    @if($viewMode === 'card')
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @forelse($done as $row)
                            @php
                                $avatarChar = strtoupper(substr($row->item_name ?? 'D', 0, 1));
                                $rowNo = ($done->firstItem() ?? 1) + $loop->index;
                                $isDelivered = $row->status === 'delivered';
                                $statusLabel = $isDelivered ? __('app.delivered') : __('app.taken');
                                $statusBg = $isDelivered ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800';

                                $completionDate = $row->created_at; // Default to created_at
                                if ($isDelivered && $row->pengiriman) {
                                    $completionDate = $row->pengiriman;
                                } elseif (!$isDelivered && $row->pengambilan) {
                                    $completionDate = $row->pengambilan;
                                }
                            @endphp

                            <div wire:key="done-{{ $row->delivery_id }}"
                                class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 hover:shadow-sm hover:border-gray-300 transition flex flex-col justify-between">
                                
                                <div class="space-y-3">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 bg-[#4E653D] rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0 overflow-hidden mt-0.5">
                                            {{ strtoupper(substr($row->item_name ?? 'D', 0, 1)) }}
                                        </div>

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
                                                    <span class="text-[11px] px-2 py-0.5 rounded-full flex-shrink-0 {{ $statusBg }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- Senders & Receiver information --}}
                                            <div class="space-y-2 text-[13px] text-gray-600 mb-3 border-y border-gray-100 py-2">
                                                @if($row->nama_pengirim)
                                                    <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                        <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                        <span class="truncate">{{ __('app.from_label') }}: <span class="font-semibold">{{ $row->nama_pengirim }}</span></span>
                                                    </div>
                                                @endif
                                                @if($row->nama_penerima)
                                                    <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                        <x-heroicon-o-user class="w-4 h-4 text-gray-500 shrink-0"/>
                                                        <span class="truncate">{{ __('app.to_label') }}: <span class="font-semibold">{{ $row->nama_penerima }}</span></span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Date / Receptionist details --}}
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg p-2 mt-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                            <span class="truncate">{{ fmtDate($completionDate) }} · {{ fmtTime($completionDate) }}</span>
                                        </div>
                                        @if($row->receptionist?->full_name)
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <x-heroicon-o-user-circle class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                <span class="truncate font-medium text-gray-700">{{ $row->receptionist->full_name }}</span>
                                            </div>
                                        @endif
                                        <div class="col-span-2 flex flex-col gap-1 min-w-0 pt-1 border-t border-gray-200/50 mt-1">
                                            @if($row->created_at)
                                                <div class="flex items-center gap-1.5">
                                                    <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                    <span class="truncate font-medium text-gray-600">Created: <span class="text-gray-900 font-semibold">{{ optional($row->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                </div>
                                            @endif
                                            @if($row->updated_at)
                                                <div class="flex items-center gap-1.5">
                                                    <x-heroicon-o-pencil-square class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                                    <span class="truncate font-medium text-gray-600">Last Edited: <span class="text-gray-900 font-semibold">{{ optional($row->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span></span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Show Image button --}}
                                @if($row->image && Storage::disk('public')->exists($row->image))
                                    <button type="button"
                                        @click="$dispatch('open-lightbox', { src: '{{ asset('storage/' . $row->image) }}' })"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-xs font-semibold transition">
                                        <x-heroicon-o-photo class="w-3.5 h-3.5 shrink-0"/>
                                        {{ __('app.lihat_bukti_foto') }}
                                    </button>
                                @endif

                                {{-- BOTTOM ACTIONS --}}
                                <div class="pt-3 border-t border-gray-100 mt-4 flex items-center justify-between">
                                    <span class="text-[10px] font-semibold text-gray-400 font-mono">
                                        #{{ $rowNo }}
                                    </span>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                            wire:loading.attr="disabled"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] focus:ring-2 focus:ring-[#4E653D]/20 focus:outline-none transition shadow-sm">
                                            {{ __('app.edit') }}
                                        </button>
                                        <button type="button" wire:click="confirmDelete({{ $row->delivery_id }}, '{{ addslashes($row->item_name) }}')"
                                            wire:loading.attr="disabled"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 focus:outline-none transition">
                                            {{ __('app.delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-16 text-center text-gray-500 text-sm bg-white border border-dashed border-gray-200 rounded-xl">
                                <x-heroicon-o-document-text class="w-8 h-8 mx-auto text-gray-300 mb-2"/>
                                {{ __('app.no_data_label') }}
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
                                        <th class="px-6 py-3.5">{{ __('app.item_name') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.type') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.status') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.sender') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.receiver') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.completed_at') }}</th>
                                        <th class="px-6 py-3.5">{{ __('app.officer') }}</th>
                                        <th class="px-6 py-3.5">Created Time</th>
                                        <th class="px-6 py-3.5">Last Edited</th>
                                        <th class="px-6 py-3.5 text-right">{{ __('app.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($done as $row)
                                        @php
                                            $rowNo = ($done->firstItem() ?? 1) + $loop->index;
                                            $isDelivered = $row->status === 'delivered';
                                            $statusLabel = $isDelivered ? __('app.delivered') : __('app.taken');
                                            $statusBg = $isDelivered ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800';

                                            $completionDate = $row->created_at;
                                            if ($isDelivered && $row->pengiriman) {
                                                $completionDate = $row->pengiriman;
                                            } elseif (!$isDelivered && $row->pengambilan) {
                                                $completionDate = $row->pengambilan;
                                            }
                                        @endphp
                                        <tr class="hover:bg-gray-50/50 transition text-sm text-gray-700">
                                            <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400">#{{ $rowNo }}</td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-[#4E653D] flex items-center justify-center text-white font-semibold text-xs shrink-0 overflow-hidden">
                                                        {{ strtoupper(substr($row->item_name ?? 'D', 0, 1)) }}
                                                    </div>
                                                    <div class="font-semibold text-gray-900">{{ $row->item_name }}</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full border border-gray-300 text-gray-700 bg-gray-50 font-medium uppercase">
                                                    {{ __('app.type_' . $row->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full {{ $statusBg }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">{{ $row->nama_pengirim ?? '—' }}</td>
                                            <td class="px-6 py-4">{{ $row->nama_penerima ?? '—' }}</td>
                                            <td class="px-6 py-4 font-medium">{{ fmtDate($completionDate) }} · {{ fmtTime($completionDate) }}</td>
                                            <td class="px-6 py-4 text-xs text-gray-500 font-medium">{{ $row->receptionist?->full_name ?? '—' }}</td>
                                            <td class="px-6 py-4 font-mono text-[11px] text-gray-500">
                                                @if($row->created_at) {{ optional($row->created_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }} @else — @endif
                                            </td>
                                            <td class="px-6 py-4 font-mono text-[11px] text-gray-500">
                                                @if($row->updated_at) {{ optional($row->updated_at)->timezone('Asia/Jakarta')->format('d M Y H:i') }} @else — @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex items-center justify-end gap-2 font-medium">
                                                    @if($row->image && Storage::disk('public')->exists($row->image))
                                                        <button type="button"
                                                            @click="$dispatch('open-lightbox', { src: '{{ asset('storage/' . $row->image) }}' })"
                                                            class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 focus:outline-none transition inline-flex items-center gap-1.5">
                                                            <x-heroicon-o-photo class="w-3.5 h-3.5"/>
                                                            {{ __('app.lihat_bukti_foto') }}
                                                        </button>
                                                    @endif
                                                    <button type="button" wire:click="openEdit({{ $row->delivery_id }})"
                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition">
                                                        {{ __('app.edit') }}
                                                    </button>
                                                    <button type="button" wire:click="confirmDelete({{ $row->delivery_id }}, '{{ addslashes($row->item_name) }}')"
                                                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition">
                                                        {{ __('app.delete') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">{{ __('app.no_data_label') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                    </div>

                {{-- Pagination --}}
                <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-white">
                    <div class="flex justify-center">
                        {{ $done->onEachSide(1)->links() }}
                    </div>
                </div>
            </section>

            {{-- RIGHT: SIDEBAR (DESKTOP / TABLET) --}}
            <aside class="hidden md:flex md:flex-col md:col-span-1 gap-4">
                {{-- Filter by Department & User --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50 rounded-t-2xl">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">{{ __('app.advanced_filters') }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_dept_user') }}</p>
                    </div>

                    <div class="p-4 space-y-4 bg-white">
                        {{-- Department Combobox --}}
                        <div class="space-y-1">
                            <label class="{{ $label }}">{{ __('app.department') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    get items() {
                                        const q = this.search.toLowerCase().trim();
                                        return @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray()).filter(i =>
                                            !q || i.label.toLowerCase().includes(q)
                                        );
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        $wire.set('departmentId', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        $wire.set('departmentId', null);
                                    }
                                }"
                                x-init="
                                    $watch('$wire.departmentId', val => {
                                        if (!val) { search = ''; }
                                        else {
                                            const found = @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray()).find(i => i.id == val);
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
                                        placeholder="{{ __('app.all_departments') }}"
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
                                            :class="$wire.departmentId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                            class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                            x-text="item.label"
                                        ></li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                    {{ __('app.no_data_label') }}
                                </p>
                            </div>
                        </div>

                        {{-- User / Receptionist Combobox --}}
                        <div class="space-y-1">
                            <label class="{{ $label }}">Receptionist / User</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    get items() {
                                        const q = this.search.toLowerCase().trim();
                                        const deptId = $wire.departmentId;
                                        const all = @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name, 'dept' => $u->department_id])->values()->toArray());
                                        const filtered = deptId ? all.filter(i => i.dept == deptId) : all;
                                        return q ? filtered.filter(i => i.label.toLowerCase().includes(q)) : filtered;
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        $wire.set('userId', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        $wire.set('userId', null);
                                    }
                                }"
                                x-init="
                                    $watch('$wire.departmentId', () => { search = ''; $wire.set('userId', null); });
                                    $watch('$wire.userId', val => {
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
                                            :class="$wire.userId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                            class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                            x-text="item.label"
                                        ></li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                    {{ __('app.no_data_label') }}
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
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('app.filter_by_dept_user') }}</p>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" wire:click="closeFilterModal">✕</button>
                </div>

                <div class="p-5 space-y-5 overflow-y-auto flex-1 bg-white">
                    {{-- Department Combobox (Mobile) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">{{ __('app.department') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                get items() {
                                    const q = this.search.toLowerCase().trim();
                                    return @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray()).filter(i =>
                                        !q || i.label.toLowerCase().includes(q)
                                    );
                                },
                                select(id, label) {
                                    this.search = label;
                                    $wire.set('departmentId', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    $wire.set('departmentId', null);
                                }
                            }"
                            x-init="
                                $watch('$wire.departmentId', val => {
                                    if (!val) { search = ''; }
                                    else {
                                        const found = @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray()).find(i => i.id == val);
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
                                    placeholder="{{ __('app.all_departments') }}"
                                    class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition pr-8"
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
                                        :class="$wire.departmentId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.label"
                                    ></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                {{ __('app.no_data_label') }}
                            </p>
                        </div>
                    </div>

                    {{-- User / Receptionist Combobox (Mobile) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">Receptionist / User</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                get items() {
                                    const q = this.search.toLowerCase().trim();
                                    const deptId = $wire.departmentId;
                                    const all = @js($users->map(fn($u) => ['id' => $u->user_id, 'label' => $u->full_name, 'dept' => $u->department_id])->values()->toArray());
                                    const filtered = deptId ? all.filter(i => i.dept == deptId) : all;
                                    return q ? filtered.filter(i => i.label.toLowerCase().includes(q)) : filtered;
                                },
                                select(id, label) {
                                    this.search = label;
                                    $wire.set('userId', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    $wire.set('userId', null);
                                }
                            }"
                            x-init="
                                $watch('$wire.departmentId', () => { search = ''; $wire.set('userId', null); });
                                $watch('$wire.userId', val => {
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
                                    class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition pr-8"
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
                                        :class="$wire.userId == item.id ? 'bg-[#4E653D] text-white' : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.label"
                                    ></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-400" style="display:none">
                                {{ __('app.no_data_label') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-gray-200 bg-gray-50">
                    <button type="button" class="w-full h-10 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition shadow-sm"
                        wire:click="closeFilterModal">
                        {{ __('app.apply_close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

{{-- MODALS WRAPPER --}}
<div>
    {{-- EDIT MODAL --}}
    @if($showEdit)
        <div wire:key="edit-modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" wire:click="$set('showEdit', false)"></div>
            <div class="relative w-full max-w-lg bg-white rounded-2xl border border-gray-200 shadow-2xl overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                            <x-heroicon-o-pencil class="w-4 h-4 text-[#CDDEA7]" />
                        </div>
                        <h3 class="font-bold text-base tracking-tight">{{ __('app.edit') }}</h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition" wire:click="$set('showEdit', false)">✕</button>
                </div>
                <div class="p-6 space-y-4 bg-white">
                    @if($editLastEdited)
                        <div class="text-[13px] text-gray-500 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 flex items-center gap-2 shadow-sm">
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400" />
                            <span>Created: <strong class="text-gray-700">{{ $editCreatedAt ?? '—' }}</strong> | Last Edited: <strong class="text-gray-700">{{ $editLastEdited }}</strong></span>
                        </div>
                    @endif
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">{{ __('app.item_name') }}</label>
                        <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition" wire:model.defer="edit.item_name">
                        @error('edit.item_name') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">{{ __('app.sender_name') }}</label>
                            <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition" wire:model.defer="edit.nama_pengirim">
                            @error('edit.nama_pengirim') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">{{ __('app.receiver_name') }}</label>
                            <input type="text" class="w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition" wire:model.defer="edit.nama_penerima">
                            @error('edit.nama_penerima') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-gray-100">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Document Photo</label>
                        <div class="flex gap-4">
                            <div class="w-32 h-40 shrink-0 bg-gray-50 border border-gray-200 rounded-lg overflow-hidden relative flex items-center justify-center">
                                @if($editPhoto)
                                    <img src="{{ $editPhoto->temporaryUrl() }}" class="w-full h-full object-cover">
                                @elseif($editCurrentImage && Storage::disk('public')->exists($editCurrentImage))
                                    <img src="{{ asset('storage/' . $editCurrentImage) }}" class="w-full h-full object-cover">
                                @else
                                    <x-heroicon-o-document-text class="w-10 h-10 text-gray-300" />
                                @endif
                                <div class="absolute inset-0 border border-black/5 rounded-lg pointer-events-none"></div>
                            </div>
                            <div class="flex-1 space-y-3 flex flex-col justify-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Replace Document Photo</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Upload a new image to replace the current one. Max size 2MB.</p>
                                </div>
                                <input
                                    type="file"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer"
                                    wire:model="editPhoto"
                                    accept="image/*"
                                >
                                <div wire:loading wire:target="editPhoto" class="text-xs text-[#4E653D] font-medium animate-pulse">
                                    Uploading...
                                </div>
                                @error('editPhoto') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pt-5 border-t border-gray-200 flex items-center justify-end gap-3 bg-gray-50/50 p-4 mt-6">
                    <button type="button" wire:click="$set('showEdit', false)"
                        class="h-9 px-4 rounded-lg bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition inline-flex items-center gap-1.5 text-xs font-semibold">
                        <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" />
                        <span>{{ __('app.cancel') }}</span>
                    </button>
                    <button type="button" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit"
                        class="h-9 px-4 rounded-lg bg-[#4E653D] text-white text-xs font-semibold hover:bg-[#354C2B] transition shadow-sm inline-flex items-center gap-1.5 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveEdit">{{ __('app.save_changes') }}</span>
                        <span wire:loading wire:target="saveEdit" class="flex items-center gap-1.5">
                            <x-heroicon-o-arrow-path class="animate-spin h-3.5 w-3.5 text-white"/>
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
        class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        @click.self="open = false"
        style="display:none">
        <button type="button" @click="open = false"
            class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img :src="src" alt="Bukti foto" class="max-w-full max-h-[90vh] rounded-xl shadow-2xl object-contain">
    </div>

    {{-- DELETE MODAL --}}
    @if($showDeleteModal)
        <div wire:key="delete-modal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
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
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('app.are_you_sure_delete') }}</h3>
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
</div>