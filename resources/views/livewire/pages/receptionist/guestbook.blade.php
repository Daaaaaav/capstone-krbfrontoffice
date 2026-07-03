<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-white rounded-2xl border border-gray-200 shadow-sm';
        $head   = 'bg-[#4A2F24]';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-[#4E653D] text-white hover:bg-[#354C2B] transition shadow-sm focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60';
    @endphp

    <style>
      :root { color-scheme: light; }
      select, option {
        color: #111827 !important;
        background: #ffffff !important;
      }
      option:checked { background: #f3f4f6 !important; color: #111827 !important; }
    </style>

    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.guestbook_title') }}"
            subtitle="{{ __('app.guestbook_subtitle') }}" />

        {{-- Form Entri Baru --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50/50 rounded-t-2xl flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center border border-[#4E653D]/20">
                    <x-heroicon-o-plus class="w-4.5 h-4.5 text-[#4E653D]" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('app.add_new_entry') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">{{ __('app.add_entry_subtitle') }}</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-6">
                {{-- Auto-recorded fields badge row --}}
                <div class="flex flex-wrap items-center gap-2.5 text-xs">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#4E653D]/10 border border-[#4E653D]/25 text-[#4E653D] font-semibold shadow-sm">
                        <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                        {{ __('app.auto_date') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#4E653D]/10 border border-[#4E653D]/25 text-[#4E653D] font-semibold shadow-sm">
                        <x-heroicon-o-clock class="w-3.5 h-3.5" />
                        {{ __('app.auto_time_in') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-100 border border-gray-200 text-gray-600 font-semibold shadow-sm">
                        <x-heroicon-o-user class="w-3.5 h-3.5" />
                        {{ __('app.officer') }}: {{ auth()->user()->full_name ?? auth()->user()->name ?? 'Receptionist' }}
                    </span>
                </div>

                {{-- Grid Form Tamu --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="{{ $label }}">{{ __('app.full_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="name" placeholder="{{ __('app.full_name_placeholder') }}" class="{{ $input }}">
                        @error('name') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.guest_email') }} <span class="text-rose-500">*</span></label>
                        <input type="email" wire:model.defer="email" placeholder="{{ __('app.guest_email_placeholder') }}" class="{{ $input }}">
                        @error('email') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[10px] text-gray-400 font-medium leading-tight">
                            {{ __('app.guest_email_hint') }}
                        </p>
                    </div>

                    <div>
                        <label class="{{ $label }}">Jumlah Pengunjung <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model.defer="visitor_count" min="1" max="999" placeholder="1" class="{{ $input }}">
                        @error('visitor_count') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[10px] text-gray-400 font-medium leading-tight">
                            Jumlah orang dalam rombongan. Setiap pengunjung akan mendapat QR code unik.
                        </p>
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.phone') }}</label>
                        <input type="text" wire:model.defer="phone_number" placeholder="{{ __('app.phone_placeholder') }}" class="{{ $input }}">
                        @error('phone_number') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.institution') }}</label>
                        <input type="text" wire:model.defer="instansi" placeholder="{{ __('app.institution_placeholder') }}" class="{{ $input }}">
                        @error('instansi') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.visit_purpose') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="keperluan" placeholder="{{ __('app.visit_purpose_placeholder') }}" class="{{ $input }}">
                        @error('keperluan') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">Tempat Penyimpanan (1-100)</label>
                        <input type="number" wire:model.defer="storage_place" min="1" max="100" placeholder="Contoh: 12" class="{{ $input }}">
                        @error('storage_place') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[10px] text-gray-400 font-medium leading-tight">
                            Nomor laci atau slot penyimpanan KTP (opsional).
                        </p>
                    </div>

                    {{-- Departemen yang Dituju --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ __('app.target_department_opt') }}
                        </label>
                        <div
                            wire:ignore
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: $wire.department_id,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = @js($departments_list);
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.name.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const list = @js($departments_list);
                                    const found = list.find(i => i.id == $wire.department_id);
                                    return found ? found.name : '';
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
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('$wire.department_id', val => {
                                    this.selectedId = val || null;
                                    search = selectedLabel;
                                });
                            "
                            @guestbook-form-reset.window="clear()"
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
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].name)"
                                    autocomplete="off"
                                    placeholder="{{ __('app.select_department_opt') }}"
                                    class="{{ $input }} pr-8"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                    <button
                                        x-show="search"
                                        type="button"
                                        @click.stop="clear()"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
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
                                        @click="select(item.id, item.name)"
                                        :class="selectedId == item.id
                                            ? 'bg-[#4E653D] text-white'
                                            : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.name"
                                    ></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-500" style="display:none">
                                {{ __('app.no_data') }}
                            </p>

                            <input type="hidden" wire:model="department_id">
                        </div>
                        @error('department_id') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Bertemu dengan --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ __('app.meet_with_opt') }}
                        </label>
                        <div
                            wire:ignore
                            x-data="{
                                open: false,
                                search: '',
                                selectedId: $wire.user_id,
                                usersList: [],
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = this.usersList;
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.full_name.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const found = this.usersList.find(i => i.id == $wire.user_id);
                                    return found ? found.full_name : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.selectedId = id;
                                    $wire.set('user_id', id);
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.selectedId = null;
                                    $wire.set('user_id', null);
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('$wire.department_id', () => { search = ''; selectedId = null; usersList = []; });
                                $watch('$wire.user_id', val => {
                                    selectedId = val || null;
                                    search = selectedLabel;
                                });
                            "
                            @users-list-updated.window="usersList = $event.detail.users; search = ''; selectedId = null;"
                            @guestbook-form-reset.window="usersList = []; search = ''; selectedId = null; open = false;"
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
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].full_name)"
                                    autocomplete="off"
                                    placeholder="{{ __('app.select_employee') }}"
                                    :disabled="!$wire.department_id"
                                    class="{{ $input }} pr-8 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2.5">
                                    <button
                                        x-show="search"
                                        type="button"
                                        @click.stop="clear()"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
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
                                        @click="select(item.id, item.full_name)"
                                        :class="selectedId == item.id
                                            ? 'bg-[#4E653D] text-white'
                                            : 'text-gray-800 hover:bg-gray-100 cursor-pointer'"
                                        class="px-3.5 py-2.5 cursor-pointer transition-colors"
                                        x-text="item.full_name"
                                    ></li>
                                </template>
                            </ul>
                            <p x-show="open && items.length === 0 && search && $wire.department_id" class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg text-sm px-3.5 py-2.5 text-gray-500" style="display:none">
                                {{ __('app.no_users_dept') }}
                            </p>

                            <input type="hidden" wire:model="user_id">
                        </div>
                        @error('user_id') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-5 border-t border-gray-200 bg-gray-50/50 -mx-6 -mb-6 p-6 rounded-b-2xl flex items-center justify-end gap-3">
                    @if (session('saved'))
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-emerald-600 text-xs font-bold shadow-sm">
                            <x-heroicon-o-check class="w-3.5 h-3.5 font-bold" />
                            <span>{{ __('app.data_saved') }}</span>
                        </span>
                    @endif

                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="{{ $btnBlk }}">
                        <span class="flex items-center gap-2" wire:loading.remove wire:target="save">
                            <x-heroicon-o-check class="w-4 h-4" />
                            <span>{{ __('app.save_data') }}</span>
                        </span>
                        <span class="flex items-center gap-2 animate-pulse" wire:loading wire:target="save">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __('app.saving_data') }}</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>