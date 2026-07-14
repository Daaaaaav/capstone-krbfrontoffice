<div class="min-h-screen flex">
    @if(config('services.system.captcha_enabled'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    <div class="relative hidden md:block flex-1">
        <img src="{{ asset('images/login.jpg') }}" alt="Background"
            class="absolute inset-0 w-full h-full object-cover object-center select-none" draggable="false" />
        <div class="absolute inset-0 bg-black/20"></div>
    </div>

    <div class="flex-1 bg-white flex items-center justify-center px-8">
        <div class="w-full max-w-md">
            <div class="text-center mb-12">
                <img src="{{ asset('https://tiketkebunraya.id/assets/images/kebun-raya.png') }}" alt="Kebun Raya"
                    class="mx-auto mb-6 h-20 hover:scale-105 transition-transform duration-300" />
                <h2 class="text-gray-800 text-lg font-light tracking-wide mb-2">KEBUN RAYA</h2>
                <p class="text-gray-500 text-sm font-medium tracking-wider">
                    {{ $showOtpInput ? __('app.verify_otp_title') : __('app.welcome_login') }}
                </p>
                <div class="mt-4 w-16 h-0.5 bg-gray-800 mx-auto"></div>
            </div>

            {{-- Flash Messages --}}
            @if (session()->has('message'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                    {{ session('message') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if (!$showOtpInput)
                {{-- LOGIN FORM --}}
                <form x-data="{
                    showPassword: false,
                    submitLogin() {
                        const captchaEnabled = @js(config('services.system.captcha_enabled'));
                        const token = (captchaEnabled && typeof grecaptcha !== 'undefined')
                            ? grecaptcha.getResponse()
                            : '';
                        this.$wire.login(token);
                    }
                }" x-on:submit.prevent="submitLogin()" class="space-y-8">
                    <div class="group">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-3">{{ __('app.email_address') }}</label>
                        <input type="email" id="email" wire:model.defer="email" autocomplete="email"
                            class="w-full px-0 py-3 text-gray-900 placeholder-gray-400 border-0 border-b-2 border-gray-200 bg-transparent focus:outline-none focus:border-gray-800 focus:ring-0 transition-all duration-300 group-hover:border-gray-400"
                            placeholder="{{ __('app.enter_email') }}">
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="group">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-3">{{ __('app.password') }}</label>
                        <div class="relative">
                            <input x-bind:type="showPassword ? 'text' : 'password'" id="password"
                                wire:model.defer="password" autocomplete="current-password"
                                class="w-full px-0 py-3 pr-10 text-gray-900 placeholder-gray-400 border-0 border-b-2 border-gray-200 bg-transparent focus:outline-none focus:border-gray-800 focus:ring-0 transition-all duration-300 group-hover:border-gray-400"
                                placeholder="{{ __('app.enter_password') }}">
                            <button type="button" x-on:click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center text-gray-400 hover:text-gray-800 transition-colors duration-200">
                                <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600 select-none cursor-pointer">
                            <input type="checkbox" wire:model.defer="remember"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary bg-transparent">
                            <span>{{ __('app.remember_me') }}</span>
                        </label>

                        <a href="#" class="text-gray-500 hover:text-primary transition-colors duration-200 text-sm font-medium">
                            {{ __('app.forgot_password') }}
                        </a>
                    </div>

                    @if(config('services.system.captcha_enabled'))
                    <div class="mt-6" id="recaptcha-container">
                        <div wire:ignore>
                            <div class="g-recaptcha"
                                data-sitekey="{{ config('services.recaptcha.site_key') }}"
                                data-callback="onCaptchaSuccess"
                                data-expired-callback="onCaptchaExpired">
                            </div>
                        </div>
                        @error('captcha')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <button type="submit"
                        class="w-full rounded-3xl mt-6 bg-primary text-primary-foreground py-4 px-6 font-medium tracking-wide hover:bg-primary/95 shadow-lg shadow-primary/15 hover:shadow-xl hover:shadow-primary/20 focus:outline-none focus:ring-4 focus:ring-primary/30 transform hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="login"> {{ __('app.sign_in') }} </span>
                        <span wire:loading wire:target="login"> {{ __('app.processing') }} </span>
                    </button>
                </form>
            @else
                {{-- OTP VERIFICATION FORM --}}
                <form wire:submit.prevent="verifyOtp" class="space-y-8"
                    x-data="{
                        seconds: {{ $otpExpiresIn }},
                        timer: null,
                        expired: false,
                        get minutes() { return Math.floor(this.seconds / 60); },
                        get secs() { return String(this.seconds % 60).padStart(2, '0'); },
                        startTimer() {
                            clearInterval(this.timer);
                            this.expired = false;
                            this.timer = setInterval(() => {
                                if (this.seconds > 0) {
                                    this.seconds--;
                                } else {
                                    this.expired = true;
                                    clearInterval(this.timer);
                                }
                            }, 1000);
                        }
                    }"
                    x-init="startTimer()"
                    x-on:otp-resent.window="seconds = {{ $otpExpiresIn }}; startTimer()">

                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-primary/10 rounded-full mb-4">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-600">
                            {{ __('app.otp_sent_to') }}<br>
                            <span class="font-semibold text-gray-900">{{ $email }}</span>
                        </p>
                    </div>

                    <div class="group">
                        <label for="otpCode" class="block text-sm font-medium text-gray-700 mb-3">{{ __('app.enter_otp') }}</label>
                        <input type="text" id="otpCode" wire:model.defer="otpCode" maxlength="6" 
                            inputmode="numeric" pattern="[0-9]*"
                            class="w-full px-0 py-3 text-center text-2xl tracking-widest text-gray-900 placeholder-gray-400 border-0 border-b-2 border-gray-200 bg-transparent focus:outline-none focus:border-primary focus:ring-0 transition-all duration-300 group-hover:border-gray-400"
                            placeholder="000000" autofocus>
                        @error('otpCode')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="text-center text-sm text-gray-600">
                        <template x-if="!expired">
                            <span>
                                {{ __('app.otp_expires_in') }}
                                <span class="font-semibold text-gray-900" x-text="`${minutes}:${secs}`"></span>
                            </span>
                        </template>
                        <template x-if="expired">
                            <span class="font-semibold text-red-600">{{ __('app.otp_expired') }}</span>
                        </template>
                    </div>

                    <button type="submit"
                        class="w-full rounded-3xl bg-primary text-primary-foreground py-4 px-6 font-medium tracking-wide hover:bg-primary/95 shadow-lg shadow-primary/15 hover:shadow-xl hover:shadow-primary/20 focus:outline-none focus:ring-4 focus:ring-primary/30 transform hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled" :disabled="expired">
                        <span wire:loading.remove wire:target="verifyOtp"> {{ __('app.verify_otp') }} </span>
                        <span wire:loading wire:target="verifyOtp"> {{ __('app.verifying') }} </span>
                    </button>

                    <div class="flex items-center justify-between text-sm">
                        <button type="button" wire:click="resendOtp"
                            class="text-gray-600 hover:text-gray-900 font-medium transition-colors duration-200"
                            wire:loading.attr="disabled">
                            {{ __('app.resend_code') }}
                        </button>

                        <button type="button" wire:click="cancelOtp"
                            class="text-gray-600 hover:text-gray-900 font-medium transition-colors duration-200">
                            {{ __('app.back_to_login') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

@if(config('services.system.captcha_enabled'))
<script>
    // Must be global and defined before reCAPTCHA renders
    window.onCaptchaSuccess = function(token) {
        // Token is read via grecaptcha.getResponse() on submit.
    };

    window.onCaptchaExpired = function() {
        // Reset handled server-side on next submission attempt.
    };
</script>
@endif

@script
<script>
    Livewire.on('captcha-error', () => {
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.reset();
        }
    });
</script>
@endscript