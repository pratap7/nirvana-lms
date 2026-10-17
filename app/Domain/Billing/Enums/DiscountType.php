<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum DiscountType: string
{
    use HasEnumHelpers;

    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Discount (%)',
            self::Fixed => 'Fixed Amount Off',
        };
    }
}
