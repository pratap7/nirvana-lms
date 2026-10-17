<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class WebhookVerificationException extends RuntimeException
{
    public static function invalidSignature(string $gateway): self
    {
        return new self("Webhook signature verification failed for [{$gateway}].");
    }

    public static function missingHeader(string $header): self
    {
        return new self("Required webhook signature header [{$header}] was not provided.");
    }
}
