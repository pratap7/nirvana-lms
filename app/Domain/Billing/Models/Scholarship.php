<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\ScholarshipStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Institutional scholarship or tuition grant.
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property ?int $course_id
 * @property ?int $batch_id
 * @property string $type
 * @property int $value
 * @property string $reason
 * @property ScholarshipStatus $status
 * @property ?int $requested_by
 * @property ?int $approved_by
 * @property ?CarbonInterface $approved_at
 * @property ?string $rejection_reason
 * @property-read ?User $student
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?User $approver
 */
class Scholarship extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'student_id',
        'course_id',
        'batch_id',
        'type',
        'value',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'status' => ScholarshipStatus::class,
            'approved_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === ScholarshipStatus::Approved;
    }

    public function calculateDiscount(Money $subtotal): Money
    {
        return $this->calculateGrantAmount($subtotal);
    }

    public function calculateGrantAmount(Money $subtotal): Money
    {
        if ($this->type === 'full') {
            return $subtotal;
        }

        if ($this->type === 'percentage') {
            $amount = (int) round(($subtotal->getAmount() * $this->value) / 100);

            return Money::fromMinor($amount, $subtotal->getCurrency());
        }

        // Fixed amount
        $fixed = min($subtotal->getAmount(), $this->value);

        return Money::fromMinor($fixed, $subtotal->getCurrency());
    }
}
