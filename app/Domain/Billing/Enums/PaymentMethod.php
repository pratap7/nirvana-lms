<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum PaymentMethod: string
{
    use HasEnumHelpers;

    case Stripe = 'stripe';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Scholarship = 'scholarship';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe (Credit / Debit / NetBanking)',
            self::Upi => 'UPI (QR / VPA)',
            self::BankTransfer => 'Direct Bank Wire (NEFT / RTGS / IMPS)',
            self::Cash => 'Cash at Campus Counter',
            self::Scholarship => 'Institutional Scholarship / Grant',
            self::Credit => 'Referral Credit Balance',
        };
    }

    public function isManual(): bool
    {
        return in_array($this, [self::Upi, self::BankTransfer, self::Cash], true);
    }
}
