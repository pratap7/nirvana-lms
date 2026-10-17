<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment ledger transaction.
 *
 * @property int $id
 * @property string $ulid
 * @property int $invoice_id
 * @property int $student_id
 * @property int $amount
 * @property string $currency
 * @property PaymentMethod $method
 * @property string $gateway
 * @property ?string $gateway_transaction_id
 * @property ?string $gateway_order_id
 * @property PaymentStatus $status
 * @property ?string $reference_number
 * @property ?string $proof_path
 * @property ?int $recorded_by
 * @property ?int $verified_by
 * @property ?CarbonInterface $verified_at
 * @property ?string $verification_notes
 * @property ?array<string, mixed> $payload
 * @property-read ?Invoice $invoice
 * @property-read ?User $student
 * @property-read ?User $recorder
 * @property-read ?User $verifier
 * @property-read Collection<int, Refund> $refunds
 */
class Payment extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'invoice_id',
        'student_id',
        'amount',
        'currency',
        'method',
        'gateway',
        'gateway_transaction_id',
        'gateway_order_id',
        'status',
        'reference_number',
        'proof_path',
        'recorded_by',
        'verified_by',
        'verified_at',
        'verification_notes',
        'payload',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
            'payload' => 'array',
        ];
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
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function getAmount(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }

    public function isVerified(): bool
    {
        return $this->status === PaymentStatus::Verified;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Verified->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending->value);
    }
}
