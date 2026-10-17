<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Coupon;
use App\Domain\Billing\Models\CouponRedemption;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Models\Scholarship;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use App\Support\Money;
use App\Support\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceAction extends BaseAction
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Generate a GST-ready invoice with sequential FY number, tax breakup, and applied discounts.
     *
     * @param  array<int, array{description: string, quantity: int, unit_price: int, sac_code?: string, tax_rate?: float}>  $customItems
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        User $student,
        ?Enrollment $enrollment = null,
        ?Batch $batch = null,
        ?Course $course = null,
        ?string $couponCode = null,
        ?int $scholarshipId = null,
        int $dueDateDays = 7,
        array $customItems = [],
        array $metadata = []
    ): Invoice {
        return DB::transaction(function () use (
            $student,
            $enrollment,
            $batch,
            $course,
            $couponCode,
            $scholarshipId,
            $dueDateDays,
            $customItems,
            $metadata
        ): Invoice {
            $currency = (string) $this->settings->get('institute.currency', 'INR');
            $instituteState = (string) $this->settings->get('tax.institute_state', 'DL');
            $defaultTaxRate = (float) $this->settings->get('tax.default_rate', 18.0); // 18% GST

            // Determine line items
            $resolvedCourse = $course ?? ($batch !== null ? $batch->course : $enrollment?->course);
            $resolvedBatch = $batch ?? $enrollment?->batch;

            $itemsData = [];
            if (! empty($customItems)) {
                $itemsData = $customItems;
            } else {
                $feeMinor = 0;
                if ($resolvedBatch && $resolvedBatch->fee_override_amount !== null) {
                    $feeMinor = $resolvedBatch->getEffectiveFee()->getAmount();
                } elseif ($resolvedCourse) {
                    $feeMinor = $resolvedCourse->fee->getAmount();
                }

                $title = $resolvedCourse ? "Course: {$resolvedCourse->title}" : 'Educational Training Program';
                if ($resolvedBatch) {
                    $title .= " ({$resolvedBatch->name})";
                }

                $itemsData[] = [
                    'description' => $title,
                    'quantity' => 1,
                    'unit_price' => $feeMinor,
                    'sac_code' => '999293', // Educational and vocational services
                    'tax_rate' => $defaultTaxRate,
                ];
            }

            // Calculate Subtotal
            $subtotalMinor = 0;
            foreach ($itemsData as $item) {
                $subtotalMinor += ((int) $item['quantity']) * ((int) $item['unit_price']);
            }

            // Calculate Discounts
            $discountMinor = 0;
            $appliedCoupon = null;
            if ($couponCode !== null) {
                $coupon = Coupon::findByCode($couponCode);
                if ($coupon) {
                    $val = $coupon->validateFor($student, $resolvedCourse?->id, $resolvedBatch?->id, Money::fromMinor($subtotalMinor, $currency));
                    if ($val['valid']) {
                        $discountMoney = $coupon->calculateDiscount(Money::fromMinor($subtotalMinor, $currency));
                        $discountMinor += $discountMoney->getAmount();
                        $appliedCoupon = $coupon;
                    }
                }
            }

            $appliedScholarship = null;
            if ($scholarshipId !== null) {
                /** @var Scholarship|null $scholarship */
                $scholarship = Scholarship::query()->find($scholarshipId);
                if ($scholarship && $scholarship->student_id === $student->id && $scholarship->isApproved()) {
                    $scholarshipDiscount = $scholarship->calculateDiscount(Money::fromMinor($subtotalMinor, $currency));
                    $discountMinor += $scholarshipDiscount->getAmount();
                    $appliedScholarship = $scholarship;
                }
            }

            // Cap discount at subtotal
            $discountMinor = min($discountMinor, $subtotalMinor);
            $taxableMinor = $subtotalMinor - $discountMinor;

            // Calculate GST Breakup (Intra-state: CGST 9% + SGST 9%, Inter-state: IGST 18%)
            $studentState = $student->address['state'] ?? $student->state ?? $instituteState;
            $isIntraState = strtoupper((string) $studentState) === strtoupper($instituteState);

            $taxMinor = (int) round(($taxableMinor * $defaultTaxRate) / 100);
            if ($isIntraState) {
                $halfTax = (int) round($taxMinor / 2);
                $taxBreakup = [
                    'type' => 'intra_state',
                    'cgst_rate' => $defaultTaxRate / 2,
                    'cgst_amount' => $halfTax,
                    'sgst_rate' => $defaultTaxRate / 2,
                    'sgst_amount' => $taxMinor - $halfTax,
                    'igst_rate' => 0.0,
                    'igst_amount' => 0,
                ];
            } else {
                $taxBreakup = [
                    'type' => 'inter_state',
                    'cgst_rate' => 0.0,
                    'cgst_amount' => 0,
                    'sgst_rate' => 0.0,
                    'sgst_amount' => 0,
                    'igst_rate' => $defaultTaxRate,
                    'igst_amount' => $taxMinor,
                ];
            }

            $totalMinor = $taxableMinor + $taxMinor;

            // Generate gap-free sequential invoice number
            $seqData = Invoice::generateNextNumber();

            $now = CarbonImmutable::now();
            $dueDate = $now->addDays($dueDateDays)->endOfDay();

            $invoice = Invoice::create([
                'invoice_number' => $seqData['number'],
                'financial_year' => $seqData['fy'],
                'sequence_number' => $seqData['sequence'],
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id,
                'batch_id' => $resolvedBatch?->id,
                'course_id' => $resolvedCourse?->id,
                'subtotal_amount' => $subtotalMinor,
                'discount_amount' => $discountMinor,
                'tax_amount' => $taxMinor,
                'total_amount' => $totalMinor,
                'currency' => $currency,
                'status' => InvoicePaymentStatus::Pending,
                'due_date' => $dueDate,
                'issued_at' => $now,
                'tax_breakup' => $taxBreakup,
                'billing_name' => $student->name ?? $student->full_name,
                'billing_email' => $student->email,
                'billing_address' => $student->address_line_1 ?? '',
                'billing_gstin' => null,
                'metadata' => array_merge($metadata, [
                    'coupon_code' => $appliedCoupon?->code,
                    'scholarship_id' => $appliedScholarship?->id,
                ]),
            ]);

            // Create Invoice Items
            foreach ($itemsData as $item) {
                $itemSubtotal = ((int) $item['quantity']) * ((int) $item['unit_price']);
                $itemTax = (int) round(($itemSubtotal * ($item['tax_rate'] ?? $defaultTaxRate)) / 100);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'course_fee',
                    'description' => $item['description'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price_amount' => (int) $item['unit_price'],
                    'total_amount' => $itemSubtotal + $itemTax,
                    'currency' => $currency,
                    'metadata' => [
                        'sac_code' => $item['sac_code'] ?? '999293',
                        'tax_rate' => (float) ($item['tax_rate'] ?? $defaultTaxRate),
                        'tax_amount' => $itemTax,
                    ],
                ]);
            }

            // Record Coupon Redemption
            if ($appliedCoupon !== null) {
                CouponRedemption::create([
                    'coupon_id' => $appliedCoupon->id,
                    'user_id' => $student->id,
                    'invoice_id' => $invoice->id,
                    'discount_amount' => $discountMinor,
                    'currency' => $currency,
                ]);
                $appliedCoupon->increment('used_count');
            }

            return $invoice->fresh(['items', 'student', 'enrollment', 'batch', 'course']);
        });
    }
}
