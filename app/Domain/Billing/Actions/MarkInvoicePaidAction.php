<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Events\InvoicePaid;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class MarkInvoicePaidAction extends BaseAction
{
    /**
     * Recalculate invoice ledger status and transition linked enrollment if fully paid.
     */
    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->refresh();
            $status = $invoice->recalculateStatus();

            // If the invoice is now Paid, check if enrollment should be activated
            if ($status === InvoicePaymentStatus::Paid) {
                if ($invoice->enrollment_id !== null && $invoice->enrollment !== null) {
                    $enrollment = $invoice->enrollment;
                    if ($enrollment->status === EnrollmentStatus::Approved || $enrollment->status === EnrollmentStatus::PaymentPending) {
                        TransitionEnrollmentAction::run(
                            $enrollment,
                            EnrollmentStatus::Active,
                            actor: null,
                            reason: "Invoice [{$invoice->invoice_number}] fully settled."
                        );
                    }
                }

                InvoicePaid::dispatch($invoice);
            }

            return $invoice->fresh();
        });
    }
}
