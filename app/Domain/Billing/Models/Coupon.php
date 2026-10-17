<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\DiscountType;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Promotional discount coupon.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property DiscountType $discount_type
 * @property int $discount_value
 * @property ?int $max_discount_amount
 * @property ?int $min_order_amount
 * @property ?CarbonInterface $valid_from
 * @property ?CarbonInterface $valid_until
 * @property ?int $usage_limit
 * @property int $used_count
 * @property int $per_user_limit
 * @property ?int $course_id
 * @property ?int $batch_id
 * @property bool $is_active
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read Collection<int, CouponRedemption> $redemptions
 */
class Coupon extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'code',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'valid_from',
        'valid_until',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'course_id',
        'batch_id',
        'is_active',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_order_amount' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Validate whether coupon can be applied.
     *
     * @return array{valid: bool, reason: ?string}
     */
    public function validateFor(User $student, ?int $courseId, ?int $batchId, Money $subtotal): array
    {
        if (! $this->is_active) {
            return ['valid' => false, 'reason' => 'Coupon is inactive.'];
        }

        if ($this->valid_from && now()->isBefore($this->valid_from)) {
            return ['valid' => false, 'reason' => 'Coupon is not yet active.'];
        }

        if ($this->valid_until && now()->isAfter($this->valid_until)) {
            return ['valid' => false, 'reason' => 'Coupon has expired.'];
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return ['valid' => false, 'reason' => 'Coupon usage limit has been reached.'];
        }

        if ($this->course_id !== null && $this->course_id !== $courseId) {
            return ['valid' => false, 'reason' => 'Coupon is not applicable to this course.'];
        }

        if ($this->batch_id !== null && $this->batch_id !== $batchId) {
            return ['valid' => false, 'reason' => 'Coupon is not applicable to this batch.'];
        }

        if ($this->min_order_amount !== null && $subtotal->getAmount() < $this->min_order_amount) {
            $minFormatted = Money::fromMinor($this->min_order_amount, $subtotal->getCurrency())->format();

            return ['valid' => false, 'reason' => "Minimum order amount of {$minFormatted} required."];
        }

        $userRedemptions = $this->redemptions()->where('user_id', $student->id)->count();
        if ($userRedemptions >= $this->per_user_limit) {
            return ['valid' => false, 'reason' => 'You have already used this coupon maximum times.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Calculate discount amount for a given subtotal.
     */
    public function calculateDiscount(Money $subtotal): Money
    {
        $currency = $subtotal->getCurrency();

        if ($this->discount_type === DiscountType::Percentage) {
            $calc = (int) round(($subtotal->getAmount() * $this->discount_value) / 100);
            if ($this->max_discount_amount !== null && $calc > $this->max_discount_amount) {
                $calc = $this->max_discount_amount;
            }

            return Money::fromMinor($calc, $currency);
        }

        // Fixed discount
        $discountAmount = min($subtotal->getAmount(), $this->discount_value);

        return Money::fromMinor($discountAmount, $currency);
    }
}
