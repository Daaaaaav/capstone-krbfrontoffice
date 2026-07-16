<div class="min-h-screen bg-background" wire:poll.30000ms.keep-alive>
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-xl overflow-hidden';
        $head   = 'bg-[#4A2F24]';
        $hpad   = 'px-6 py-5';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    @push('styles')
    <style>
      :root { color-scheme: light; }
      select, option {
        color: var(--foreground) !important;
        background: var(--background) !important;
      }
      option:checked { background: var(--muted) !important; color: var(--foreground) !important; }
    </style>
    @endpush

    <div class="px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.docpac_form_title') }}"
            subtitle="{{ __('app.docpac_form_sub') }}" />

        {{-- FORM --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-border bg-muted/10">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 bg-primary rounded-full animate-pulse"></div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">{{ __('app.add_data') }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_data_sub') }}</p>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-6">
                {{-- Row: Direction & Type & Storage --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="flex flex-col justify-end">
                        <label class="{{ $label }}">{{ __('app.type') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: $wire.direction,
                                options: [
                                    { id: 'taken', label: @js(__('app.incoming') . ' (' . __('app.taken') . ')') },
                                    { id: 'deliver', label: @js(__('app.outgoing') . ' (' . __('app.deliver') . ')') }
                                ],
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return this.options;
                                    return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const found = this.options.find(i => i.id === $wire.direction);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.selectedId = id;
                                    $wire.set('direction', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.selectedId = 'taken';
                                    $wire.set('direction', 'taken');
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('$wire.direction', val => {
                                    this.selectedId = val || 'taken';
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
                                    placeholder="{{ __('app.type') }}"
                                    class="{{ $input }} pr-8"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                    <button
                                        x-show="search && selectedId !== 'taken'"
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
                            <input type="hidden" wire:model="direction">
                        </div>
                        @error('direction') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col justify-end">
                        <label class="{{ $label }}">{{ __('app.type') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: $wire.itemType,
                                options: [
                                    { id: 'package', label: @js(__('app.type_package')) },
                                    { id: 'document', label: @js(__('app.type_document')) }
                                ],
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return this.options;
                                    return this.options.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const found = this.options.find(i => i.id === $wire.itemType);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.selectedId = id;
                                    $wire.set('itemType', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.selectedId = 'package';
                                    $wire.set('itemType', 'package');
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('$wire.itemType', val => {
                                    this.selectedId = val || 'package';
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
                                    placeholder="{{ __('app.type') }}"
                                    class="{{ $input }} pr-8"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                    <button
                                        x-show="search && selectedId !== 'package'"
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
                            <input type="hidden" wire:model="itemType">
                        </div>
                        @error('itemType') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.storage_location') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: null,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = @js(collect($storages)->map(fn($s) => ['id' => $s['id'], 'label' => $s['name']])->values()->toArray());
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const list = @js(collect($storages)->map(fn($s) => ['id' => $s['id'], 'label' => $s['name']])->values()->toArray());
                                    const found = list.find(i => i.id == $wire.storageId);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.selectedId = id;
                                    $wire.set('storageId', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.selectedId = null;
                                    $wire.set('storageId', null);
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('$wire.storageId', val => {
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
                                    placeholder="{{ __('app.select_storage') }}"
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
                            <input type="hidden" wire:model="storageId">
                        </div>
                        @error('storageId') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Item name --}}
                <div>
                    <label class="{{ $label }}">{{ __('app.item_name_label') }}</label>
                    <input type="text" class="{{ $input }}" wire:model.defer="itemName" placeholder="{{ __('app.item_name_placeholder') }}">
                    @error('itemName') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-5">
                        <div>
                            <label class="{{ $label }}">
                                {{ $direction === 'taken' ? __('app.receiver_dept') : __('app.sender_dept') }}
                            </label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: null,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                    },
                                    get selectedLabel() {
                                        const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                        const found = list.find(i => i.id == $wire.departmentId);
                                        return found ? found.label : '';
                                    },
                                    select(id, label) {
                                        this.search = label;
                                        this.selectedId = id;
                                        $wire.set('departmentId', id);
                                        this.open = false;
                                    },
                                    clear() {
                                        this.search = '';
                                        this.selectedId = null;
                                        $wire.set('departmentId', null);
                                    }
                                }"
                                x-init="
                                    search = selectedLabel;
                                    $watch('$wire.departmentId', val => {
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
                                        placeholder="{{ __('app.select_department') }}…"
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
                                            x-bind:class="item.id == selectedId
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
                                <input type="hidden" wire:model="departmentId">
                            </div>
                            @error('departmentId') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">
                                {{ $direction === 'taken' ? __('app.receiver_user') : __('app.sender_user') }}
                            </label>
                            <div
                                x-data="{
                                    open: false,
                                    search: '',
                                    selectedId: null,
                                    get items() {
                                        const q = (this.search || '').toLowerCase().trim();
                                        const list = Object.entries($wire.users || {}).map(([id, name]) => ({ id: parseInt(id), label: name }));
                                        if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                        return q ? list.filter(i => i.label.toLowerCase().includes(q)) : list;
                                    },
                                    get selectedLabel() {
                                        const found = Object.entries($wire.users || {}).find(([id]) => id == $wire.userId);
                                        return found ? found[1] : '';
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
                                    search = selectedLabel;
                                    $watch('$wire.departmentId', () => { search = ''; this.selectedId = null; });
                                    $watch('$wire.userId', val => {
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
                                        @focus="if ($wire.departmentId && Object.keys($wire.users || {}).length) open = true"
                                        @input="if ($wire.departmentId) open = true"
                                        @keydown.escape="open = false"
                                        @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                        autocomplete="off"
                                        :placeholder="!$wire.departmentId ? '{{ __('app.select_dept_first_ph') }}' : (Object.keys($wire.users || {}).length === 0 ? '{{ __('app.no_users_dept_ph') }}' : '{{ __('app.select_user_ph') }}')"
                                        :disabled="!$wire.departmentId || Object.keys($wire.users || {}).length === 0"
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
                                <p x-show="open && items.length === 0 && search && $wire.departmentId" class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-card shadow-lg text-sm px-3.5 py-2.5 text-muted-foreground" style="display:none">
                                    {{ __('app.no_data') }}
                                </p>
                                <input type="hidden" wire:model="userId">
                            </div>
                            @error('userId') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-5">
                        @if ($direction === 'taken')
                            <div>
                                <label class="{{ $label }}">{{ __('app.sender_freetext') }}</label>
                                <input type="text" class="{{ $input }}" wire:model.defer="senderText" placeholder="{{ __('app.sender_placeholder') }}">
                                @error('senderText') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div>
                                <label class="{{ $label }}">{{ __('app.receiver_freetext') }}</label>
                                <input type="text" class="{{ $input }}" wire:model.defer="receiverText" placeholder="{{ __('app.receiver_placeholder') }}">
                                @error('receiverText') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </div>

                {{-- FOTO BUKTI --}}
                <div>
                    <label class="{{ $label }}">{{ __('app.photo_proof') }} <span class="text-muted-foreground/60 font-normal">({{ __('app.optional') }})</span></label>

                    <div class="space-y-3.5">
                        {{-- upload biasa (galeri / file explorer) --}}
                        <input
                            id="photo-input"
                            type="file"
                            class="{{ $input }} !h-auto py-2 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20"
                            wire:model="photo"
                            accept="image/*"
                            capture="environment"
                        >
                        @error('photo') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror

                        {{-- tombol khusus kamera (laptop / PC / HP) --}}
                        <button
                            type="button"
                            id="open-camera-btn"
                            class="inline-flex items-center gap-2 px-4 h-9 rounded-lg border border-border bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 transition">
                            <x-heroicon-o-camera class="w-4 h-4" />
                            <span>{{ __('app.take_from_camera') }}</span>
                        </button>

                        {{-- preview --}}
                        @if ($photo)
                            <div class="mt-3">
                                <p class="text-xs text-muted-foreground font-semibold mb-1.5">Preview bukti:</p>
                                <img src="{{ $photo->temporaryUrl() }}" class="w-40 h-40 object-cover rounded-2xl border border-border shadow-sm">
                            </div>
                        @endif

                        <p class="text-[11px] text-muted-foreground">
                            {{ __('app.take_from_camera') }}.
                        </p>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="pt-4 flex items-center border-t border-border bg-muted/5 -mx-6 -mb-6 p-6">
                    <button type="submit" class="{{ $btnBlk }}" wire:loading.attr="disabled" wire:target="save,photo">
                        <span wire:loading.remove wire:target="save,photo" class="inline-flex items-center gap-2">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span>{{ __('app.save') }}</span>
                        </span>
                        <span wire:loading wire:target="save,photo" class="inline-flex items-center gap-2">
                            <x-heroicon-o-arrow-path class="animate-spin h-4 w-4 text-white" />
                            <span>{{ __('app.saving') }}</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL KAMERA (untuk laptop/PC & HP) --}}
    <div id="camera-modal"
         wire:ignore   {{-- penting: Livewire jangan utak-atik modal ini --}}
         class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-lg w-full overflow-hidden flex flex-col">
            <div class="px-6 py-5 border-b border-gray-200 bg-[#4A2F24] text-[#CDDEA7] flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#CDDEA7]/10 flex items-center justify-center border border-[#CDDEA7]/20">
                        <x-heroicon-o-camera class="w-4 h-4 text-[#CDDEA7]" />
                    </div>
                    <span class="text-base font-bold tracking-tight">{{ __('app.camera_active') }}</span>
                </div>
                <button id="close-camera-btn" class="w-8 h-8 flex items-center justify-center rounded-lg text-[#CDDEA7] hover:text-white hover:bg-white/10 transition">
                    <x-heroicon-o-x-mark class="w-4.5 h-4.5" />
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-black/5 rounded-2xl border border-border overflow-hidden flex items-center justify-center">
                    <video id="camera-video" autoplay playsinline class="max-h-[60vh] rounded-xl"></video>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-xs text-muted-foreground">
                        Pastikan browser mengizinkan akses kamera (HTTPS / localhost).
                    </span>
                    <button id="capture-btn" class="inline-flex items-center gap-1.5 px-4 h-9 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm shrink-0">
                        <x-heroicon-o-camera class="w-4 h-4" />
                        <span>Ambil Foto</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            console.log('[DocPackForm] <script> tag evaluated');

            function initCameraScript() {
                console.log('[DocPackForm] initCameraScript called');

                let stream = null;

                const openBtn   = document.getElementById('open-camera-btn');
                const modal     = document.getElementById('camera-modal');
                const closeBtn  = document.getElementById('close-camera-btn');
                const video     = document.getElementById('camera-video');
                const captureBtn= document.getElementById('capture-btn');
                const fileInput = document.getElementById('photo-input');

                console.log('[DocPackForm] DOM lookup:', {
                    openBtn: !!openBtn,
                    modal: !!modal,
                    closeBtn: !!closeBtn,
                    video: !!video,
                    captureBtn: !!captureBtn,
                    fileInput: !!fileInput,
                });

                if (!openBtn || !modal || !video || !captureBtn || !fileInput || !closeBtn) {
                    console.warn('[DocPackForm] Some elements not found, aborting initCameraScript');
                    return;
                }

                async function openCamera() {
                    console.log('[DocPackForm] openCamera() called');

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        console.error('[DocPackForm] navigator.mediaDevices.getUserMedia NOT available');
                        alert('Browser tidak mendukung kamera (getUserMedia tidak tersedia).');
                        return;
                    }

                    try {
                        console.log('[DocPackForm] Requesting getUserMedia...');
                        stream = await navigator.mediaDevices.getUserMedia({ video: true });
                        console.log('[DocPackForm] getUserMedia SUCCESS, stream tracks:', stream.getTracks().length);
                        video.srcObject = stream;
                        video.play().then(() => {
                            console.log('[DocPackForm] video.play() resolved');
                        }).catch((e) => {
                            console.error('[DocPackForm] video.play() error:', e);
                        });
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        console.log('[DocPackForm] camera modal shown');
                    } catch (e) {
                        console.error('[DocPackForm] getUserMedia ERROR:', e);
                        alert('Gagal mengakses kamera. Cek izin browser & HTTPS / localhost.');
                    }
                }

                function closeCamera() {
                    console.log('[DocPackForm] closeCamera() called');
                    if (stream) {
                        console.log('[DocPackForm] stopping stream tracks');
                        stream.getTracks().forEach(t => t.stop());
                        stream = null;
                    }
                    video.srcObject = null;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }

                // Attach listeners
                openBtn.addEventListener('click', () => {
                    console.log('[DocPackForm] open-camera-btn CLICK');
                    openCamera();
                });

                closeBtn.addEventListener('click', () => {
                    console.log('[DocPackForm] close-camera-btn CLICK');
                    closeCamera();
                });

                captureBtn.addEventListener('click', () => {
                    console.log('[DocPackForm] capture-btn CLICK');
                    if (!stream) {
                        console.warn('[DocPackForm] No stream when capture clicked');
                        return;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width  = video.videoWidth  || 640;
                    canvas.height = video.videoHeight || 480;
                    console.log('[DocPackForm] capture canvas size:', canvas.width, canvas.height);

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            console.error('[DocPackForm] canvas.toBlob returned null blob');
                            return;
                        }

                        console.log('[DocPackForm] canvas.toBlob OK, size:', blob.size);

                        const file = new File([blob], 'camera-photo.png', { type: 'image/png' });
                        const dt = new DataTransfer();
                        dt.items.add(file);

                        fileInput.files = dt.files;
                        console.log('[DocPackForm] fileInput.files set from camera, dispatching change event');
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));

                        closeCamera();
                    }, 'image/png');
                });

                // Optional: log setiap Livewire re-render
                if (window.Livewire) {
                    Livewire.hook('message.processed', () => {
                        console.log('[DocPackForm] Livewire message.processed (poll re-render)');
                    });
                }
            }

            // Try to init on both events, for safety
            document.addEventListener('DOMContentLoaded', () => {
                console.log('[DocPackForm] DOMContentLoaded');
                initCameraScript();
            });

            document.addEventListener('livewire:load', () => {
                console.log('[DocPackForm] livewire:load');
                initCameraScript();
            });
        })();
    </script>
    @endpush
</div>
