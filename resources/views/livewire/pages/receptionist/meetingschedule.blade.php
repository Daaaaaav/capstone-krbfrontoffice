<div class="min-h-screen bg-background">
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-xl overflow-visible';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';

        $otherId = $otherRequirementId ?? null;
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
            title="{{ __('app.meeting_schedule_title') }}"
            subtitle="{{ __('app.meeting_schedule_sub') }}" />

        {{-- MAIN LAYOUT: LEFT (FORM) + RIGHT (SIDEBAR) --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            {{-- LEFT: FORM CARDS --}}
            <div class="lg:col-span-3 space-y-6">

                {{-- FORM: BOOKING ROOM (OFFLINE) --}}
                <section class="{{ $card }}">
            <button type="button" wire:click="$toggle('showOfflineForm')"
                class="w-full flex items-center justify-between px-6 py-5 border-b border-border bg-muted/10 hover:bg-muted/20 transition text-left focus:outline-none">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                        <x-heroicon-o-calendar class="w-4.5 h-4.5 text-primary" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">{{ __('app.add_booking_offline') }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.add_booking_offline_sub') }}</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 {{ $showOfflineForm ? 'rotate-180' : '' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            @if($showOfflineForm)
                <form class="p-6 space-y-6" wire:submit.prevent="saveOffline">
                    {{-- Rules & Notes Alert --}}
                    <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-400 shadow-sm">
                        <div class="flex items-center gap-2 mb-2.5">
                            <x-heroicon-o-information-circle class="w-5 h-5 shrink-0" />
                            <h4 class="text-sm font-bold tracking-wide">Rules & Notes</h4>
                        </div>
                        <ul class="list-disc list-outside text-xs space-y-1.5 ml-5 text-blue-600/90 dark:text-blue-300/90 leading-relaxed">
                            <li><strong>Minimum Booking Limit:</strong> Bookings must be made at least 1 hour in advance.</li>
                            <li><strong>Maximum Booking Limit:</strong> You can only book a room up to 1 month in advance from today's date.</li>
                            <li><strong>Auto-Approval System:</strong> Bookings will be automatically approved once the scheduled time arrives.</li>
                            <li><strong>Auto-Archive System:</strong> Completed meetings will be automatically moved to the history logs immediately after the scheduled duration ends.</li>
                        </ul>
                    </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="md:col-span-3">
                        <label class="{{ $label }}">{{ __('app.title_col') }}</label>
                        <input type="text" wire:model.defer="form.meeting_title" class="{{ $input }}" placeholder="{{ __('app.title_col') }}">
                        @error('form.meeting_title') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Room Combobox --}}
                    {{-- Room Combobox --}}
                    <div>
                        <label class="{{ $label }}">{{ __('app.room') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                roomId: @entangle('form.room_id'),
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = @js(collect($rooms)->map(fn($r) => ['id' => $r['id'], 'label' => $r['name']])->values()->toArray());
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const list = @js(collect($rooms)->map(fn($r) => ['id' => $r['id'], 'label' => $r['name']])->values()->toArray());
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
                                <input
                                    type="text"
                                    x-model="search"
                                    @focus="open = true"
                                    @input="open = true"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                    autocomplete="off"
                                    placeholder="{{ __('app.select_room') }}"
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
                                        :class="roomId == item.id
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
                            <input type="hidden" wire:model="form.room_id">
                        </div>
                        @error('form.room_id') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Department Combobox (OFFLINE) --}}
                    <div>
                        <label class="{{ $label }}">{{ __('app.dept_label') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                deptId: @entangle('form.department_id').live,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                    const found = list.find(i => i.id == this.deptId);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.deptId = id;
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.deptId = null;
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('deptId', val => {
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
                                    placeholder="{{ __('app.search_dept_offline_ph') }}"
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
                                        :class="deptId == item.id
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
                            <input type="hidden" wire:model="form.department_id">
                        </div>
                        @error('form.department_id') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- User Combobox (OFFLINE, filtered by department) --}}
                    <div>
                        <label class="{{ $label }}">{{ __('app.user_filtered_dept') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                deptId: @entangle('form.department_id').live,
                                userId: @entangle('offline_user_id').live,
                                users: @entangle('usersByDeptOffline').live,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = (this.users || []).map(u => ({ id: u.id, label: u.name }));
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return q ? list.filter(i => i.label.toLowerCase().includes(q)) : list;
                                },
                                get selectedLabel() {
                                    const list = (this.users || []).map(u => ({ id: u.id, label: u.name }));
                                    const found = list.find(i => i.id == this.userId);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.userId = id;
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.userId = null;
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('deptId', () => { search = ''; userId = null; });
                                $watch('userId', val => {
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
                                    @focus="if (deptId) open = true"
                                    @input="if (deptId) open = true"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                    autocomplete="off"
                                    placeholder="{{ __('app.search_user_offline_ph') }}"
                                    :disabled="!deptId"
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
                                        :class="userId == item.id
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
                            <input type="hidden" wire:model="offline_user_id">
                        </div>
                        @error('offline_user_id') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.date_field') }}</label>
                        <input type="date" wire:model.defer="form.date" class="{{ $input }}">
                        @error('form.date') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.participants_label') }}</label>
                        <input type="number" min="1" wire:model.defer="form.participant" class="{{ $input }}">
                        @error('form.participant') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.start_label') }}</label>
                        <input type="time" wire:model.defer="form.time" class="{{ $input }}">
                        @error('form.time') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.end_label') }}</label>
                        <input type="time" wire:model.defer="form.time_end" class="{{ $input }}">
                        @error('form.time_end') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-3">
                        <label class="{{ $label }}">{{ __('app.room_requirements_label') }}</label>
                        @php
                            $reqNameMap = [
                                'projector & screen' => __('app.req_projector_screen'),
                                'whiteboard'         => __('app.req_whiteboard'),
                                'coffee break'       => __('app.req_coffee_break'),
                                'lunch set'          => __('app.req_lunch_set'),
                                'sound system'       => __('app.req_sound_system'),
                                'other'              => __('app.req_other'),
                            ];
                        @endphp
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 bg-muted/20 border border-border rounded-2xl p-4">
                            @foreach ($requirementOptions as $opt)
                                @if ($opt['id'] !== $otherId)
                                    @php $reqLabel = $reqNameMap[strtolower($opt['name'])] ?? $opt['name']; @endphp
                                    <label class="flex items-center space-x-2.5 cursor-pointer group" wire:key="req-{{ $opt['id'] }}">
                                        <input type="checkbox" value="{{ $opt['id'] }}" wire:model.live="form.requirements" class="w-4 h-4 rounded border-input text-primary focus:ring-primary/20 bg-background transition-all">
                                        <span class="text-xs text-foreground group-hover:text-primary transition-colors">{{ $reqLabel }}</span>
                                    </label>
                                @endif
                            @endforeach
                            <label class="flex items-center space-x-2.5 cursor-pointer group">
                                <input type="checkbox" wire:model.live="form.requirements" value="Other"
                                    class="w-4 h-4 rounded border-input text-primary focus:ring-primary/20 bg-background transition-all">
                                <span class="text-xs text-foreground group-hover:text-primary transition-colors">{{ __('app.req_other') }}</span>
                            </label>
                        </div>
                        @error('form.requirements.*') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Conditional display: Show notes if the string 'Other' is in the requirements array --}}
                @if (in_array('Other', $form['requirements'] ?? [], true))
                    <div class="mt-4 bg-primary/5 border border-primary/20 rounded-2xl p-5">
                        <label class="{{ $label }}">{{ __('app.special_notes') }}</label>
                        <textarea wire:model.defer="form.notes" rows="3" placeholder="{{ __('app.special_notes') }}..."
                            class="w-full px-3.5 py-2.5 border border-input rounded-lg bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"></textarea>
                        @error('form.notes') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                @endif
                


                <div class="pt-5 border-t border-border bg-muted/5 -mx-6 -mb-6 p-6 flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 {{ $btnBlk }}" wire:loading.attr="disabled" wire:target="saveOffline">
                        <div wire:loading.remove wire:target="saveOffline">
                            <x-heroicon-o-check class="w-4 h-4" />
                        </div>
                        <div wire:loading wire:target="saveOffline">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <span>{{ __('app.save_booking') }}</span>
                    </button>
                </div>
            </form>
            @endif
        </section>

        {{-- FORM: ONLINE MEETING --}}
        <section class="{{ $card }}">
            <button type="button" wire:click="$toggle('showOnlineForm')"
                class="w-full flex items-center justify-between px-6 py-5 border-b border-border bg-muted/10 hover:bg-muted/20 transition text-left focus:outline-none">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                        <x-heroicon-o-video-camera class="w-4.5 h-4.5 text-primary" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">{{ __('app.create_online_meeting') }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('app.create_online_sub') }}</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 {{ $showOnlineForm ? 'rotate-180' : '' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            @if($showOnlineForm)
                <form class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6" wire:submit.prevent="saveOnline">
                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">{{ __('app.title_col') }}</label>
                        <input type="text" wire:model.defer="online_meeting_title" class="{{ $input }}" placeholder="{{ __('app.title_col') }}">
                        @error('online_meeting_title') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col justify-end">
                                <label class="{{ $label }}">Platform</label>
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selectedId: $wire.entangle('online_platform').live,
                                        options: [
                                            { id: 'google_meet', label: 'Google Meet' },
                                            { id: 'zoom', label: 'Zoom' }
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
                                            this.selectedId = 'google_meet';
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
                                        <input type="text" x-model="search" @focus="open = true" @input="open = true" @keydown.escape="open = false" @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)" autocomplete="off" placeholder="Platform" class="{{ $input }} pr-8">
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
                                @error('online_platform') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                            </div>
                        <div class="flex items-end">
                            @if($online_platform === 'google_meet')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold {{ $googleConnected ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-500' : 'bg-yellow-500/10 border border-yellow-500/20 text-yellow-500' }}">
                                    {{ $googleConnected ? __('app.google_connected') : __('app.google_not_connected') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Department Combobox (ONLINE) --}}
                    <div>
                        <label class="{{ $label }}">{{ __('app.dept_label') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                deptId: @entangle('online_department_id').live,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return list.filter(i => !q || i.label.toLowerCase().includes(q));
                                },
                                get selectedLabel() {
                                    const list = @js(collect($departments)->map(fn($d) => ['id' => $d['id'], 'label' => $d['name']])->values()->toArray());
                                    const found = list.find(i => i.id == this.deptId);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.deptId = id;
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.deptId = null;
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('deptId', val => {
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
                                        :class="deptId == item.id
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
                            <input type="hidden" wire:model="online_department_id">
                        </div>
                        @error('online_department_id') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- User Combobox (ONLINE, filtered by department) --}}
                    <div>
                        <label class="{{ $label }}">{{ __('app.user_filtered_optional') }}</label>
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                deptId: @entangle('online_department_id').live,
                                userId: @entangle('online_user_id').live,
                                users: @entangle('usersByDept').live,
                                get items() {
                                    const q = (this.search || '').toLowerCase().trim();
                                    const list = (this.users || []).map(u => ({ id: u.id, label: u.name }));
                                    if (q === (this.selectedLabel || '').toLowerCase().trim()) return list;
                                    return q ? list.filter(i => i.label.toLowerCase().includes(q)) : list;
                                },
                                get selectedLabel() {
                                    const list = (this.users || []).map(u => ({ id: u.id, label: u.name }));
                                    const found = list.find(i => i.id == this.userId);
                                    return found ? found.label : '';
                                },
                                select(id, label) {
                                    this.search = label;
                                    this.userId = id;
                                    this.open = false;
                                },
                                clear() {
                                    this.search = '';
                                    this.userId = null;
                                }
                            }"
                            x-init="
                                search = selectedLabel;
                                $watch('deptId', () => { search = ''; userId = null; });
                                $watch('userId', val => {
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
                                    @focus="if (deptId) open = true"
                                    @input="if (deptId) open = true"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="items.length === 1 && select(items[0].id, items[0].label)"
                                    autocomplete="off"
                                    placeholder="{{ __('app.search_user_online_ph') }}"
                                    :disabled="!deptId"
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
                                        :class="userId == item.id
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
                            <input type="hidden" wire:model="online_user_id">
                        </div>
                        @error('online_user_id') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-4 flex flex-col justify-between">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="{{ $label }}">{{ __('app.date') }}</label>
                            <input type="date" wire:model.defer="online_date" class="{{ $input }}">
                            @error('online_date') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('app.start') }}</label>
                            <input type="time" wire:model.defer="online_start_time" class="{{ $input }}">
                            @error('online_start_time') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('app.end') }}</label>
                            <input type="time" wire:model.defer="online_end_time" class="{{ $input }}">
                            @error('online_end_time') <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>



                    <div class="pt-5 flex items-center justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 {{ $btnBlk }}" wire:loading.attr="disabled" wire:target="saveOnline">
                            <div wire:loading.remove wire:target="saveOnline">
                                <x-heroicon-o-link class="w-4 h-4" />
                            </div>
                            <div wire:loading wire:target="saveOnline">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <span>{{ __('app.submit_online') }}</span>
                        </button>
                    </div>
                </div>
            </form>
            @endif
            </section>
            </div>

            {{-- RIGHT: SIDEBAR (DESKTOP) --}}
            <aside class="hidden lg:flex lg:flex-col lg:col-span-1 gap-4">
                {{-- Available Rooms Widget --}}
                <div class="bg-card rounded-2xl border border-border shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-border bg-muted/30 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Room Directory</h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5">Available meeting rooms</p>
                        </div>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse($rooms ?? [] as $room)
                            <div wire:click="openScheduleModal({{ $room['id'] }})" class="flex items-start gap-3 p-2 rounded-lg hover:bg-muted/50 transition cursor-pointer">
                                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-semibold shrink-0">
                                    <x-heroicon-o-building-office-2 class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $room['name'] }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">Meeting Room</p>
                                </div>
                            </div>

                        @empty
                            <div class="text-center py-6">
                                <x-heroicon-o-building-office-2 class="w-8 h-8 mx-auto text-muted-foreground/30 mb-2"/>
                                <p class="text-xs text-muted-foreground">No rooms available.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </main>

    {{-- Room Schedule Modal --}}
    @if($showScheduleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-data="{ show: @entangle('showScheduleModal') }" x-show="show" x-cloak>
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
                        <h3 class="text-base font-semibold text-foreground">Room Schedule</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">30-Day Timeline</p>
                    </div>
                    <button @click="show = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition">
                        ✕
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    @if(empty($roomScheduleData))
                        <div class="text-center py-8">
                            <x-heroicon-o-calendar-days class="w-10 h-10 mx-auto text-muted-foreground/30 mb-3"/>
                            <h4 class="text-sm font-medium text-foreground">No bookings</h4>
                            <p class="text-xs text-muted-foreground mt-1">This room is fully available for the next 30 days.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                            @foreach($roomScheduleData as $booking)
                                <div wire:click="openBookingDetail({{ $booking['id'] }})" class="cursor-pointer p-3 rounded-xl border border-border bg-muted/5 shadow-sm hover:border-primary/30 transition-colors">
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="font-bold text-foreground text-sm leading-tight break-words">{{ $booking['title'] }}</div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase bg-primary/10 text-primary whitespace-nowrap">
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
                
                <div class="px-6 py-4 border-b border-border bg-muted/5 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-foreground leading-tight">{{ $selectedBookingDetail['room_name'] }}</h3>
                        <p class="text-sm text-muted-foreground mt-1">Room Booking Detail</p>
                    </div>
                    <button @click="showDetail = false" class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded-md hover:bg-muted/10">
                        ✕
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Room</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['room_name'] }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Status</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase bg-primary/10 text-primary">
                                {{ $selectedBookingDetail['status'] }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Borrower</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['borrower'] }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1">Department</p>
                            <p class="text-sm font-semibold text-foreground">{{ $selectedBookingDetail['department'] }}</p>
                        </div>
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