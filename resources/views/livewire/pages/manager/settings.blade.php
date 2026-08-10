<div>
    <main class="max-w-3xl mx-auto py-8 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.settings_title') }}"
            subtitle="{{ __('app.settings_manage_sub') }}" />

        {{-- ===== TAB SWITCHER ===== --}}
        <div class="flex gap-1 bg-[#e8ede0] rounded-xl p-1 w-fit">
            <button wire:click="$set('activeTab', 'profile')"
                class="px-5 py-2 text-sm font-medium rounded-lg transition
                    {{ $activeTab === 'profile'
                        ? 'bg-white text-[#2d3a24] shadow-sm'
                        : 'text-[#7a8f6a] hover:text-[#2d3a24]' }}">
                Profile
            </button>
            <button wire:click="$set('activeTab', 'ai')"
                class="px-5 py-2 text-sm font-medium rounded-lg transition
                    {{ $activeTab === 'ai'
                        ? 'bg-white text-[#2d3a24] shadow-sm'
                        : 'text-[#7a8f6a] hover:text-[#2d3a24]' }}">
                AI Model
            </button>
            <button wire:click="$set('activeTab', 'integrations')"
                class="px-5 py-2 text-sm font-medium rounded-lg transition
                    {{ $activeTab === 'integrations'
                        ? 'bg-white text-[#2d3a24] shadow-sm'
                        : 'text-[#7a8f6a] hover:text-[#2d3a24]' }}">
                Integrations
            </button>
        </div>

        {{-- ================================================================ --}}
        {{-- PROFILE TAB                                                       --}}
        {{-- ================================================================ --}}
        @if($activeTab === 'profile')

            {{-- Alerts --}}
            @if($successMessage)
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $successMessage }}
                </div>
            @endif
            @if($errorMessage)
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M12 5a7 7 0 100 14A7 7 0 0012 5z" />
                    </svg>
                    {{ $errorMessage }}
                </div>
            @endif

            {{-- Profile Card --}}
            <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e4edd8] bg-[#f0f4eb]">
                    <h2 class="text-base font-semibold text-[#2d3a24]">{{ __('app.profile_information') }}</h2>
                    <p class="text-xs text-[#7a8f6a] mt-0.5">{{ __('app.profile_info_sub') }}</p>
                </div>

                <form wire:submit.prevent="updateProfile" class="px-6 py-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.full_name_label') }}</label>
                        <input type="text" wire:model="name" data-validate="text"
                            class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                   focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition"
                            placeholder="{{ __('app.full_name_ph') }}">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.email_address') }}</label>
                        <input type="email" wire:model="email" data-validate="email"
                            class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                   focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition"
                            placeholder="you@example.com">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.phone_optional_label') }}</label>
                        <input type="text" wire:model="phone" data-validate="phone"
                            class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                   focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition"
                            placeholder="e.g. +62-812-3456-7890">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="px-6 py-2.5 bg-[#4A2F24] text-white text-sm font-medium rounded-xl
                                   hover:bg-[#3d2720] transition shadow-sm">
                            {{ __('app.save_profile') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Password Card --}}
            <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                <button wire:click="$toggle('showPasswordSection')"
                    class="w-full flex items-center justify-between px-6 py-4 border-b border-[#e4edd8] bg-[#f0f4eb]
                           hover:bg-[#eef1e8] transition text-left">
                    <div>
                        <h2 class="text-base font-semibold text-[#2d3a24]">{{ __('app.change_password') }}</h2>
                        <p class="text-xs text-[#7a8f6a] mt-0.5">{{ __('app.change_password_sub') }}</p>
                    </div>
                    <svg class="w-5 h-5 text-[#9aaa8a] transition-transform {{ $showPasswordSection ? 'rotate-180' : '' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                @if($showPasswordSection)
                    <form wire:submit.prevent="updatePassword" class="px-6 py-6 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.current_password') }}</label>
                            <input type="password" wire:model="currentPassword"
                                class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                       focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                            @error('currentPassword') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.new_password') }}</label>
                            <input type="password" wire:model="newPassword"
                                class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                       focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                            @error('newPassword') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#4E653D] mb-1">{{ __('app.confirm_new_password') }}</label>
                            <input type="password" wire:model="confirmPassword"
                                class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                       focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                            @error('confirmPassword') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="pt-2 flex gap-3">
                            <button type="submit"
                                class="px-6 py-2.5 bg-[#4A2F24] text-white text-sm font-medium rounded-xl
                                       hover:bg-[#3d2720] transition shadow-sm">
                                {{ __('app.update_password') }}
                            </button>
                            <button type="button" wire:click="$set('showPasswordSection', false)"
                                class="px-6 py-2.5 bg-[#eef1e8] text-[#4E653D] text-sm font-medium rounded-xl
                                       hover:bg-[#dde4d4] transition">
                                {{ __('app.cancel') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        @endif {{-- end profile tab --}}


        {{-- ================================================================ --}}
        {{-- AI MODEL TAB                                                      --}}
        {{-- ================================================================ --}}
        @if($activeTab === 'ai')

            {{-- AI Alerts --}}
            @if($aiSuccess)
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $aiSuccess }}
                </div>
            @endif
            @if($aiError)
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M12 5a7 7 0 100 14A7 7 0 0012 5z" />
                    </svg>
                    {{ $aiError }}
                </div>
            @endif

            {{-- Info banner --}}
            <div class="flex gap-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 text-sm">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                </svg>
                <span>
                    These values replace all hardcoded parameters in the LSTM model, fallback forecasting,
                    and the decision engine. Changes take effect on the next prediction run.
                </span>
            </div>

            {{-- Group cards --}}
            @php
                $groupLabels = [
                    'booking' => ['Booking Behaviour',              'Controls validation rules applied to room bookings created by receptionists.'],
                    'lstm'     => ['LSTM Model Hyperparameters',   'Controls the neural network architecture and training behaviour.'],
                    'fallback' => ['Fallback Moving Average',       'Used when the LSTM service is unavailable.'],
                    'decision' => ['Decision Engine Thresholds',    'Risk scoring rules applied to booking requests.'],
                    'security' => ['Security & Spam Detection',     'Rate-limiting thresholds for form abuse detection.'],
                ];
            @endphp

            <form wire:submit.prevent="saveAISettings" class="space-y-6">

                @foreach($aiGrouped as $group => $fields)
                    @php [$groupTitle, $groupSub] = $groupLabels[$group] ?? [$group, '']; @endphp

                    <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-[#e4edd8] bg-[#f0f4eb]">
                            <h2 class="text-base font-semibold text-[#2d3a24]">{{ $groupTitle }}</h2>
                            @if($groupSub)
                                <p class="text-xs text-[#7a8f6a] mt-0.5">{{ $groupSub }}</p>
                            @endif
                        </div>

                        <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @foreach($fields as $key => $meta)
                                <div>
                                    <label class="block text-sm font-medium text-[#4E653D] mb-1">
                                        {{ $meta['label'] }}
                                    </label>

                                    @if($meta['type'] === 'bool')
                                        {{-- Toggle switch for boolean settings --}}
                                        <div class="flex items-center gap-3 mt-1">
                                            <button
                                                type="button"
                                                wire:click="$set('aiSettings.{{ $key }}', '{{ $aiSettings[$key] == '1' ? '0' : '1' }}')"
                                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#4E653D] focus:ring-offset-2 {{ $aiSettings[$key] == '1' ? 'bg-[#4E653D]' : 'bg-gray-200' }}"
                                                role="switch"
                                                aria-checked="{{ $aiSettings[$key] == '1' ? 'true' : 'false' }}"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $aiSettings[$key] == '1' ? 'translate-x-5' : 'translate-x-0' }}"
                                                ></span>
                                            </button>
                                            <span class="text-sm font-medium {{ $aiSettings[$key] == '1' ? 'text-[#4E653D]' : 'text-gray-400' }}">
                                                {{ $aiSettings[$key] == '1' ? 'ON' : 'OFF' }}
                                            </span>
                                        </div>
                                        {{-- Hidden input so Livewire model stays in sync --}}
                                        <input type="hidden" wire:model="aiSettings.{{ $key }}">
                                    @else
                                        <input
                                            type="number"
                                            step="{{ $meta['type'] === 'float' ? 'any' : '1' }}"
                                            wire:model.lazy="aiSettings.{{ $key }}"
                                            class="w-full px-4 py-2.5 border border-[#c4d4b4] rounded-xl text-[#2d3a24]
                                                   focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition text-sm"
                                        >
                                    @endif

                                    @error("aiSettings.{$key}")
                                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                    @if($meta['description'])
                                        <p class="text-xs text-[#9aaa8a] mt-1 leading-relaxed">{{ $meta['description'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Action buttons --}}
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        wire:loading.attr="disabled"
                        class="px-6 py-2.5 bg-[#4A2F24] text-white text-sm font-medium rounded-xl
                               hover:bg-[#3d2720] transition shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveAISettings">Save AI Settings</span>
                        <span wire:loading wire:target="saveAISettings">Saving…</span>
                    </button>

                    <button type="button"
                        wire:click="resetAISettings"
                        wire:confirm="Reset all AI settings to their original defaults?"
                        wire:loading.attr="disabled"
                        class="px-6 py-2.5 bg-[#eef1e8] text-[#4E653D] text-sm font-medium rounded-xl
                               hover:bg-[#dde4d4] transition disabled:opacity-60">
                        Reset to Defaults
                    </button>
                </div>

            </form>

        @endif {{-- end ai tab --}}


        {{-- ================================================================ --}}
        {{-- INTEGRATIONS TAB                                                  --}}
        {{-- ================================================================ --}}
        @if($activeTab === 'integrations')
            @inject('googleService', 'App\Services\GoogleMeetService')
            @php $isGoogleConnected = $googleService->isConnected(); @endphp

            {{-- Show any Google auth errors passed back via redirect --}}
            @if($errors->has('google'))
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14A7 7 0 0012 5z"/>
                    </svg>
                    <div>
                        <p class="font-semibold">Google Connection Error</p>
                        <p class="mt-0.5 text-xs">{{ $errors->first('google') }}</p>
                    </div>
                </div>
            @endif

            @if(session('success'))
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e4edd8] bg-[#f0f4eb]">
                    <h2 class="text-base font-semibold text-[#2d3a24]">Google Meet Integration</h2>
                    <p class="text-xs text-[#7a8f6a] mt-0.5">Connect a Google account to automatically generate Meet links for online bookings.</p>
                </div>
                
                <div class="px-6 py-6 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="p-3 {{ $isGoogleConnected ? 'bg-green-100 text-green-700' : 'bg-[#e8ede0] text-[#4E653D]' }} rounded-xl">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-medium text-[#2d3a24]">Google Calendar & Meet</h3>
                                @if($isGoogleConnected)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium bg-green-50 text-green-700 border border-green-200 rounded-lg">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        Connected
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200 rounded-lg">
                                        <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                        Not Connected
                                    </span>
                                @endif
                            </div>
                            
                            <p class="text-sm text-[#7a8f6a] mt-1 mb-4 leading-relaxed">
                                @if($isGoogleConnected)
                                    Your Google account is successfully connected. The booking system will automatically generate Google Meet links for all online meetings using this account in the background.
                                @else
                                    By connecting your Google account, the booking system will be able to automatically generate Google Meet links for online meetings. You only need to connect this once. The system will use this account in the background for all future bookings.
                                @endif
                            </p>
                            
                            <a href="{{ route('google.auth') }}"
                               class="inline-flex items-center gap-2 px-6 py-2.5 {{ $isGoogleConnected ? 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' : 'bg-[#4A2F24] text-white hover:bg-[#3d2720] shadow-sm' }} text-sm font-medium rounded-xl transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
                                </svg>
                                {{ $isGoogleConnected ? 'Reconnect Google Account' : 'Connect Google Meet' }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif {{-- end integrations tab --}}

    </main>
</div>
