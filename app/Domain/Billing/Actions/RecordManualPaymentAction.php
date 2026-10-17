<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class RecordManualPaymentAction extends BaseAction
{
    /**
     * Record an offline/manual payment awaiting verification (Maker).
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Invoice $invoice,
        int $amountMinor,
        PaymentMethod $method,
        User $recorder,
        string $referenceNumber,
        ?string $proofPath = null,
        ?string $notes = null,
        array $payload = []
    ): Payment {
        return DB::transaction(function () use (
            $invoice,
            $amountMinor,
            $method,
            $recorder,
            $referenceNumber,
            $proofPath,
            $notes,
            $payload
        ): Payment {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'amount' => $amountMinor,
                'currency' => $invoice->currency,
                'method' => $method,
                'gateway' => 'manual',
                'status' => PaymentStatus::Pending,
                'reference_number' => $referenceNumber,
                'proof_path' => $proofPath,
                'recorded_by' => $recorder->id,
                'verification_notes' => $notes,
                'payload' => $payload,
            ]);

            // Note: invoice status is NOT updated here because only verified payments count!

            return $payment;
        });
    }
}
