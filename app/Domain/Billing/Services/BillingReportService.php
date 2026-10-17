<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\GatewayWebhookEvent;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Support\Money;
use App\Support\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BillingReportService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Get outstanding balance report across all pending / partially paid invoices.
     *
     * @return array{
     *     total_outstanding: Money,
     *     invoices_count: int,
     *     records: Collection<int, array<string, mixed>>
     * }
     */
    public function getOutstandingBalances(?CarbonInterface $asOf = null): array
    {
        $currency = (string) $this->settings->get('institute.currency', 'INR');
        $now = $asOf ? CarbonImmutable::parse($asOf) : CarbonImmutable::now();

        $invoices = Invoice::query()
            ->with(['student', 'payments'])
            ->whereIn('status', [InvoicePaymentStatus::Pending->value, InvoicePaymentStatus::PartiallyPaid->value])
            ->get();

        $totalOutstandingMinor = 0;
        /** @var Collection<int, array<string, mixed>> $records */
        $records = $invoices->map(function (Invoice $invoice) use (&$totalOutstandingMinor, $now): array {
            $remaining = $invoice->getRemainingBalance();
            $totalOutstandingMinor += $remaining->getAmount();

            $isOverdue = $now->isAfter($invoice->due_date);

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student_name' => $invoice->billing_name,
                'student_email' => $invoice->billing_email,
                'total_amount' => $invoice->getTotal()->getFormatted(),
                'paid_amount' => $invoice->getPaidAmount()->getFormatted(),
                'remaining_amount' => $remaining->getFormatted(),
                'remaining_minor' => $remaining->getAmount(),
                'status' => $invoice->status->value,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'is_overdue' => $isOverdue,
            ];
        });

        return [
            'total_outstanding' => Money::fromMinor($totalOutstandingMinor, $currency),
            'invoices_count' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Get daily collection aggregated by date and payment method.
     *
     * @return array{
     *     total_collected: Money,
     *     transactions_count: int,
     *     by_method: array<string, int>,
     *     daily_breakdown: array<string, int>
     * }
     */
    public function getDailyCollection(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $currency = (string) $this->settings->get('institute.currency', 'INR');
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Verified->value)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalCollectedMinor = 0;
        $byMethod = [];
        $dailyBreakdown = [];

        foreach ($payments as $payment) {
            $amount = $payment->amount;
            $totalCollectedMinor += $amount;

            $method = $payment->method->value;
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;

            $dateKey = $payment->created_at->format('Y-m-d');
            $dailyBreakdown[$dateKey] = ($dailyBreakdown[$dateKey] ?? 0) + $amount;
        }

        return [
            'total_collected' => Money::fromMinor($totalCollectedMinor, $currency),
            'transactions_count' => $payments->count(),
            'by_method' => $byMethod,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Reconcile verified ledger payments against gateway transactions/events.
     *
     * @return array{
     *     gateway: string,
     *     date: string,
     *     ledger_total: Money,
     *     gateway_total: Money,
     *     discrepancy: Money,
     *     is_reconciled: bool,
     *     ledger_count: int,
     *     event_count: int
     * }
     */
    public function reconcileGatewaySettlements(string $gateway, CarbonInterface $date): array
    {
        $currency = (string) $this->settings->get('institute.currency', 'INR');
        $start = CarbonImmutable::parse($date)->startOfDay();
        $end = CarbonImmutable::parse($date)->endOfDay();

        // 1. Ledger payments for this gateway
        $ledgerPayments = Payment::query()
            ->where('gateway', $gateway)
            ->where('status', PaymentStatus::Verified->value)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $ledgerTotalMinor = (int) $ledgerPayments->sum('amount');

        // 2. Verified gateway webhook events
        $events = GatewayWebhookEvent::query()
            ->where('gateway', $gateway)
            ->whereBetween('received_at', [$start, $end])
            ->whereNotNull('processed_at')
            ->get();

        $gatewayTotalMinor = 0;
        foreach ($events as $event) {
            $payload = $event->payload;
            /** @var array<string, mixed> $object */
            $object = (isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['object']) && is_array($payload['data']['object']))
                ? $payload['data']['object']
                : $payload;
            $amount = (int) ($object['amount_total'] ?? $object['amount'] ?? 0);
            $gatewayTotalMinor += $amount;
        }

        $discrepancyMinor = abs($ledgerTotalMinor - $gatewayTotalMinor);

        return [
            'gateway' => $gateway,
            'date' => $start->format('Y-m-d'),
            'ledger_total' => Money::fromMinor($ledgerTotalMinor, $currency),
            'gateway_total' => Money::fromMinor($gatewayTotalMinor, $currency),
            'discrepancy' => Money::fromMinor($discrepancyMinor, $currency),
            'is_reconciled' => $discrepancyMinor === 0,
            'ledger_count' => $ledgerPayments->count(),
            'event_count' => $events->count(),
        ];
    }
}
