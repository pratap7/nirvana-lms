<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refund ledger record.
 *
 * @property int $id
 * @property string $ulid
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $amount
 * @property string $currency
 * @property string $reason
 * @property ?string $policy_applied
 * @property ?string $gateway_refund_id
 * @property string $status
 * @property ?int $processed_by
 * @property ?CarbonInterface $processed_at
 * @property ?string $notes
 * @property-read ?Payment $payment
 * @property-read ?Invoice $invoice
 * @property-read ?User $processor
 */
class Refund extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'payment_id',
        'invoice_id',
        'amount',
        'currency',
        'reason',
        'policy_applied',
        'gateway_refund_id',
        'status',
        'processed_by',
        'processed_at',
        'notes',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getAmount(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }
}
