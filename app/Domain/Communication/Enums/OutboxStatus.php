<?php

declare(strict_types=1);

namespace App\Domain\Communication\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum OutboxStatus: string
{
    use HasEnumHelpers;

    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Delivered = 'delivered';
    case HeldForQuietHours = 'held_for_quiet_hours';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued for Delivery',
            self::Sent => 'Sent to Provider',
            self::Failed => 'Failed Delivery',
            self::Delivered => 'Delivered',
            self::HeldForQuietHours => 'Held for Quiet Hours',
        };
    }
}
