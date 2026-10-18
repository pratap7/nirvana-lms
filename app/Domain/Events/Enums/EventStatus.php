<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum EventStatus: string
{
    use HasEnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function schemaEventStatus(): string
    {
        return match ($this) {
            self::Draft => 'https://schema.org/EventScheduled',
            self::Published => 'https://schema.org/EventScheduled',
            self::Cancelled => 'https://schema.org/EventCancelled',
            self::Completed => 'https://schema.org/EventCompleted',
        };
    }
}
