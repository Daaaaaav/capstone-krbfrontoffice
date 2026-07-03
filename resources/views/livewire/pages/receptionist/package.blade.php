<div class="min-h-screen bg-background" wire:poll.1000ms.keep-alive>
    @php
        $card      = 'bg-card border border-border rounded-2xl shadow-xl overflow-hidden';
        $head      = 'bg-gradient-to-r from-slate-900 via-slate-800 to-black';
        $hpad      = 'px-6 py-5';
        $tag       = 'w-1.5 bg-white rounded-full';
        $label     = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input     = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk    = 'inline-flex items-center justify-center gap-1.5 px-3.5 h-8 text-xs font-semibold rounded-lg bg-secondary text-secondary-foreground border border-border hover:bg-secondary/80 transition shadow-sm';
        $btnGrn    = 'inline-flex items-center justify-center gap-1.5 px-3.5 h-8 text-xs font-semibold rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition shadow-sm';
        $btnAmb    = 'inline-flex items-center justify-center gap-1.5 px-3.5 h-8 text-xs font-semibold rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition shadow-sm';
        $btnRed    = 'inline-flex items-center justify-center gap-1.5 px-3.5 h-8 text-xs font-semibold rounded-lg bg-destructive text-destructive-foreground hover:bg-destructive/90 transition shadow-sm';
        $chip      = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-muted border border-border text-muted-foreground text-xs font-semibold';
        $mono      = 'inline-flex items-center text-[10px] font-mono text-muted-foreground bg-muted px-2.5 py-1 rounded-full border border-border';
        $icoAvatar = 'w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary font-bold text-sm shrink-0 border border-primary/20';
        $editIn    = 'w-full h-10 bg-background border border-input rounded-lg px-3.5 text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
    @endphp

    <style>
      :root { color-scheme: light; }
      select, option {
        color: var(--foreground) !important;
        background: var(--background) !important;
      }
      option:checked { background: var(--muted) !important; color: var(--foreground) !important; }
    </style>

    <div class="px-4 sm:px-6 py-6 space-y-6">
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-[#CDDEA7]/10 rounded-xl flex items-center justify-center backdrop-blur-sm border border-[#CDDEA7]/20">
                        <svg class="w-6 h-6 text-[#CDDEA7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m-4-4h8M4 6h16v12H4z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold">{{ __('app.on_going_packages') }}</h2>
                        <p class="text-xs text-[#CDDEA7]/80">{{ __('app.complete_package_data') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- FORM TAMBAH/EDIT --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                    <x-heroicon-o-plus-circle class="w-4.5 h-4.5 text-primary" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">{{ __('app.add_package') }}</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.complete_package_data') }}</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="{{ $label }}">{{ __('app.package_name') }}</label>
                        <input type="text" wire:model.defer="form.package_name" class="{{ $input }}" placeholder="{{ __('app.package_name_ph') }}">
                        @error('form.package_name') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col justify-end">
                        <label class="{{ $label }}">{{ __('app.storage') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: $wire.entangle('form.penyimpanan'),
                                options: [
                                    { id: '1', label: 'Rak 1' },
                                    { id: '2', label: 'Rak 2' },
                                    { id: '3', label: 'Rak 3' }
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
                                    this.selectedId = '';
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
                                <input
                                    type="text"
                                    x-model="search"
                                    @focus="open = true"
                                    @input="open = true"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                    autocomplete="off"
                                    placeholder="-"
                                    class="{{ $input }} pr-8"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                    <button
                                        x-show="search"
                                        type="button"
                                        @click.stop="clear()"
                                        class="text-muted-foreground hover:text-foreground"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    <svg class="fill-current h-4 w-4 text-muted-foreground/60 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                </div>
                            </div>
                            <ul
                                x-show="open && items.length > 0"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-border bg-card shadow-lg text-sm"
                                style="display:none"
                            >
                                <template x-for="item in items" :key="item.id">
                                    <li
                                        @click="select(item.id, item.label)"
                                        :class="selectedId == item.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-foreground hover:bg-muted cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.label"
                                    ></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">
                                {{ __('app.no_data') }}
                            </p>
                        </div>
                        @error('form.penyimpanan') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="{{ $label }}">Expedition / Sender</label>
                        <input type="text" wire:model.defer="form.nama_pengirim" class="{{ $input }}" placeholder="Kurir / Pengirim">
                        @error('form.nama_pengirim') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Owner (Penerima)</label>
                        <input type="text" wire:model.defer="form.nama_penerima" class="{{ $input }}" placeholder="Nama penerima">
                        @error('form.nama_penerima') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-4 border-t border-border bg-muted/5 -mx-6 -mb-6 p-6 flex items-center justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60 relative overflow-hidden">
                        <span class="flex items-center gap-2" wire:loading.remove wire:target="save">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span>{{ __('app.save_data') }}</span>
                        </span>
                        <span class="flex items-center gap-2" wire:loading wire:target="save">
                            <x-heroicon-o-arrow-path class="animate-spin h-4 w-4 text-white" />
                            <span>{{ __('app.saving_data') }}</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>

        {{-- ON-GOING (stored) --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <x-heroicon-o-archive-box-arrow-down class="w-4.5 h-4.5 text-amber-500" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">On-going Packages</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.no_pending_items') }}</p>
                </div>
            </div>

            <div class="p-6 space-y-4">
                @forelse ($ongoing as $r)
                    <div class="flex items-center justify-between p-4 rounded-xl bg-muted/20 border border-border hover:bg-muted/30 transition-colors"
                         wire:key="ongoing-{{ $r->delivery_id }}">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 bg-primary/10 border border-primary/20 rounded-xl flex items-center justify-center text-primary text-sm font-semibold shrink-0">
                                {{ strtoupper(substr($r->package_name, 0, 1)) }}
                            </div>
                            <div class="leading-tight min-w-0">
                                <div class="font-bold text-foreground text-sm truncate">{{ $r->package_name }}</div>
                                <div class="text-[11px] text-muted-foreground font-medium mt-1">
                                    Stored {{ optional($r->created_at)->format('d M Y H:i') ?? '—' }}
                                    • Container {{ $r->penyimpanan ?? '—' }}
                                    • Recep. {{ $r->receptionist->full_name ?? '—' }}
                                    • Sender {{ $r->nama_pengirim ?? '—' }}
                                    • Owner {{ $r->nama_penerima ?? '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button wire:click="openEdit({{ $r->delivery_id }})" wire:loading.attr="disabled"
                                    wire:target="openEdit({{ $r->delivery_id }})" class="{{ $btnBlk }}">
                                <span wire:loading.remove wire:target="openEdit({{ $r->delivery_id }})">{{ __('app.edit') }}</span>
                                <span wire:loading wire:target="openEdit({{ $r->delivery_id }})">{{ __('app.loading_label') }}</span>
                            </button>

                            <button wire:click="markDone({{ $r->delivery_id }})" wire:loading.attr="disabled"
                                    wire:target="markDone({{ $r->delivery_id }})" class="{{ $btnGrn }}">
                                <span class="inline-flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" wire:loading wire:target="markDone({{ $r->delivery_id }})">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0A12 12 0 000 12h4z" />
                                    </svg>
                                    <span>{{ __('app.done') }}</span>
                                </span>
                            </button>

                            <button wire:click="confirmDelete({{ $r->delivery_id }}, '{{ str_replace('\'', '', $r->package_name ?? '') }}')"
                                    wire:loading.attr="disabled"
                                    class="{{ $btnRed }}">
                                <span>{{ __('app.delete') }}</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-muted-foreground text-sm font-medium">{{ __('app.no_ongoing_packages') }}</div>
                @endforelse
            </div>

            <div class="px-6 py-4 bg-muted/10 border-t border-border w-full">
                {{ $ongoing->onEachSide(1)->links() }}
            </div>
        </div>

        {{-- COMPLETED (taken) --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <x-heroicon-o-check-badge class="w-4.5 h-4.5 text-emerald-500" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">Completed Packages</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Paket berstatus <b>taken</b></p>
                </div>
            </div>

            <div class="divide-y divide-border">
                @forelse ($done as $e)
                    @php $rowNo = (($done->firstItem() ?? 1) + $loop->index); @endphp

                    <div class="px-6 py-5 hover:bg-muted/10 transition-colors" wire:key="done-{{ $e->delivery_id }}">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                <div class="{{ $icoAvatar }}">{{ strtoupper(substr($e->package_name, 0, 1)) }}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <h4 class="font-bold text-foreground text-base truncate">{{ $e->package_name }}</h4>
                                        @if ($e->nama_pengirim)
                                            <span class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider bg-muted border border-border px-2 py-0.5 rounded-md">{{ __('app.sender') }}: {{ $e->nama_pengirim }}</span>
                                        @endif
                                        @if ($e->nama_penerima)
                                            <span class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider bg-muted border border-border px-2 py-0.5 rounded-md">{{ __('app.receiver') }}: {{ $e->nama_penerima }}</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-2 mb-2.5">
                                        <span class="{{ $chip }}">
                                            <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <span>{{ $e->penyimpanan ?? '—' }}</span>
                                        </span>
                                        <span class="{{ $chip }}">
                                            <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>{{ $e->receptionist->full_name ?? '—' }}</span>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span>Stored: {{ optional($e->created_at)->format('d M Y H:i') ?? '—' }}</span>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                            </svg>
                                            <span>Taken: {{ optional($e->pengambilan)->format('d M Y H:i') ?? '—' }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0 space-y-2 lg:self-stretch flex lg:flex-col lg:justify-between items-end flex-wrap gap-2 lg:gap-0">
                                <div class="flex flex-col items-end">
                                    <span class="{{ $mono }}">No. {{ $rowNo }}</span>
                                    <span class="text-[10px] text-muted-foreground/60 font-semibold mt-1">{{ optional($e->created_at)->format('d M Y H:i') }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2 justify-end">
                                    <button wire:click="openEdit({{ $e->delivery_id }})" wire:loading.attr="disabled"
                                            wire:target="openEdit({{ $e->delivery_id }})" class="{{ $btnBlk }}">
                                        <span wire:loading.remove wire:target="openEdit({{ $e->delivery_id }})">{{ __('app.edit') }}</span>
                                        <span wire:loading wire:target="openEdit({{ $e->delivery_id }})">{{ __('app.loading_label') }}</span>
                                    </button>

                                    <button wire:click="markStored({{ $e->delivery_id }})" wire:loading.attr="disabled"
                                            wire:target="markStored({{ $e->delivery_id }})" class="{{ $btnAmb }}">
                                        <span class="inline-flex items-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" wire:loading wire:target="markStored({{ $e->delivery_id }})">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0A12 12 0 000 12h4z" />
                                            </svg>
                                            <span>Move to On-going</span>
                                        </span>
                                    </button>

                                    <button wire:click="confirmDelete({{ $e->delivery_id }}, '{{ str_replace('\'', '', $e->package_name ?? '') }}')"
                                            wire:loading.attr="disabled"
                                            class="{{ $btnRed }}">
                                        <span>{{ __('app.delete') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-14 text-center text-muted-foreground text-sm font-medium">{{ __('app.no_completed_packages') }}</div>
                @endforelse
            </div>

            <div class="px-6 py-4 bg-muted/10 border-t border-border w-full">
                {{ $done->onEachSide(1)->links() }}
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
                        {{ $isForceDelete ? __(`app.delete_permanent_confirm`) : __(`app.delete_package_confirm`) }}
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
