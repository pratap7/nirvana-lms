<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class RefundPolicyViolationException extends RuntimeException
{
    public static function pastAllowedWindow(string $reason): self
    {
        return new self("Refund denied by policy: {$reason}");
    }

    public static function exceedsRefundable(int $requestedMinor, int $refundableMinor, string $currency): self
    {
        return new self("Requested refund of [{$requestedMinor} {$currency}] exceeds maximum refundable amount of [{$refundableMinor} {$currency}].");
    }
}
