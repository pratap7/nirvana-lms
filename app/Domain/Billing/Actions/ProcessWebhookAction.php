<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\TamperedAmountException;
use App\Domain\Billing\Exceptions\WebhookVerificationException;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Models\GatewayWebhookEvent;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class ProcessWebhookAction extends BaseAction
{
    public function __construct(
        protected PaymentGateway $gateway
    ) {}

    /**
     * Verify signature, deduplicate on gateway_event_id, check for amount tampering,
     * record ledger entry, and trigger MarkInvoicePaidAction.
     *
     * @return array{status: string, event: GatewayWebhookEvent, payment: ?Payment}
     *
     * @throws WebhookVerificationException
     * @throws TamperedAmountException
     */
    public function execute(
        string $rawPayload,
        string $signature,
        string $gatewayName = 'stripe'
    ): array {
        // 1. Verify signature authenticity
        if (! $this->gateway->verifySignature($rawPayload, $signature)) {
            throw WebhookVerificationException::invalidSignature($gatewayName);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($rawPayload, true) ?? [];
        $eventId = (string) ($data['id'] ?? hash('sha256', $rawPayload));
        $eventType = (string) ($data['type'] ?? 'unknown');

        // 2. Atomic deduplication on gateway event ID (idempotency)
        return DB::transaction(function () use ($signature, $gatewayName, $eventId, $eventType, $data): array {
            /** @var GatewayWebhookEvent|null $existingEvent */
            $existingEvent = GatewayWebhookEvent::query()
                ->where('gateway', $gatewayName)
                ->where('gateway_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existingEvent !== null && $existingEvent->processed_at !== null) {
                // Replay detected! Return idempotent success without creating another payment
                return [
                    'status' => 'duplicate',
                    'event' => $existingEvent,
                    'payment' => null,
                ];
            }

            $webhookEvent = $existingEvent ?? GatewayWebhookEvent::create([
                'gateway' => $gatewayName,
                'gateway_event_id' => $eventId,
                'event_type' => $eventType,
                'signature' => $signature,
                'payload' => $data,
                'received_at' => now(),
            ]);

            $payment = null;

            // 3. Process checkout / payment events
            $targetEvents = ['checkout.session.completed', 'payment_intent.succeeded', 'charge.succeeded'];
            if (in_array($eventType, $targetEvents, true)) {
                /** @var array<string, mixed> $object */
                $object = $data['data']['object'] ?? $data;

                // Resolve invoice
                $invoiceUlid = $object['client_reference_id'] ?? $object['metadata']['invoice_ulid'] ?? null;
                $invoiceId = $object['metadata']['invoice_id'] ?? null;

                $invoice = null;
                if ($invoiceUlid !== null) {
                    $invoice = Invoice::query()->where('ulid', (string) $invoiceUlid)->first();
                }
                if ($invoice === null && $invoiceId !== null) {
                    $invoice = Invoice::query()->find((int) $invoiceId);
                }

                if ($invoice !== null) {
                    // 4. Verify Amount (Anti-Tampering)
                    $receivedAmountMinor = (int) ($object['amount_total'] ?? $object['amount'] ?? 0);
                    $expectedAmountMinor = $invoice->total_amount;

                    if ($receivedAmountMinor !== $expectedAmountMinor) {
                        throw TamperedAmountException::mismatch(
                            $expectedAmountMinor,
                            $receivedAmountMinor,
                            $invoice->currency
                        );
                    }

                    // 5. Ensure single payment transaction
                    $transactionId = (string) ($object['payment_intent'] ?? $object['id']);
                    /** @var Payment|null $existingPayment */
                    $existingPayment = Payment::query()
                        ->where('gateway_transaction_id', $transactionId)
                        ->first();

                    if ($existingPayment === null) {
                        $payment = Payment::create([
                            'invoice_id' => $invoice->id,
                            'student_id' => $invoice->student_id,
                            'amount' => $receivedAmountMinor,
                            'currency' => strtoupper((string) ($object['currency'] ?? $invoice->currency)),
                            'method' => PaymentMethod::Stripe,
                            'gateway' => $gatewayName,
                            'gateway_transaction_id' => $transactionId,
                            'gateway_order_id' => (string) ($object['id'] ?? null),
                            'status' => PaymentStatus::Verified,
                            'verified_at' => now(),
                            'payload' => $object,
                        ]);
                    } else {
                        $payment = $existingPayment;
                    }

                    // 6. Settle invoice and transition enrollment
                    MarkInvoicePaidAction::run($invoice);
                }
            }

            $webhookEvent->update(['processed_at' => now()]);

            return [
                'status' => 'processed',
                'event' => $webhookEvent,
                'payment' => $payment,
            ];
        });
    }
}
