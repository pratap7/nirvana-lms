<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum PaymentStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Verified => 'Verified & Settled',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
