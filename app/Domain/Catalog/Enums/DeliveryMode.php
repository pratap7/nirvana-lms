<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum DeliveryMode: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online (Live & Self-Paced)',
            self::Offline => 'In-Person (Ashram / Campus)',
            self::Hybrid => 'Hybrid (Online + Campus Immersion)',
        };
    }
}
