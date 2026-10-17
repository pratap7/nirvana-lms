<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record of student transfer between cohorts/batches with pro-rata financial ledger.
 *
 * @property int $id
 * @property string $ulid
 * @property int $enrollment_id
 * @property int $source_batch_id
 * @property int $target_batch_id
 * @property ?int $transferred_by
 * @property ?int $pro_rata_refund_amount
 * @property ?int $pro_rata_fee_due_amount
 * @property string $currency
 * @property ?string $notes
 * @property-read ?Enrollment $enrollment
 * @property-read ?Batch $sourceBatch
 * @property-read ?Batch $targetBatch
 * @property-read ?User $transferredBy
 */
class EnrollmentTransfer extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'enrollment_id',
        'source_batch_id',
        'target_batch_id',
        'transferred_by',
        'pro_rata_refund_amount',
        'pro_rata_fee_due_amount',
        'currency',
        'notes',
    ];

    protected $hidden = ['id'];

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
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'source_batch_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function targetBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'target_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function getProRataRefund(): ?Money
    {
        if ($this->pro_rata_refund_amount === null) {
            return null;
        }

        return Money::fromMinor($this->pro_rata_refund_amount, $this->currency);
    }

    public function getProRataFeeDue(): ?Money
    {
        if ($this->pro_rata_fee_due_amount === null) {
            return null;
        }

        return Money::fromMinor($this->pro_rata_fee_due_amount, $this->currency);
    }
}
