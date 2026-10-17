<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways\DTOs;

use App\Support\Money;

final readonly class GatewayPaymentDetails
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $transactionId,
        public Money $amount,
        public string $status,
        public string $method = 'stripe',
        public ?string $orderId = null,
        public array $rawPayload = [],
    ) {}
}
