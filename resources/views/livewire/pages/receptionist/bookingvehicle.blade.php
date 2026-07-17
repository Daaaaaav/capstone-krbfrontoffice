<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-xl overflow-hidden';
        $head   = 'bg-[#4A2F24]';
        $hpad   = 'px-6 py-5';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    <style>
      :root { color-scheme: light; }
      select, option {
        color: var(--foreground) !important;
        background: var(--background) !important;
      }
      option:checked { background: var(--muted) !important; color: var(--foreground) !important; }
    </style>

    <main class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.vehicle_booking_title') }}"
            subtitle="{{ __('app.vehicle_booking_subtitle') }}" />

        {{-- MAIN LAYOUT: LEFT (FORM) + RIGHT (SIDEBAR) --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            {{-- LEFT: FORM CARD --}}
            <div class="{{ $card }} lg:col-span-3">
            <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                    <x-heroicon-o-truck class="w-4.5 h-4.5 text-primary" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">{{ __('app.booking_form_title') }}</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.booking_form_subtitle') }}</p>
                </div>
            </div>

            <div class="p-6">
                @if(session()->has('success'))
                    <div class="mb-6 inline-flex items-center gap-2.5 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold shadow-sm w-full">
                        <svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                {{-- Rules & Notes Alert --}}
                <div class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-400 shadow-sm">
                    <div class="flex items-center gap-2 mb-2.5">
                        <x-heroicon-o-information-circle class="w-5 h-5 shrink-0" />
                        <h4 class="text-sm font-bold tracking-wide">Rules & Notes</h4>
                    </div>
                    <ul class="list-disc list-outside text-xs space-y-1.5 ml-5 text-blue-600/90 dark:text-blue-300/90 leading-relaxed">
                        <li><strong>Maximum Booking Limit:</strong> You can only book a vehicle up to 1 month in advance from today's date.</li>
                        <li><strong>Late Return Label:</strong> Returns delayed by more than 1 hour will be automatically flagged as 'Late Return'.</li>
                        <li><strong>Mandatory Reason:</strong> If the return is delayed by more than 3 hours, a mandatory explanation/reason must be provided before completing the task.</li>
                        <li><strong>Grace Period:</strong> Delays under 1 hour are still considered on-time (Success).</li>
                    </ul>
                </div>

                <form wire:submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        {{-- Departemen --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.department') }} <span class="text-destructive">*</span></label>

                            {{-- Combobox: search + select in one --}}
                            <div
                                x-data="{
                                    open: false,
                                    search: $wire.departmentSearch,
                                    selectedId: null,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray());
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const id = $wire.department_id;
                                        const found = @js($departments->map(fn($d) => ['id' => $d->department_id, 'label' => $d->department_name])->values()->toArray()).find(i => i.id == id);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('department_id', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('department_id', null);
                                        $wire.set('departmentSearch', '');
                                    }
                                }"
                                x-init="
                                    $watch('search', val => $wire.set('departmentSearch', val));
                                    $watch('$wire.department_id', val => {
                                        this.selectedId = val || null;
                                        if (!val) { search = ''; }
                                        else { search = selectedLabel; }
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
                                        placeholder="{{ __('app.search_department') }}"
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

                                {{-- Dropdown list --}}
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

                                {{-- Hidden real input for Livewire --}}
                                <input type="hidden" wire:model="department_id">
                            </div>

                            @error('department_id')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- User peminjam (filtered by department) --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.user_filtered') }}</label>

                            {{-- Combobox: search + select in one --}}
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: null,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = ($wire.usersForCombobox || []);
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return q ? list.filter(i => i.label.toLowerCase().includes(q) || (i.email && i.email.toLowerCase().includes(q))) : list;
                                    },
                                    get selectedLabel() {
                                        const list = ($wire.usersForCombobox || []);
                                        const found = list.find(i => i.id == this.selectedId);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('borrower_user_id', id);
                                        if (!$wire.borrower_name) $wire.set('borrower_name', label);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('borrower_user_id', null);
                                        $wire.set('userSearch', '');
                                    }
                                }"
                                x-init="
                                    $watch('search', val => $wire.set('userSearch', val));
                                    $watch('$wire.department_id', () => { search = ''; this.selectedId = null; });
                                    $watch('$wire.borrower_user_id', val => { this.selectedId = val || null; });
                                "
                                class="relative"
                                @click.outside="open = false"
                            >
                                <div class="relative">
                                    <input
                                        type="text"
                                        x-model="search"
                                        @focus="if ($wire.department_id) open = true"
                                        @input="if ($wire.department_id) open = true"
                                        @keydown.escape="open = false"
                                        @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                        autocomplete="off"
                                        placeholder="{{ __('app.search_user') }}"
                                        :disabled="!$wire.department_id"
                                        class="{{ $input }} pr-8 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed"
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

                                {{-- Dropdown list --}}
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
                                        >
                                            <span x-text="item.label" class="font-medium"></span>
                                            <span x-show="item.email" x-text="' — ' + item.email" class="text-xs opacity-60"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="open && items.length === 0 && search && $wire.department_id" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">
                                    {{ __('app.no_users_found') }}
                                </p>

                                {{-- Hidden real input for Livewire --}}
                                <input type="hidden" wire:model="borrower_user_id">
                            </div>

                            {{-- Hover tooltip instead of always-visible hint text --}}
                            <div class="relative inline-flex items-center gap-1 mt-1.5 group">
                                <svg class="w-3.5 h-3.5 text-muted-foreground/50 cursor-help shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                                </svg>
                                <span class="text-[11px] text-muted-foreground/50 cursor-help">{{ __('app.user_hint_short') }}</span>
                                <div class="absolute bottom-full left-0 mb-2 w-56 hidden group-hover:block z-50">
                                    <div class="bg-gray-900 text-white text-[11px] leading-relaxed rounded-lg px-3 py-2 shadow-xl">
                                        <p class="font-medium mb-0.5">{{ __('app.select_dept_first') }}</p>
                                        <p class="text-white/70">{{ __('app.borrower_name_hint') }}</p>
                                        <div class="absolute top-full left-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-l-transparent border-r-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                            @error('borrower_user_id')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Nama peminjam manual --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">
                                {{ __('app.borrower_name') }} <span class="text-destructive">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model.defer="borrower_name"
                                placeholder="{{ __('app.borrower_name') }}"
                                class="{{ $input }}"
                            >
                            @error('borrower_name')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Odd/even --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.odd_even_area') }}</label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: $wire.odd_even_area,
                                    options: [
                                        { id: 'tidak', label: @js(__('app.not_enter')) },
                                        { id: 'ganjil', label: @js(__('app.odd')) },
                                        { id: 'genap', label: @js(__('app.even')) }
                                    ],
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return this.options;
                                        return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const found = this.options.find(i => i.id === $wire.odd_even_area);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('odd_even_area', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = 'tidak';
                                        $wire.set('odd_even_area', 'tidak');
                                    }
                                }"
                                x-init="
                                    search = selectedLabel;
                                    $watch('$wire.odd_even_area', val => {
                                        this.selectedId = val || 'tidak';
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
                                        placeholder="{{ __('app.odd_even_area') }}"
                                        class="{{ $input }} pr-8"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                        <button
                                            x-show="search && selectedId !== 'tidak'"
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

                                <input type="hidden" wire:model="odd_even_area">
                            </div>
                            @error('odd_even_area')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Kendaraan --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.vehicle') }} <span class="text-destructive">*</span></label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: null,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = @js(collect($vehicles)->map(fn($v) => ['id' => $v->vehicle_id, 'label' => ($v->name ?? __('app.vehicle')) . ($v->plate_number ? ' — ' . $v->plate_number : '')])->values()->toArray());
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const id = $wire.vehicle_id;
                                        const list = @js(collect($vehicles)->map(fn($v) => ['id' => $v->vehicle_id, 'label' => ($v->name ?? __('app.vehicle')) . ($v->plate_number ? ' — ' . $v->plate_number : '')])->values()->toArray());
                                        const found = list.find(i => i.id == id);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('vehicle_id', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('vehicle_id', null);
                                    }
                                }"
                                x-init="
                                    $watch('$wire.vehicle_id', val => {
                                        this.selectedId = val || null;
                                        if (!val) { search = ''; }
                                        else { search = selectedLabel; }
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
                                        placeholder="{{ __('app.select_vehicle') }}"
                                        @if(!$hasVehicles) disabled @endif
                                        class="{{ $input }} pr-8 disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed"
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

                                <input type="hidden" wire:model="vehicle_id">
                            </div>
                            @error('vehicle_id')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Tanggal pinjam --}}
                        <div>
                            <label class="{{ $label }}">{{ __('app.borrow_date') }} <span class="text-destructive">*</span></label>
                            <input type="date" wire:model.defer="date_from" class="{{ $input }}">
                            @error('date_from')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Tanggal kembali --}}
                        <div>
                            <label class="{{ $label }}">{{ __('app.return_date') }} <span class="text-destructive">*</span></label>
                            <input type="date" wire:model.defer="date_to" class="{{ $input }}">
                            @error('date_to')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Jam mulai --}}
                        <div>
                            <label class="{{ $label }}">{{ __('app.start_time') }} <span class="text-destructive">*</span></label>
                            <input type="time" wire:model.defer="start_time" class="{{ $input }}">
                            @error('start_time')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Jam selesai --}}
                        <div>
                            <label class="{{ $label }}">{{ __('app.end_time') }} <span class="text-destructive">*</span></label>
                            <input type="time" wire:model.defer="end_time" class="{{ $input }}">
                            @error('end_time')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>



                        {{-- Jenis keperluan --}}
                        <div class="flex flex-col justify-end">
                            <label class="{{ $label }}">{{ __('app.purpose_type') }} <span class="text-destructive">*</span></label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: $wire.purpose_type,
                                    options: [
                                        { id: 'dinas', label: @js(__('app.official')) },
                                        { id: 'operasional', label: @js(__('app.operational')) },
                                        { id: 'antar_jemput', label: @js(__('app.pickup_dropoff')) },
                                        { id: 'lainnya', label: @js(__('app.other')) }
                                    ],
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return this.options;
                                        return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const found = this.options.find(i => i.id === $wire.purpose_type);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('purpose_type', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('purpose_type', null);
                                    }
                                }"
                                x-init="
                                    search = selectedLabel;
                                    $watch('$wire.purpose_type', val => {
                                        this.selectedId = val || null;
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
                                        placeholder="{{ __('app.select_purpose') }}"
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

                                <input type="hidden" wire:model="purpose_type">
                            </div>
                            @error('purpose_type')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Keperluan --}}
                        <div class="md:col-span-2">
                            <label class="{{ $label }}">{{ __('app.purpose') }} <span class="text-destructive">*</span></label>
                            <input
                                type="text"
                                wire:model.defer="purpose"
                                placeholder="{{ __('app.purpose_placeholder') }}"
                                class="{{ $input }}"
                            >
                            @error('purpose')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Tujuan --}}
                        <div class="md:col-span-3">
                            <label class="{{ $label }}">{{ __('app.destination') }}</label>
                            <input
                                type="text"
                                wire:model.defer="destination"
                                placeholder="{{ __('app.destination_placeholder') }}"
                                class="{{ $input }}"
                            >
                            @error('destination')
                                <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Conditional field for "Lainnya" --}}
                        @if($purpose_type === 'lainnya')
                            <div class="bg-primary/5 border border-primary/20 rounded-2xl p-5 md:col-span-3">
                                <label class="{{ $label }}">
                                    {{ __('app.other_purpose_detail') }} <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.defer="purpose_type_other"
                                    placeholder="{{ __('app.other_purpose_placeholder') }}"
                                    class="{{ $input }}"
                                >
                                <p class="text-[11px] text-muted-foreground mt-1.5 font-medium">
                                    {{ __('app.other_purpose_required') }}
                                </p>
                                @error('purpose_type_other')
                                    <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                    </div>

                    {{-- Submit Button --}}
                    <div class="pt-4 border-t border-border bg-muted/5 -mx-6 -mb-6 p-6 flex items-center justify-end">
                        <button type="submit" class="{{ $btnBlk }}">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span>{{ __('app.submit_booking') }}</span>
                        </button>
                    </div>
                </form>
            </div>
            </div>

            {{-- RIGHT: SIDEBAR (DESKTOP) --}}
            <aside class="hidden lg:flex lg:flex-col lg:col-span-1 gap-4">
                {{-- Available Vehicles Widget --}}
                <div class="bg-card rounded-2xl border border-border shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-border bg-muted/30 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Fleet Availability</h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5">Quick overview of available vehicles</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse($vehiclesForDirectory ?? [] as $vehicle)
                            <div wire:click="openVehicleScheduleModal({{ $vehicle->vehicle_id }})" class="flex items-start gap-3 p-2 rounded-lg hover:bg-muted/50 transition cursor-pointer">
                                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-semibold shrink-0">
                                    <x-heroicon-o-truck class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $vehicle->name ?? __('app.vehicle') }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $vehicle->plate_number ?? 'No Plate' }}</p>
                                </div>
                            </div>

                        @empty
                            <div class="text-center py-6">
                                <x-heroicon-o-truck class="w-8 h-8 mx-auto text-muted-foreground/30 mb-2"/>
                                <p class="text-xs text-muted-foreground">No vehicles available.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </main>

    {{-- Vehicle Schedule Modal --}}
    @if($showVehicleScheduleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-data="{ show: @entangle('showVehicleScheduleModal') }" x-show="show" x-cloak>
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="show = false"></div>
            <div class="relative w-full max-w-5xl h-fit max-h-[90vh] bg-card border border-border rounded-2xl shadow-2xl overflow-hidden transform transition-all flex flex-col"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div class="px-5 py-4 border-b border-border bg-muted/10 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Vehicle Schedule</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">30-Day Timeline</p>
                    </div>
                    <button @click="show = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition">
                        ✕
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    @if(empty($vehicleScheduleData))
                        <div class="text-center py-8">
                            <x-heroicon-o-calendar-days class="w-10 h-10 mx-auto text-muted-foreground/30 mb-3"/>
                            <h4 class="text-sm font-medium text-foreground">No bookings</h4>
                            <p class="text-xs text-muted-foreground mt-1">This vehicle is fully available for the next 30 days.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                            @foreach($vehicleScheduleData as $booking)
                            @php $isPriority = !empty($booking['is_priority']); @endphp
                                <div wire:click="openBookingDetail('{{ $booking['id'] }}')"
                                     wire:key="vschedule-{{ $booking['id'] }}"
                                     class="cursor-pointer p-3 rounded-xl border shadow-sm hover:border-primary/30 transition-colors
                                        {{ $isPriority ? 'border-amber-300 bg-amber-50/40' : 'border-border bg-muted/5' }}">
                                    {{-- Priority star badge --}}
                                    @if($isPriority)
                                    <div class="flex items-center gap-1 mb-1.5">
                                        <svg class="w-3 h-3 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                        <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wide">Priority</span>
                                    </div>
                                    @endif
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="font-bold text-foreground text-sm leading-tight break-words">{{ $booking['title'] }}</div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase whitespace-nowrap
                                            {{ $isPriority ? 'bg-amber-500/15 text-amber-700' : 'bg-primary/10 text-primary' }}">
                                            {{ $booking['status'] }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col text-[11px] text-muted-foreground font-medium gap-1.5 mt-2">
                                        <div class="flex items-center gap-1.5">
                                            <x-heroicon-o-calendar-days class="w-3.5 h-3.5"/>
                                            <span><strong>Start:</strong> {{ $booking['start_date'] }}, {{ $booking['start_time'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <x-heroicon-o-calendar-days class="w-3.5 h-3.5"/>
                                            <span><strong>End:</strong> {{ $booking['end_date'] }}, {{ $booking['end_time'] }}</span>
                                        </div>
                                        @if($isPriority && !empty($booking['requested_by']))
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <x-heroicon-o-user class="w-3.5 h-3.5"/>
                                            <span>{{ $booking['requested_by'] }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="px-5 py-4 border-t border-border bg-muted/5 flex justify-end">
                    <button @click="show = false" class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm">Close</button>
                </div>
            </div>
        </div>
    @endif

    @if($showBookingDetailModal && !empty($selectedBookingDetail))
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6" x-data="{ showDetail: @entangle('showBookingDetailModal') }" x-show="showDetail" x-cloak>
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" @click="showDetail = false"></div>
            <div class="relative w-full max-w-lg bg-card border border-border rounded-2xl shadow-2xl overflow-hidden transform transition-all flex flex-col"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                @php $detailIsPriority = !empty($selectedBookingDetail['is_priority']); @endphp

                <div class="px-6 py-4 border-b border-border {{ $detailIsPriority ? 'bg-amber-50/60' : 'bg-muted/5' }} flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            @if($detailIsPriority)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 text-[10px] font-bold uppercase tracking-wide">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                Priority
                            </span>
                            @endif
                            <h3 class="text-lg font-bold text-foreground leading-tight">{{ $selectedBookingDetail['vehicle_name'] }}</h3>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">Vehicle Booking Detail</p>
                    </div>
                    <button @click="showDetail = false" class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-md hover:bg-muted/10">
                        ✕
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Vehicle</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['vehicle_name'] }} ({{ $selectedBookingDetail['plate_number'] }})</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Status</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase
                                {{ $detailIsPriority ? 'bg-amber-500/15 text-amber-700' : 'bg-primary/10 text-primary' }}">
                                {{ $selectedBookingDetail['status'] }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">
                                {{ $detailIsPriority ? 'Requested by (Manager)' : 'Borrower' }}
                            </p>
                            <p class="text-sm font-semibold text-foreground">
                                {{ $detailIsPriority ? ($selectedBookingDetail['requested_by'] ?? '—') : $selectedBookingDetail['borrower'] }}
                            </p>
                        </div>
                        @if(!$detailIsPriority)
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Department</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['department'] }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Start</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['start_at_full'] }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">End</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['end_at_full'] }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Purpose</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['purpose'] }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Destination</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['destination'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-border flex justify-start">
                    <button @click="showDetail = false" class="px-5 py-2 rounded-xl text-sm font-bold border border-border bg-card text-foreground hover:bg-muted transition shadow-sm">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
