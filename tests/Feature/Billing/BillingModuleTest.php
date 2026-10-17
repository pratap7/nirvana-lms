<?php

declare(strict_types=1);

use App\Domain\Billing\Actions\CreateInstallmentPlanAction;
use App\Domain\Billing\Actions\GenerateInvoiceAction;
use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Actions\ProcessRefundAction;
use App\Domain\Billing\Actions\RecordManualPaymentAction;
use App\Domain\Billing\Actions\VerifyManualPaymentAction;
use App\Domain\Billing\Enums\DiscountType;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Enums\ScholarshipStatus;
use App\Domain\Billing\Exceptions\MakerCheckerViolationException;
use App\Domain\Billing\Exceptions\RefundPolicyViolationException;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Gateways\StripePaymentGateway;
use App\Domain\Billing\Models\Coupon;
use App\Domain\Billing\Models\GatewayWebhookEvent;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Scholarship;
use App\Domain\Billing\Services\BillingReportService;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->settings = app(SettingsService::class);
    $this->settings->set('tax.institute_state', 'DL');
    $this->settings->set('tax.default_rate', 18.0);
    $this->settings->set('institute.currency', 'INR');

    $this->branch = Branch::factory()->create();
    $this->moderatorMaker = User::factory()->create(['first_name' => 'Moderator', 'last_name' => 'Maker']);
    $this->moderatorMaker->assignRole('Moderator');

    $this->adminChecker = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Checker']);
    $this->adminChecker->assignRole('Admin');

    $this->student = User::factory()->create([
        'first_name' => 'Arjuna',
        'last_name' => 'Disciple',
        'email' => 'arjuna@example.com',
        'address_line_1' => '108 Ashram Way',
        'state' => 'DL',
    ]);
    $this->student->assignRole('Student');

    $this->course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->adminChecker->id,
        'name' => 'Pranayama & Kriya Sadhana',
        'base_fee_amount' => 1000000, // 10,000 INR
        'base_fee_currency' => 'INR',
    ]);

    $this->batch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Autumn 2026 Batch',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => CarbonImmutable::now()->addDays(14)->toDateString(),
        'end_date' => CarbonImmutable::now()->addDays(45)->toDateString(),
        'capacity' => 20,
        'seat_hold_hours' => 24,
        'status' => BatchStatus::Open,
    ]);

    $this->enrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'batch_id' => $this->batch->id,
        'course_id' => $this->course->id,
        'status' => EnrollmentStatus::Approved,
    ]);

    // Bind real Stripe gateway with known test secret for deterministic test signatures
    $this->testSecret = 'whsec_test_secret_key_12345';
    config()->set('services.stripe.webhook_secret', $this->testSecret);
    $this->stripeGateway = new StripePaymentGateway(
        secretKey: 'sk_test_mock',
        webhookSecret: $this->testSecret
    );
    app()->instance(PaymentGateway::class, $this->stripeGateway);
});

test('Acceptance: replaying the same webhook twice yields exactly one payment record', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    $eventId = 'evt_test_replay_'.Str::random(10);
    $paymentIntentId = 'pi_test_'.Str::random(10);

    $payloadArray = [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.Str::random(10),
                'client_reference_id' => $invoice->ulid,
                'payment_intent' => $paymentIntentId,
                'amount_total' => $invoice->total_amount,
                'currency' => 'inr',
                'metadata' => [
                    'invoice_id' => (string) $invoice->id,
                    'invoice_ulid' => $invoice->ulid,
                ],
            ],
        ],
    ];

    $rawPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signatureHash = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->testSecret);
    $headerSignature = "t={$timestamp},v1={$signatureHash}";

    // First Webhook Delivery
    $response1 = $this->withHeaders([
        'Stripe-Signature' => $headerSignature,
    ])->postJson('/webhooks/stripe', $payloadArray);

    $response1->assertStatus(200);
    $response1->assertJson(['status' => 'processed', 'received' => true]);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(GatewayWebhookEvent::where('gateway_event_id', $eventId)->count())->toBe(1);

    // Replay the EXACT same webhook a second time
    $response2 = $this->withHeaders([
        'Stripe-Signature' => $headerSignature,
    ])->postJson('/webhooks/stripe', $payloadArray);

    $response2->assertStatus(200);
    $response2->assertJson(['status' => 'duplicate', 'received' => true]);

    // Ledger Assertion: strictly ONE payment must exist
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(GatewayWebhookEvent::where('gateway_event_id', $eventId)->count())->toBe(1);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoicePaymentStatus::Paid);
    expect($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('Acceptance: a forged signature is rejected', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    $payloadArray = [
        'id' => 'evt_forged_sig_'.Str::random(8),
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'client_reference_id' => $invoice->ulid,
                'amount_total' => $invoice->total_amount,
            ],
        ],
    ];

    $rawPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);
    $headerSignature = 't=1600000000,v1=bad_forged_hex_signature_deadbeef';

    $response = $this->withHeaders([
        'Stripe-Signature' => $headerSignature,
    ])->postJson('/webhooks/stripe', $payloadArray);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid signature']);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(0);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);
});

