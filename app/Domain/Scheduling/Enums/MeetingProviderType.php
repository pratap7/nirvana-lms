<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum MeetingProviderType: string
{
    use HasEnumHelpers;

    case None = 'none';
    case Zoom = 'zoom';
    case GoogleMeet = 'google_meet';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::None => 'In-Person / No Virtual Link',
            self::Zoom => 'Zoom Video Meeting',
            self::GoogleMeet => 'Google Meet',
            self::Manual => 'Manual Meeting Link',
        };
    }
}
