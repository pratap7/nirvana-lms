<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Support\Concerns\HasEnumHelpers;

/**
 * Derived invoice payment statuses.
 * NEVER edited directly by hand; computed strictly from ledger payments and refunds.
 */
enum InvoicePaymentStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Payment Pending',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Fully Paid',
            self::Failed => 'Payment Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
