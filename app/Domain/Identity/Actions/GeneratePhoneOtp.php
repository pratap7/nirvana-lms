<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\PhoneOtp;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GeneratePhoneOtp extends BaseAction
{
    /**
     * Generate a 6-digit OTP for phone authentication with 5-minute validity.
     */
    public function execute(string $phone): string
    {
        $normalizedPhone = preg_replace('/[^\d+]/', '', trim($phone));

        // Rate limiting: allow max 3 requests per 10 minutes per phone number
        $recentRequests = PhoneOtp::query()
            ->where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentRequests >= 3) {
            throw ValidationException::withMessages([
                'phone' => 'Too many OTP requests. Please wait a few minutes before trying again.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        PhoneOtp::create([
            'phone' => $normalizedPhone,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'created_at' => now(),
        ]);

        return $code;
    }
}