test('Acceptance: a tampered amount fails verification', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    // Tampered payload: student tries to pay only 1 INR (100 minor units) instead of full amount
    $tamperedAmount = 100;
    expect($invoice->total_amount)->toBeGreaterThan(100);

    $payloadArray = [
        'id' => 'evt_tampered_'.Str::random(8),
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.Str::random(10),
                'client_reference_id' => $invoice->ulid,
                'payment_intent' => 'pi_test_'.Str::random(10),
                'amount_total' => $tamperedAmount,
                'currency' => 'inr',
                'metadata' => [
                    'invoice_id' => (string) $invoice->id,
                    'invoice_ulid' => $invoice->ulid,
                ],
            ],
        ],
    ];

    $rawPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signatureHash = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->testSecret);
    $headerSignature = "t={$timestamp},v1={$signatureHash}";

    $response = $this->withHeaders([
        'Stripe-Signature' => $headerSignature,
    ])->postJson('/webhooks/stripe', $payloadArray);

    $response->assertStatus(422);
    $response->assertJson(['error' => 'Amount verification failed']);

    // Assert that payment was NOT recorded and invoice remains pending
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(0);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);
    expect($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Approved);
});

test('Acceptance: one user cannot record and verify the same manual payment (enforced maker-checker)', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    /** @var RecordManualPaymentAction $recordAction */
    $recordAction = app(RecordManualPaymentAction::class);

    // Moderator records a cash payment (Maker)
    $payment = $recordAction->execute(
        invoice: $invoice,
        amountMinor: $invoice->total_amount,
        method: PaymentMethod::Cash,
        recorder: $this->moderatorMaker,
        referenceNumber: 'CASH-REC-101',
        proofPath: 'receipts/cash_101.jpg',
        notes: 'Handed over cash at Delhi center desk'
    );

    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->recorded_by)->toBe($this->moderatorMaker->id);
    expect($payment->verified_by)->toBeNull();

    // The invoice must NOT be paid yet because only verified payments count!
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);
    expect($invoice->fresh()->getPaidAmount()->getAmount())->toBe(0);

    /** @var VerifyManualPaymentAction $verifyAction */
    $verifyAction = app(VerifyManualPaymentAction::class);

    // VIOLATION ATTEMPT: The same moderator attempts to verify their own payment
    expect(fn () => $verifyAction->execute(
        payment: $payment,
        checker: $this->moderatorMaker,
        approved: true
    ))->toThrow(MakerCheckerViolationException::class);

    // Payment still pending, ledger unchanged
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);

    // PROPER FLOW: A different user with payment.verify (adminChecker) approves
    $verifiedPayment = $verifyAction->execute(
        payment: $payment,
        checker: $this->adminChecker,
        approved: true,
        notes: 'Verified cash deposited into register'
    );

    expect($verifiedPayment->status)->toBe(PaymentStatus::Verified);
    expect($verifiedPayment->verified_by)->toBe($this->adminChecker->id);
    expect($verifiedPayment->verified_at)->not->toBeNull();

    // Now the derived invoice status reflects full payment
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoicePaymentStatus::Paid);
    expect($invoice->getPaidAmount()->getAmount())->toBe($invoice->total_amount);
    expect($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('Refund policy engine: 100% refund before start, 50% week one, rejected past 7 days', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'student_id' => $this->student->id,
        'amount' => $invoice->total_amount,
        'currency' => $invoice->currency,
        'method' => PaymentMethod::Stripe,
        'gateway' => 'stripe',
        'gateway_transaction_id' => 'pi_test_refund_101',
        'status' => PaymentStatus::Verified,
        'verified_at' => now(),
    ]);
    MarkInvoicePaidAction::run($invoice);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Paid);

    /** @var ProcessRefundAction $refundAction */
    $refundAction = app(ProcessRefundAction::class);

    // Scenario 1: Today is 14 days before start date -> 100% refund eligible
    $refund1 = $refundAction->execute(
        payment: $payment,
        reason: 'Student scheduling conflict before course start',
        actor: $this->adminChecker
    );

    expect($refund1->amount)->toBe($invoice->total_amount);
    expect($refund1->status)->toBe('processed');
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Refunded);

    // Scenario 2: Batch started 3 days ago -> 50% refund eligible
    $startedBatch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Active Cohort',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => CarbonImmutable::now()->subDays(3)->toDateString(),
        'end_date' => CarbonImmutable::now()->addDays(20)->toDateString(),
        'capacity' => 10,
        'status' => BatchStatus::Open,
    ]);
    $invoice2 = $generateInvoice->execute(
        student: $this->student,
        batch: $startedBatch,
        course: $this->course
    );
    $payment2 = Payment::create([
        'invoice_id' => $invoice2->id,
        'student_id' => $this->student->id,
        'amount' => $invoice2->total_amount,
        'currency' => $invoice2->currency,
        'method' => PaymentMethod::Stripe,
        'gateway' => 'stripe',
        'gateway_transaction_id' => 'pi_test_refund_102',
        'status' => PaymentStatus::Verified,
        'verified_at' => now(),
    ]);
    MarkInvoicePaidAction::run($invoice2);

    $refund2 = $refundAction->execute(
        payment: $payment2,
        reason: 'Left in week one',
        actor: $this->adminChecker
    );

    $expectedFiftyPercent = (int) round($payment2->amount * 0.5);
    expect($refund2->amount)->toBe($expectedFiftyPercent);
    // Invoice had total paid, refunded half -> now PartiallyPaid
    expect($invoice2->fresh()->status)->toBe(InvoicePaymentStatus::PartiallyPaid);

    // Scenario 3: Batch started 15 days ago -> Policy denies refund
    $pastBatch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Old Cohort',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => CarbonImmutable::now()->subDays(15)->toDateString(),
        'end_date' => CarbonImmutable::now()->addDays(10)->toDateString(),
        'capacity' => 10,
        'status' => BatchStatus::Open,
    ]);
    $invoice3 = $generateInvoice->execute(
        student: $this->student,
        batch: $pastBatch,
        course: $this->course
    );
    $payment3 = Payment::create([
        'invoice_id' => $invoice3->id,
        'student_id' => $this->student->id,
        'amount' => $invoice3->total_amount,
        'currency' => $invoice3->currency,
        'method' => PaymentMethod::Stripe,
        'gateway' => 'stripe',
        'gateway_transaction_id' => 'pi_test_refund_103',
        'status' => PaymentStatus::Verified,
        'verified_at' => now(),
    ]);
    MarkInvoicePaidAction::run($invoice3);

    expect(fn () => $refundAction->execute(
        payment: $payment3,
        reason: 'Want money back after 2 weeks',
        actor: $this->adminChecker
    ))->toThrow(RefundPolicyViolationException::class);
});

