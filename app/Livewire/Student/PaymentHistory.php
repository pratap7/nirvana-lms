<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.student')]
class PaymentHistory extends Component
{
    public ?string $payingInstallmentUlid = null;

    public string $paymentMethod = 'stripe';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        //
    }

    public function selectInstallmentToPay(string $installmentUlid): void
    {
        $this->payingInstallmentUlid = $installmentUlid;
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function cancelPayment(): void
    {
        $this->payingInstallmentUlid = null;
    }

    public function processInstallmentPayment(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->payingInstallmentUlid) {
            return;
        }

        /** @var ?Installment $installment */
        $installment = Installment::query()
            ->where('ulid', $this->payingInstallmentUlid)
            ->whereHas('invoice', fn ($q) => $q->where('student_id', $user->id))
            ->first();

        if (! $installment) {
            $this->errorMessage = 'Installment not found or unauthorized.';

            return;
        }

        if ($installment->isPaid()) {
            $this->errorMessage = 'This installment is already settled.';
            $this->payingInstallmentUlid = null;

            return;
        }

        // Simulate gateway transaction and record verified payment
        $payment = Payment::query()->create([
            'invoice_id' => $installment->invoice_id,
            'student_id' => $user->id,
            'amount' => $installment->amount,
            'currency' => $installment->currency,
            'method' => PaymentMethod::from($this->paymentMethod === 'stripe' ? 'stripe' : 'upi'),
            'gateway' => $this->paymentMethod === 'stripe' ? 'stripe' : 'manual',
            'gateway_transaction_id' => 'TXN_'.strtoupper(bin2hex(random_bytes(6))),
            'status' => PaymentStatus::Verified,
            'reference_number' => 'REF-'.time(),
            'payload' => [
                'installment_ulid' => $installment->ulid,
                'installment_number' => $installment->installment_number,
                'gateway' => $this->paymentMethod,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $installment->update([
            'paid_at' => CarbonImmutable::now(),
            'is_overdue' => false,
        ]);

        // Recompute invoice payment status
        /** @var Invoice $invoice */
        $invoice = $installment->invoice;
        $invoice->recalculateStatus();

        $this->payingInstallmentUlid = null;
        $this->successMessage = 'Payment of '.$installment->getAmount()->format().' was processed successfully. Receipt generated.';
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->where('student_id', $user->id)
            ->with(['course', 'batch', 'items', 'installments' => fn ($q) => $q->orderBy('installment_number'), 'payments'])
            ->latest('issued_at')
            ->get();

        /** @var Collection<int, Payment> $payments */
        $payments = Payment::query()
            ->where('student_id', $user->id)
            ->with('invoice')
            ->latest()
            ->get();

        /** @var Collection<int, Certificate> $certificates */
        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->with(['course', 'batch'])
            ->latest('issue_date')
            ->get();

        $payingInstallment = $this->payingInstallmentUlid
            ? Installment::query()->where('ulid', $this->payingInstallmentUlid)->first()
            : null;

        return view('livewire.student.payment-history', [
            'invoices' => $invoices,
            'payments' => $payments,
            'certificates' => $certificates,
            'payingInstallment' => $payingInstallment,
        ]);
    }
}
