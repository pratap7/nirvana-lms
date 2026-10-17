<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum CourseTeacherRole: string
{
    case Primary = 'primary';
    case Assistant = 'assistant';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Lead Teacher / Acharya',
            self::Assistant => 'Assistant Teacher',
            self::Guest => 'Guest Teacher',
        };
    }
}