test('Installments: due dates, grace period, overdue flag command and auto-suspension', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    // Active enrollment
    $this->enrollment->update(['status' => EnrollmentStatus::Active]);

    /** @var CreateInstallmentPlanAction $createPlan */
    $createPlan = app(CreateInstallmentPlanAction::class);
    $plan = $createPlan->execute(
        invoice: $invoice,
        numberOfInstallments: 3,
        intervalDays: 30,
        gracePeriodDays: 5,
        autoSuspendOnOverdue: true
    );

    expect($plan->installments()->count())->toBe(3);
    $installments = $plan->installments;

    // Simulate an installment that has expired past its due date + grace period
    $overdueInstallment = $installments->first();
    $overdueInstallment->update([
        'due_date' => CarbonImmutable::now()->subDays(10),
        'grace_date' => CarbonImmutable::now()->subDays(5),
        'is_overdue' => false,
    ]);

    // Run the automated console command
    Artisan::call('billing:check-overdue-installments');

    expect($overdueInstallment->fresh()->is_overdue)->toBeTrue();
    // Enrollment auto-suspended because installment is overdue
    expect($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Dropped);
});

test('GST sequential gap-free invoice number generation per financial year', function (): void {
    $seq1 = Invoice::generateNextNumber('2026-27');
    $seq2 = Invoice::generateNextNumber('2026-27');
    $seq3 = Invoice::generateNextNumber('2026-27');

    expect($seq1['number'])->toBe('INV/2026-27/0001');
    expect($seq2['number'])->toBe('INV/2026-27/0002');
    expect($seq3['number'])->toBe('INV/2026-27/0003');

    expect($seq1['sequence'])->toBe(1);
    expect($seq2['sequence'])->toBe(2);
    expect($seq3['sequence'])->toBe(3);
});

