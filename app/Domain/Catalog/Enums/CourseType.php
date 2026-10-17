<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum CourseType: string
{
    case LongTerm = 'long_term';
    case ShortTerm = 'short_term';

    public function label(): string
    {
        return match ($this) {
            self::LongTerm => 'Long Term (Diploma / Immersion)',
            self::ShortTerm => 'Short Term (Workshop / Intensive)',
        };
    }
}
