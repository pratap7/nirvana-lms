<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Tax invoice ledger master record.
 *
 * @property int $id
 * @property string $ulid
 * @property string $invoice_number
 * @property string $financial_year
 * @property int $sequence_number
 * @property int $student_id
 * @property ?int $enrollment_id
 * @property ?int $batch_id
 * @property ?int $course_id
 * @property int $subtotal_amount
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total_amount
 * @property string $currency
 * @property InvoicePaymentStatus $status
 * @property CarbonInterface $due_date
 * @property CarbonInterface $issued_at
 * @property ?CarbonInterface $paid_at
 * @property ?array<string, mixed> $tax_breakup
 * @property string $billing_name
 * @property string $billing_email
 * @property ?string $billing_address
 * @property ?string $billing_gstin
 * @property ?string $notes
 * @property ?array<string, mixed> $metadata
 * @property-read ?User $student
 * @property-read ?Enrollment $enrollment
 * @property-read ?Batch $batch
 * @property-read ?Course $course
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 * @property-read ?InstallmentPlan $installmentPlan
 * @property-read Collection<int, Installment> $installments
 * @property-read Collection<int, CouponRedemption> $couponRedemptions
 */
class Invoice extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'invoice_number',
        'financial_year',
        'sequence_number',
        'student_id',
        'enrollment_id',
        'batch_id',
        'course_id',
        'event_id',
        'billable_type',
        'billable_id',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'due_date',
        'issued_at',
        'paid_at',
        'tax_breakup',
        'billing_name',
        'billing_email',
        'billing_address',
        'billing_gstin',
        'notes',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'sequence_number' => 'integer',
            'status' => InvoicePaymentStatus::class,
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'tax_breakup' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Polymorphic relation to any Billable entity (Course Enrollment, Event Registration, etc.)
     *
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasOne<InstallmentPlan, $this>
     */
    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number');
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function getSubtotal(): Money
    {
        return Money::fromMinor($this->subtotal_amount, $this->currency);
    }

    public function getDiscount(): Money
    {
        return Money::fromMinor($this->discount_amount, $this->currency);
    }

    public function getTax(): Money
    {
        return Money::fromMinor($this->tax_amount, $this->currency);
    }

    public function getTotal(): Money
    {
        return Money::fromMinor($this->total_amount, $this->currency);
    }

    public function getPaidAmount(): Money
    {
        $paid = (int) $this->payments()->where('status', PaymentStatus::Verified->value)->sum('amount');

        return Money::fromMinor($paid, $this->currency);
    }

    public function getRefundedAmount(): Money
    {
        $refunded = (int) $this->refunds()->where('status', 'processed')->sum('amount');

        return Money::fromMinor($refunded, $this->currency);
    }

    public function getNetPaidAmount(): Money
    {
        $net = max(0, $this->getPaidAmount()->getAmount() - $this->getRefundedAmount()->getAmount());

        return Money::fromMinor($net, $this->currency);
    }

    public function getRemainingBalance(): Money
    {
        $remaining = max(0, $this->total_amount - $this->getNetPaidAmount()->getAmount());

        return Money::fromMinor($remaining, $this->currency);
    }

    /**
     * Derive invoice payment status strictly from verified ledger entries.
     */
    public function calculateStatus(): InvoicePaymentStatus
    {
        $total = $this->total_amount;
        $paid = (int) $this->payments()->where('status', PaymentStatus::Verified->value)->sum('amount');
        $refunded = (int) $this->refunds()->where('status', 'processed')->sum('amount');
        $netPaid = max(0, $paid - $refunded);

        if ($refunded >= $total && $total > 0) {
            return InvoicePaymentStatus::Refunded;
        }

        if ($netPaid >= $total && $total > 0) {
            return InvoicePaymentStatus::Paid;
        }

        if ($netPaid > 0 && $netPaid < $total) {
            return InvoicePaymentStatus::PartiallyPaid;
        }

        return InvoicePaymentStatus::Pending;
    }

    /**
     * Recalculates and saves derived status on the invoice record.
     */
    public function recalculateStatus(): InvoicePaymentStatus
    {
        $status = $this->calculateStatus();
        $updates = ['status' => $status];

        if ($status === InvoicePaymentStatus::Paid && $this->paid_at === null) {
            $updates['paid_at'] = now();
        } elseif ($status !== InvoicePaymentStatus::Paid) {
            $updates['paid_at'] = null;
        }

        $this->update($updates);

        return $status;
    }

    /**
     * Atomically generate next sequential gap-free invoice number for the financial year.
     *
     * @return array{number: string, fy: string, sequence: int}
     */
    public static function generateNextNumber(?string $financialYear = null): array
    {
        return DB::transaction(function () use ($financialYear): array {
            $now = CarbonImmutable::now();
            $year = (int) $now->format('Y');
            $month = (int) $now->format('n');
            $startYear = $month >= 4 ? $year : $year - 1;
            $fy = $financialYear ?? sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);

            /** @var InvoiceSequence|null $seq */
            $seq = InvoiceSequence::query()->where('financial_year', $fy)->lockForUpdate()->first();
            if ($seq === null) {
                $seq = InvoiceSequence::create([
                    'financial_year' => $fy,
                    'current_number' => 0,
                ]);
            }

            $seq->increment('current_number');
            $seq->refresh();

            $num = $seq->current_number;
            $invoiceNumber = sprintf('INV/%s/%04d', $fy, $num);

            return [
                'number' => $invoiceNumber,
                'fy' => $fy,
                'sequence' => $num,
            ];
        });
    }
}
