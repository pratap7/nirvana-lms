<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum SessionStatus: string
{
    use HasEnumHelpers;

    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress (Live)',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::Rescheduled => 'gray',
        };
    }
}
