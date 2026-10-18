<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum EventPricingType: string
{
    use HasEnumHelpers;

    case Free = 'free';
    case Paid = 'paid';
    case InvitationOnly = 'invitation_only';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free Admission',
            self::Paid => 'Paid Ticket',
            self::InvitationOnly => 'Invitation Only',
        };
    }
}
