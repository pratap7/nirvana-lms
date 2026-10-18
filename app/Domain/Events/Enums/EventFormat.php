<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum EventFormat: string
{
    use HasEnumHelpers;

    case InPerson = 'in_person';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'In-Person',
            self::Online => 'Online / Live Stream',
            self::Hybrid => 'Hybrid (In-Person & Online)',
        };
    }

    public function schemaAttendanceMode(): string
    {
        return match ($this) {
            self::InPerson => 'https://schema.org/OfflineEventAttendanceMode',
            self::Online => 'https://schema.org/OnlineEventAttendanceMode',
            self::Hybrid => 'https://schema.org/MixedEventAttendanceMode',
        };
    }
}
