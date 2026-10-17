<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property string $phone
 * @property string $otp_hash
 * @property CarbonInterface $expires_at
 * @property int $attempts
 * @property ?CarbonInterface $verified_at
 * @property ?CarbonInterface $created_at
 */
class PhoneOtp extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'phone',
        'otp_hash',
        'expires_at',
        'attempts',
        'verified_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verifyOtp(string $otp): bool
    {
        if ($this->isExpired() || $this->isVerified()) {
            return false;
        }

        $this->increment('attempts');

        if (Hash::check($otp, $this->otp_hash)) {
            $this->update(['verified_at' => now()]);

            return true;
        }

        return false;
    }
}
