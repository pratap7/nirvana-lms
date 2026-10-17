<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\Branch;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Batch cohort delivery for a Course.
 *
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property int $branch_id
 * @property string $name
 * @property string $slug
 * @property DeliveryMode $delivery_mode
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property int $capacity
 * @property ?int $venue_id
 * @property ?int $room_id
 * @property ?int $fee_override_amount
 * @property string $fee_override_currency
 * @property ?CarbonInterface $enrollment_starts_at
 * @property ?CarbonInterface $enrollment_ends_at
 * @property BatchStatus $status
 * @property ?array<string, mixed> $metadata
 * @property-read ?Course $course
 * @property-read ?Branch $branch
 * @property-read ?Venue $venue
 * @property-read ?Room $room
 * @property-read Collection<int, BatchSchedule> $schedules
 * @property-read Collection<int, Session> $sessions
 */
class Batch extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Batch $batch): void {
            if (empty($batch->slug) && ! empty($batch->name)) {
                $batch->slug = Str::slug($batch->name).'-'.Str::lower(Str::random(6));
            }
            if (empty($batch->branch_id) && ! empty($batch->course_id)) {
                $batch->branch_id = $batch->course ? $batch->course->branch_id : 1;
            }
        });
    }

    protected $fillable = [
        'ulid',
        'course_id',
        'branch_id',
        'name',
        'slug',
        'delivery_mode',
        'start_date',
        'end_date',
        'capacity',
        'venue_id',
        'room_id',
        'fee_override_amount',
        'fee_override_currency',
        'enrollment_starts_at',
        'enrollment_ends_at',
        'status',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_mode' => DeliveryMode::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
            'fee_override_amount' => 'integer',
            'enrollment_starts_at' => 'datetime',
            'enrollment_ends_at' => 'datetime',
            'status' => BatchStatus::class,
            'metadata' => 'array',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<BatchSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(BatchSchedule::class);
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class)->orderBy('starts_at');
    }

    public function getEffectiveFee(): Money
    {
        if ($this->fee_override_amount !== null) {
            return Money::fromMinor($this->fee_override_amount, $this->fee_override_currency);
        }

        return $this->course ? $this->course->fee : Money::zero();
    }

    public function isOpenForEnrollment(): bool
    {
        if ($this->status !== BatchStatus::Open) {
            return false;
        }

        if ($this->enrollment_starts_at !== null && now()->isBefore($this->enrollment_starts_at)) {
            return false;
        }

        if ($this->enrollment_ends_at !== null && now()->isAfter($this->enrollment_ends_at)) {
            return false;
        }

        return true;
    }
}
