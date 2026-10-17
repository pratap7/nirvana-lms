<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Exceptions\RefundPolicyViolationException;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Refund;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProcessRefundAction extends BaseAction
{
    public function __construct(
        protected PaymentGateway $gateway
    ) {}

    /**
     * Evaluate policy engine, initiate gateway refund, and create ledger entry.
     *
     * @throws RefundPolicyViolationException
     */
    public function execute(
        Payment $payment,
        string $reason,
        User $actor,
        ?int $requestedAmountMinor = null
    ): Refund {
        return DB::transaction(function () use ($payment, $reason, $actor, $requestedAmountMinor): Refund {
            $invoice = $payment->invoice;
            $batch = $invoice->batch ?? $invoice->enrollment?->batch;
            $startDate = $batch !== null ? $batch->start_date : $invoice->course?->start_date;

            $now = CarbonImmutable::now();

            // 1. Policy Engine Evaluation
            $percentageAllowed = 100;
            if ($startDate !== null) {
                $startCarbon = CarbonImmutable::parse($startDate)->startOfDay();
                if ($now->isBefore($startCarbon)) {
                    // 100% refund before batch/course commences
                    $percentageAllowed = 100;
                } else {
                    $daysSinceStart = (int) $startCarbon->diffInDays($now);
                    if ($daysSinceStart <= 7) {
                        // 50% refund within week one
                        $percentageAllowed = 50;
                    } else {
                        throw RefundPolicyViolationException::pastAllowedWindow(
                            "Course commenced {$daysSinceStart} days ago. Policy permits refunds only up to 7 days from start."
                        );
                    }
                }
            } else {
                // Self-paced or un-scheduled: 14 days cooling-off window
                $daysSinceIssued = (int) CarbonImmutable::parse($invoice->issued_at)->diffInDays($now);
                if ($daysSinceIssued > 14) {
                    throw RefundPolicyViolationException::pastAllowedWindow(
                        "Refund request exceeded the 14-day window for self-paced courses ({$daysSinceIssued} days)."
                    );
                }
                $percentageAllowed = 100;
            }

            // 2. Calculate Maximum Eligible Refundable Amount
            $maxRefundable = (int) round(($payment->amount * $percentageAllowed) / 100);
            $priorRefunded = (int) $payment->refunds()->where('status', 'processed')->sum('amount');
            $remainingRefundable = max(0, $maxRefundable - $priorRefunded);

            if ($remainingRefundable <= 0) {
                throw RefundPolicyViolationException::exceedsRefundable(
                    $requestedAmountMinor ?? 0,
                    0,
                    $payment->currency
                );
            }

            $amountToRefund = $requestedAmountMinor !== null ? $requestedAmountMinor : $remainingRefundable;

            if ($amountToRefund > $remainingRefundable) {
                throw RefundPolicyViolationException::exceedsRefundable(
                    $amountToRefund,
                    $remainingRefundable,
                    $payment->currency
                );
            }

            // 3. Gateway refund call if payment was online
            $gatewayRefundId = null;
            if ($payment->gateway === 'stripe' || $payment->gateway_transaction_id !== null) {
                $refundDetails = $this->gateway->refund($payment, $amountToRefund, $reason);
                $gatewayRefundId = $refundDetails->refundId;
            }

            // 4. Create Ledger Record
            $refund = Refund::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'student_id' => $invoice->student_id,
                'amount' => $amountToRefund,
                'currency' => $payment->currency,
                'gateway_refund_id' => $gatewayRefundId,
                'reason' => $reason,
                'status' => 'processed',
                'processed_at' => now(),
                'processed_by' => $actor->id,
            ]);

            // 5. Derived invoice status recalculation
            $invoice->recalculateStatus();

            return $refund->fresh(['invoice', 'payment', 'processor']);
        });
    }
}
