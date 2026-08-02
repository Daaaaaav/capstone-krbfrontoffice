<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function generateAndSend(string $email): array
    {
        $otpCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        OtpVerification::where('email', $email)->delete();
        $otp = OtpVerification::create([
            'email' => $email,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
            'is_verified' => false,
        ]);

        try {
            Mail::raw(
                "Your OTP code is: {$otpCode}\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this code, please ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your Login OTP Code');
                }
            );

            return [
                'success' => true,
                'message' => 'OTP sent successfully to your email',
            ];
        } catch (\Exception $e) {
            Log::error('OTP Email Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ];
        }
    }

    public function verify(string $email, string $code): array
    {
        $otp = OtpVerification::where('email', $email)
            ->where('is_verified', false)
            ->latest()
            ->first();

        if (!$otp) {
            return [
                'success' => false,
                'message' => 'No OTP found. Please request a new one.',
            ];
        }

        if ($otp->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ];
        }

        if (!$otp->isValid($code)) {
            return [
                'success' => false,
                'message' => 'Invalid OTP code. Please try again.',
            ];
        }

        $otp->update(['is_verified' => true]);

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
        ];
    }

    public function hasValidOtp(string $email): bool
    {
        return OtpVerification::where('email', $email)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->exists();
    }
}
