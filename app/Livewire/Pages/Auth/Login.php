<?php

namespace App\Livewire\Pages\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Services\CaptchaService;
use App\Services\OtpService;
use App\Services\SecurityMonitoringService;
use App\Models\User;

#[Layout('layouts.auth')]
#[Title('Login')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;
    public string $captcha = '';

    public string $otpCode = '';
    public bool $otpSent = false;
    public bool $showOtpInput = false;
    public int $otpExpiresIn = 0;

    protected function isOtpEnabled(): bool
    {
        return (bool) config('services.system.otp_enabled', false);
    }

    protected function isCaptchaEnabled(): bool
    {
        return (bool) config('services.system.captcha_enabled', false);
    }

    public function mount()
    {
        if (Auth::check()) {
            $role = Auth::user()->role->name ?? Auth::user()->role ?? null;
            if (in_array($role, ['Manager', 'Receptionist'])) {
                return redirect()->route('home');
            }
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    protected function rules(): array
    {
        if ($this->showOtpInput) {
            return [
                'otpCode' => ['required', 'string', 'size:6'],
            ];
        }

        $rules = [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];

        if ($this->isCaptchaEnabled()) {
            $rules['captcha'] = ['required'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'captcha.required' => 'Please complete the captcha verification.',
        ];
    }

    public function login(string $captchaToken = '')
    {
        SecurityMonitoringService::inspectLoginPayload($this->email, $this->password);

        Log::info('LOGIN_ATTEMPT', [
            'ip' => request()->ip(),
            'email' => $this->email,
        ]);

        if ($this->isCaptchaEnabled() && $captchaToken !== '') {
            $this->captcha = $captchaToken;
        }

        $this->validate();

        $key = 'login:' . Str::lower($this->email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if ($this->isCaptchaEnabled() && !CaptchaService::verify($this->captcha, request()->ip())) {
            Log::warning('LOGIN_FAILED', [
                'ip' => request()->ip(),
                'email' => $this->email,
                'reason' => 'captcha_failed',
            ]);
            $this->dispatch('captcha-error');
            $this->captcha = '';
            throw ValidationException::withMessages([
                'captcha' => 'Captcha verification failed. Please try again.',
            ]);
        }

        try {
            $user = User::where('email', Str::lower($this->email))->first();

            if (!$user || !\Hash::check($this->password, $user->password)) {
                RateLimiter::hit($key, 60);
                if ($this->isCaptchaEnabled()) {
                    $this->dispatch('captcha-error');
                }
                $this->captcha = '';
                Log::warning('LOGIN_FAILED', [
                    'ip' => request()->ip(),
                    'email' => $this->email,
                    'reason' => 'invalid_credentials',
                ]);
                throw ValidationException::withMessages([
                    'email' => 'These credentials do not match our records.',
                ]);
            }

            if (!$this->isOtpEnabled()) {
                if (Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
                    RateLimiter::clear($key);
                    request()->session()->regenerate();
                    request()->session()->forget('url.intended');

                    Log::info('LOGIN_SUCCESS', [
                        'ip' => request()->ip(),
                        'email' => $this->email,
                        'user_id' => Auth::id(),
                    ]);

                    return redirect()->route('home');
                }

                throw ValidationException::withMessages([
                    'email' => 'Authentication failed. Please try again.',
                ]);
            }

            // Credentials are valid, send OTP
            $otpService = new OtpService();
            $result = $otpService->generateAndSend($this->email);

            if (!$result['success']) {
                throw ValidationException::withMessages([
                    'email' => $result['message'],
                ]);
            }

            $this->showOtpInput = true;
            $this->otpSent = true;
            $this->otpExpiresIn = 300; 

            session()->flash('message', 'OTP code sent to your email. Please check your inbox.');

            Log::info('LOGIN_OTP_SENT', [
                'ip' => request()->ip(),
                'email' => $this->email,
            ]);
        } catch (QueryException $exception) {
            Log::error('LOGIN_DB_ERROR', [
                'ip' => request()->ip(),
                'email' => $this->email,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'The database is temporarily unavailable. Please try again in a moment.',
            ]);
        }
    }

    public function verifyOtp()
    {
        if (!$this->isOtpEnabled()) {
            return;
        }

        $this->validate();

        $otpService = new OtpService();
        $result = $otpService->verify($this->email, $this->otpCode);

        if (!$result['success']) {
            $this->otpCode = '';
            throw ValidationException::withMessages([
                'otpCode' => $result['message'],
            ]);
        }

        if (Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
            $key = 'login:' . Str::lower($this->email) . '|' . request()->ip();
            RateLimiter::clear($key);
            request()->session()->regenerate();
            request()->session()->forget('url.intended');

            Log::info('LOGIN_SUCCESS', [
                'ip' => request()->ip(),
                'email' => $this->email,
                'user_id' => Auth::id(),
                'otp' => true,
            ]);

            return redirect()->route('home');
        }

        throw ValidationException::withMessages([
            'otpCode' => 'OTP authentication failed. Please try again.',
        ]);
    }

    public function resendOtp()
    {
        if (!$this->isOtpEnabled()) {
            return;
        }

        $otpService = new OtpService();
        $result = $otpService->generateAndSend($this->email);

        if ($result['success']) {
            $this->otpExpiresIn = 300;
            $this->dispatch('otp-resent');
            session()->flash('message', 'New OTP code sent to your email.');
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function cancelOtp()
    {
        $this->reset(['showOtpInput', 'otpSent', 'otpCode', 'otpExpiresIn', 'captcha']);
        if ($this->isCaptchaEnabled()) {
            $this->dispatch('captcha-error');
        }
    }

    public function render()
    {
        return view('livewire.pages.auth.login');
    }
}