test('Coupons and Scholarships calculate discounts accurately', function (): void {
    // 1. Percentage Coupon
    $coupon = Coupon::create([
        'code' => 'YOGA20',
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 20, // 20%
        'usage_limit' => 50,
        'is_active' => true,
    ]);

    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);

    $invoiceWithCoupon = $generateInvoice->execute(
        student: $this->student,
        course: $this->course,
        couponCode: 'YOGA20'
    );

    // Course fee = 10,000 INR (1,000,000 minor)
    // 20% discount = 2,000 INR (200,000 minor)
    // Taxable subtotal = 8,000 INR
    // 18% GST on 8,000 = 1,440 INR (144,000 minor)
    // Total = 9,440 INR (944,000 minor)
    expect($invoiceWithCoupon->subtotal_amount)->toBe(1000000);
    expect($invoiceWithCoupon->discount_amount)->toBe(200000);
    expect($invoiceWithCoupon->tax_amount)->toBe(144000);
    expect($invoiceWithCoupon->total_amount)->toBe(944000);
    expect($coupon->fresh()->used_count)->toBe(1);

    // 2. Scholarship Approval Workflow
    $scholarship = Scholarship::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'type' => DiscountType::Fixed,
        'value' => 300000, // 3,000 INR scholarship
        'reason' => 'Academic merit and financial aid',
        'status' => ScholarshipStatus::Approved,
        'approved_by' => $this->adminChecker->id,
        'approved_at' => now(),
    ]);

    $invoiceWithScholarship = $generateInvoice->execute(
        student: $this->student,
        course: $this->course,
        scholarshipId: $scholarship->id
    );

    // 10,000 INR - 3,000 scholarship = 7,000 taxable
    // 18% GST on 7,000 = 1,260 INR (126,000 minor)
    // Total = 8,260 INR (826,000 minor)
    expect($invoiceWithScholarship->discount_amount)->toBe(300000);
    expect($invoiceWithScholarship->tax_amount)->toBe(126000);
    expect($invoiceWithScholarship->total_amount)->toBe(826000);
});

