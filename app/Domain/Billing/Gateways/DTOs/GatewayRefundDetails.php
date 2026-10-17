<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways\DTOs;

use App\Support\Money;

final readonly class GatewayRefundDetails
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $refundId,
        public string $paymentId,
        public Money $amount,
        public string $status = 'succeeded',
        public array $rawPayload = [],
    ) {}
}
