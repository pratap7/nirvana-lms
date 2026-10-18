<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum OccurrenceStatus: string
{
    use HasEnumHelpers;

    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }
}