test('Billing report service aggregates outstanding balances and daily collections', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);

    $invoice1 = $generateInvoice->execute(
        student: $this->student,
        course: $this->course
    );

    $invoice2 = $generateInvoice->execute(
        student: $this->student,
        course: $this->course
    );

    // Settle invoice 1
    Payment::create([
        'invoice_id' => $invoice1->id,
        'student_id' => $this->student->id,
        'amount' => $invoice1->total_amount,
        'currency' => $invoice1->currency,
        'method' => PaymentMethod::Upi,
        'gateway' => 'manual',
        'status' => PaymentStatus::Verified,
        'verified_at' => now(),
    ]);
    MarkInvoicePaidAction::run($invoice1);

    /** @var BillingReportService $reportService */
    $reportService = app(BillingReportService::class);

    // Outstanding balances: invoice 2 is pending, invoice 1 is paid
    $outstanding = $reportService->getOutstandingBalances();
    expect($outstanding['invoices_count'])->toBe(1);
    expect($outstanding['total_outstanding']->getAmount())->toBe($invoice2->total_amount);

    // Daily Collection: invoice 1 payment settled today
    $collection = $reportService->getDailyCollection(now()->startOfDay(), now()->endOfDay());
    expect($collection['transactions_count'])->toBe(1);
    expect($collection['total_collected']->getAmount())->toBe($invoice1->total_amount);
    expect($collection['by_method']['upi'])->toBe($invoice1->total_amount);
});

test('Enrollment is never activated by browser hit on success URL; polling polls verified state', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        enrollment: $this->enrollment,
        batch: $this->batch,
        course: $this->course
    );

    // Browser navigates to the invoice status page without verified payment
    $response = $this->getJson("/invoices/{$invoice->ulid}/status");

    $response->assertStatus(200);
    $response->assertJson([
        'invoice_number' => $invoice->invoice_number,
        'status' => 'pending',
        'is_paid' => false,
        'enrollment_status' => 'approved',
    ]);

    // Enrollment must strictly remain Approved (not Active!)
    expect($this->enrollment->fresh()->status)->toBe(EnrollmentStatus::Approved);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);
});

test('Tax breakup calculates Intra-state CGST+SGST vs Inter-state IGST', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);

    // 1. Intra-state student (Delhi to Delhi)
    $delhiStudent = User::factory()->create([
        'state' => 'DL',
    ]);
    $intraInvoice = $generateInvoice->execute(
        student: $delhiStudent,
        course: $this->course
    );

    expect($intraInvoice->tax_breakup['type'])->toBe('intra_state');
    expect($intraInvoice->tax_breakup['cgst_rate'])->toEqual(9.0);
    expect($intraInvoice->tax_breakup['sgst_rate'])->toEqual(9.0);
    expect($intraInvoice->tax_breakup['igst_rate'])->toEqual(0.0);

    // 2. Inter-state student (Karnataka to Delhi)
    $karnatakaStudent = User::factory()->create([
        'state' => 'KA',
    ]);
    $interInvoice = $generateInvoice->execute(
        student: $karnatakaStudent,
        course: $this->course
    );

    expect($interInvoice->tax_breakup['type'])->toBe('inter_state');
    expect($interInvoice->tax_breakup['cgst_rate'])->toEqual(0.0);
    expect($interInvoice->tax_breakup['sgst_rate'])->toEqual(0.0);
    expect($interInvoice->tax_breakup['igst_rate'])->toEqual(18.0);
});

test('Maker-checker: rejecting a manual payment marks status failed and leaves invoice pending', function (): void {
    /** @var GenerateInvoiceAction $generateInvoice */
    $generateInvoice = app(GenerateInvoiceAction::class);
    $invoice = $generateInvoice->execute(
        student: $this->student,
        course: $this->course
    );

    /** @var RecordManualPaymentAction $recordAction */
    $recordAction = app(RecordManualPaymentAction::class);
    $payment = $recordAction->execute(
        invoice: $invoice,
        amountMinor: $invoice->total_amount,
        method: PaymentMethod::BankTransfer,
        recorder: $this->moderatorMaker,
        referenceNumber: 'NEFT-INVALID-999'
    );

    /** @var VerifyManualPaymentAction $verifyAction */
    $verifyAction = app(VerifyManualPaymentAction::class);
    $rejectedPayment = $verifyAction->execute(
        payment: $payment,
        checker: $this->adminChecker,
        approved: false,
        notes: 'Transaction reference not found in bank statement'
    );

    expect($rejectedPayment->status)->toBe(PaymentStatus::Failed);
    expect($rejectedPayment->verified_by)->toBe($this->adminChecker->id);
    expect($invoice->fresh()->status)->toBe(InvoicePaymentStatus::Pending);
});
