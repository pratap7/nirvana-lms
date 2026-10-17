<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

enum SubstituteRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
