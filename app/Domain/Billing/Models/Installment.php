<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual installment row with due date and grace period.
 *
 * @property int $id
 * @property string $ulid
 * @property int $installment_plan_id
 * @property int $invoice_id
 * @property int $installment_number
 * @property int $amount
 * @property string $currency
 * @property CarbonInterface $due_date
 * @property CarbonInterface $grace_date
 * @property bool $is_overdue
 * @property ?CarbonInterface $paid_at
 * @property ?CarbonInterface $reminded_at
 * @property-read ?InstallmentPlan $plan
 * @property-read ?Invoice $invoice
 */
class Installment extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'installment_plan_id',
        'invoice_id',
        'installment_number',
        'amount',
        'currency',
        'due_date',
        'grace_date',
        'is_overdue',
        'paid_at',
        'reminded_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'amount' => 'integer',
            'due_date' => 'date',
            'grace_date' => 'date',
            'is_overdue' => 'boolean',
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InstallmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getAmount(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
