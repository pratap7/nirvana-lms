<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum ScholarshipStatus: string
{
    use HasEnumHelpers;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
