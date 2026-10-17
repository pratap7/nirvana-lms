<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\PhoneOtp;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Validation\ValidationException;

class VerifyPhoneOtp extends BaseAction
{
    /**
     * Verify OTP and return the associated user if verified.
     */
    public function execute(string $phone, string $otp): ?User
    {
        $normalizedPhone = preg_replace('/[^\d+]/', '', trim($phone));

        /** @var PhoneOtp|null $record */
        $record = PhoneOtp::query()
            ->where('phone', $normalizedPhone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP. Please request a new one.',
            ]);
        }

        if ($record->attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => 'Maximum verification attempts exceeded. Please request a new code.',
            ]);
        }

        if (! $record->verifyOtp($otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Incorrect verification code. Please try again.',
            ]);
        }

        // Return user with matching phone if exists
        /** @var User|null $user */
        $user = User::where('phone', $normalizedPhone)->first();

        return $user;
    }
}
