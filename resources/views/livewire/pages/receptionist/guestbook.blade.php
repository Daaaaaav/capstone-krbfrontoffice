<div class="min-h-screen bg-gray-50">
    @php
        $card   = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
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

        {{-- Hero Header --}}
        <div class="relative overflow-hidden rounded-2xl {{ $head }} text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex items-center gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold">{{ __('app.guestbook_title') }}</h2>
                        <p class="text-xs text-[#CDDEA7]/80">{{ __('app.guestbook_subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAIN LAYOUT: LEFT (FORM) + RIGHT (SIDEBAR) --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

            {{-- LEFT: FORM CARD --}}
            <div class="{{ $card }} lg:col-span-3">
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
                    <div class="relative">
                        <label class="{{ $label }}">{{ __('app.full_name') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.live.debounce.300ms="name" placeholder="{{ __('app.full_name_placeholder') }}" class="{{ $input }}" autocomplete="off">
                        @if(!empty($historyGuests))
                            <ul class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg text-sm">
                                @foreach($historyGuests as $index => $guest)
                                    <li wire:click="selectHistoryGuest({{ $index }})"
                                        class="px-3.5 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                        <div class="font-medium text-gray-900">{{ $guest['name'] }}</div>
                                        <div class="text-[10px] text-gray-500">{{ $guest['instansi'] ?? 'No Instansi' }} - {{ $guest['phone_number'] ?? 'No Phone' }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('name') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div class="space-y-1.5">
                        <label class="{{ $label }}">{{ __('app.email') }} <span class="text-rose-500">*</span></label>
                        <input type="email" wire:model.lazy="email" placeholder="{{ __('app.guest_email_placeholder') ?? 'guest@email.com' }}" class="{{ $input }}">
                        @error('email') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        @if($isAutoFilled ?? false)
                            <p class="mt-1 text-[11px] text-amber-600 font-medium leading-tight bg-amber-50 p-1.5 rounded border border-amber-200 inline-block w-full">
                                <x-heroicon-o-information-circle class="w-3.5 h-3.5 inline mr-0.5" />
                                Is the email still the same or has it changed? You can overwrite it.
                            </p>
                        @endif
                    </div>

                    {{-- Visitor Count --}}
                    <div class="space-y-1.5">
                        <label class="{{ $label }}">{{ __('app.visitor_count') }} <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="visitor_count" min="1" max="50" placeholder="1" class="{{ $input }}">
                        @error('visitor_count') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
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
                        <label class="{{ $label }}">{{ __('app.storage_place') ?? 'Storage / Locker' }}</label>
                        <input type="number" wire:model.defer="storage_place" min="1" max="100" placeholder="e.g. 12" class="{{ $input }}">
                        @error('storage_place') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.visit_purpose') }} <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="keperluan" placeholder="{{ __('app.visit_purpose_placeholder') }}" class="{{ $input }}">
                        @error('keperluan') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Departemen yang Dituju --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ __('app.target_department_opt') }}
                        </label>
                        <div class="relative">
                            <select wire:model.live="department_id" class="{{ $input }} appearance-none pr-8">
                                <option value="">{{ __('app.select_department_opt') }}</option>
                                @foreach($departments_list as $dept)
                                    <option value="{{ $dept['id'] }}">{{ $dept['name'] }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-gray-500">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                            </div>
                        </div>
                        @error('department_id') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Bertemu dengan --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ __('app.meet_with_opt') }}
                        </label>
                        <div class="relative">
                            <select wire:model.defer="user_id"
                                    class="{{ $input }} appearance-none pr-8 disabled:bg-gray-100 disabled:text-gray-400"
                                    @if(empty($users_list) && $department_id) disabled @endif>
                                <option value="">{{ __('app.select_employee') }}</option>
                                @foreach($users_list as $user)
                                    <option value="{{ $user['id'] }}">{{ $user['full_name'] }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-gray-500">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                            </div>
                        </div>
                        @if(empty($users_list) && $department_id)
                            <p class="mt-1.5 text-xs text-amber-600 font-semibold">{{ __('app.no_users_dept') }}</p>
                        @endif
                        @error('user_id') <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-5 border-t border-gray-200 bg-gray-50/50 -mx-6 -mb-6 p-6 flex items-center justify-end gap-3">
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

            {{-- RIGHT: SIDEBAR (DESKTOP) --}}
            <aside class="hidden lg:flex lg:flex-col lg:col-span-1 gap-4">
                {{-- Guest History Widget --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-3.5 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900">Guest Directory</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Quick select previous guests</p>
                        </div>
                        <a href="{{ route('receptionist.guestbookhistory') }}" class="text-xs font-medium text-[#4E653D] hover:underline">View All</a>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse(array_slice($historyGuests ?? [], 0, 5) as $index => $guest)
                            <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition" wire:click="selectHistoryGuest({{ $index }})">
                                <div class="w-8 h-8 rounded-full bg-[#4E653D]/10 text-[#4E653D] flex items-center justify-center text-xs font-semibold shrink-0">
                                    {{ strtoupper(substr($guest['name'], 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $guest['name'] }}</p>
                                    <p class="text-[11px] text-gray-500 truncate">{{ $guest['instansi'] ?? 'Personal' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <x-heroicon-o-users class="w-8 h-8 mx-auto text-gray-300 mb-2"/>
                                <p class="text-xs text-gray-500">No guest history available.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>