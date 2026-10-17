<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Gateways\DTOs\GatewayOrder;
use App\Domain\Billing\Gateways\DTOs\GatewayPaymentDetails;
use App\Domain\Billing\Gateways\DTOs\GatewayRefundDetails;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;

interface PaymentGateway
{
    /**
     * Get the unique identifier code for this payment gateway (e.g. 'stripe').
     */
    public function getIdentifier(): string;

    /**
     * Create an order/checkout session with the gateway for the given invoice.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrder(Invoice $invoice, array $options = []): GatewayOrder;

    /**
     * Verify incoming webhook signature.
     */
    public function verifySignature(string $payload, string $signature, ?string $secret = null): bool;

    /**
     * Fetch payment details from the gateway by transaction/intent ID.
     */
    public function fetchPayment(string $gatewayTransactionId): GatewayPaymentDetails;

    /**
     * Process a refund via the gateway.
     */
    public function refund(Payment $payment, int $amountMinor, string $reason): GatewayRefundDetails;
}
