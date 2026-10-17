<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Support\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * Poll endpoint for invoice status.
     * NEVER activates enrollment from client redirect; can perform secure server-side fetch if session_id provided.
     */
    public function status(
        string $invoiceUlid,
        Request $request,
        PaymentGateway $gateway
    ): JsonResponse {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()->where('ulid', $invoiceUlid)->first();
        if ($invoice === null) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        // Optional server-side fetch if webhook was delayed and session_id is provided
        $sessionId = $request->query('session_id');
        if ($sessionId && $invoice->status !== InvoicePaymentStatus::Paid) {
            try {
                $details = $gateway->fetchPayment((string) $sessionId);
                if ($details->status === 'succeeded' && $details->amount->getAmount() >= $invoice->total_amount) {
                    $existing = Payment::query()->where('gateway_transaction_id', $details->transactionId)->first();
                    if ($existing === null) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'student_id' => $invoice->student_id,
                            'amount' => $details->amount->getAmount(),
                            'currency' => $details->amount->getCurrency(),
                            'method' => PaymentMethod::Stripe,
                            'gateway' => 'stripe',
                            'gateway_transaction_id' => $details->transactionId,
                            'gateway_order_id' => (string) $sessionId,
                            'status' => PaymentStatus::Verified,
                            'verified_at' => now(),
                            'payload' => $details->rawPayload,
                        ]);

                        MarkInvoicePaidAction::run($invoice);
                        $invoice->refresh();
                    }
                }
            } catch (\Throwable) {
                // Ignore fetch errors during polling
            }
        }

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status->value,
            'is_paid' => $invoice->status === InvoicePaymentStatus::Paid,
            'total_amount' => $invoice->getTotal()->getFormatted(),
            'paid_amount' => $invoice->getPaidAmount()->getFormatted(),
            'remaining_balance' => $invoice->getRemainingBalance()->getFormatted(),
            'enrollment_status' => $invoice->enrollment?->status?->value,
        ]);
    }

    /**
     * View or print GST Tax Invoice with institute branding and SAC code.
     */
    public function viewInvoice(string $invoiceUlid, SettingsService $settings): View
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->with(['items', 'student', 'enrollment.batch', 'course', 'payments.verifier'])
            ->where('ulid', $invoiceUlid)
            ->firstOrFail();

        return view('billing.invoice', [
            'invoice' => $invoice,
            'settings' => $settings,
        ]);
    }
}
