<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class MakerCheckerViolationException extends RuntimeException
{
    public static function sameUser(int $userId): self
    {
        return new self("Maker-checker violation: User [{$userId}] cannot verify a payment that they recorded.");
    }

    public static function missingPermission(int $userId): self
    {
        return new self("Maker-checker violation: User [{$userId}] does not possess the required 'payment.verify' permission.");
    }
}
