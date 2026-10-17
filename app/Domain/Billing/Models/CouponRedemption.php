<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record of coupon redemption linked to an invoice.
 *
 * @property int $id
 * @property string $ulid
 * @property int $coupon_id
 * @property int $user_id
 * @property int $invoice_id
 * @property int $discount_amount
 * @property string $currency
 * @property-read ?Coupon $coupon
 * @property-read ?User $user
 * @property-read ?Invoice $invoice
 */
class CouponRedemption extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'coupon_id',
        'user_id',
        'invoice_id',
        'discount_amount',
        'currency',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getDiscount(): Money
    {
        return Money::fromMinor($this->discount_amount, $this->currency);
    }
}
