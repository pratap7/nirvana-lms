<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class TamperedAmountException extends RuntimeException
{
    public static function mismatch(int $expectedMinor, int $actualMinor, string $currency): self
    {
        return new self("Payment amount tampered or mismatched: expected [{$expectedMinor} {$currency}], received [{$actualMinor} {$currency}].");
    }
}
