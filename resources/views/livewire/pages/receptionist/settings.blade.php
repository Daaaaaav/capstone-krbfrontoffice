<div>
    @php
    $card         = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    $label        = 'block text-sm font-medium text-gray-700 mb-1.5';
    $input        = 'w-full h-10 px-3.5 rounded-lg border border-gray-300 text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-900 bg-white transition text-sm';
    $btnPrimary   = 'px-5 py-2.5 bg-[#4E653D] hover:bg-[#354C2B] text-white text-xs font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4E653D]/20 disabled:opacity-60 transition shadow-sm';
    $btnSecondary = 'px-5 py-2.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg border border-gray-200 hover:bg-gray-200 focus:outline-none transition';
    @endphp

    <main class="max-w-3xl mx-auto px-3 sm:px-6 py-3 sm:py-6 space-y-3 sm:space-y-6">

        {{-- HERO BANNER --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#CDDEA7]/10 rounded-xl flex items-center justify-center backdrop-blur-sm border border-[#CDDEA7]/20">
                            <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-[#CDDEA7]"/>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold text-[#CDDEA7]">{{ __('app.settings_title') }}</h2>
                            <p class="text-sm text-[#CDDEA7]/80 mt-1">
                                {{ __('app.settings_manage_sub') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        @if($successMessage)
            <div class="inline-flex items-center gap-2.5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold shadow-sm w-full">
                <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $successMessage }}</span>
            </div>
        @endif

        @if($errorMessage)
            <div class="inline-flex items-center gap-2.5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold shadow-sm w-full">
                <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01M12 5a7 7 0 100 14A7 7 0 0012 5z" />
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- Profile Card --}}
        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#4E653D]/10 flex items-center justify-center text-[#4E653D]">
                    <x-heroicon-o-user class="w-5 h-5 shrink-0" />
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('app.profile_information') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('app.profile_info_sub') }}</p>
                </div>
            </div>

            <form wire:submit.prevent="updateProfile" class="p-6 space-y-4">
                <div>
                    <label class="{{ $label }}">{{ __('app.full_name_label') }}</label>
                    <input type="text" wire:model="name" class="{{ $input }}" placeholder="{{ __('app.full_name_ph') }}">
                    @error('name') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('app.email_address') }}</label>
                    <input type="email" wire:model="email" class="{{ $input }}" placeholder="you@example.com">
                    @error('email') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">
                        {{ __('app.phone_optional_label') }}
                    </label>
                    <input type="text" wire:model="phone" class="{{ $input }}" placeholder="e.g. 08123456789">
                    @error('phone') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="{{ $btnPrimary }}">
                        {{ __('app.save_profile') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Password Card --}}
        <div class="{{ $card }}">
            <button wire:click="$toggle('showPasswordSection')"
                class="w-full flex items-center justify-between px-6 py-5 border-b border-gray-200 bg-gray-50 hover:bg-gray-100 transition text-left">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#4E653D]/10 flex items-center justify-center text-[#4E653D]">
                        <x-heroicon-o-key class="w-5 h-5 shrink-0" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('app.change_password') }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('app.change_password_sub') }}</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 {{ $showPasswordSection ? 'rotate-180' : '' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            @if($showPasswordSection)
                <form wire:submit.prevent="updatePassword" class="p-6 space-y-4">
                    <div>
                        <label class="{{ $label }}">{{ __('app.current_password') }}</label>
                        <input type="password" wire:model="currentPassword" class="{{ $input }}">
                        @error('currentPassword') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.new_password') }}</label>
                        <input type="password" wire:model="newPassword" class="{{ $input }}">
                        @error('newPassword') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $label }}">{{ __('app.confirm_new_password') }}</label>
                        <input type="password" wire:model="confirmPassword" class="{{ $input }}">
                        @error('confirmPassword') <p class="text-xs text-rose-600 mt-1.5 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit" class="{{ $btnPrimary }}">
                            {{ __('app.update_password') }}
                        </button>
                        <button type="button" wire:click="$set('showPasswordSection', false)" class="{{ $btnSecondary }}">
                            {{ __('app.cancel') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>

    </main>
</div>

