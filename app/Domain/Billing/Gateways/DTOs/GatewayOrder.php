<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways\DTOs;

use App\Support\Money;

final readonly class GatewayOrder
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $orderId,
        public string $checkoutUrl,
        public Money $amount,
        public ?string $clientSecret = null,
        public array $rawPayload = [],
    ) {}
}
