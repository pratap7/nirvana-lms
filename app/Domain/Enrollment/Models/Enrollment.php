<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasStateMachine;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Student course/batch enrollment with full state machine lifecycle.
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property int $batch_id
 * @property int $course_id
 * @property EnrollmentStatus $status
 * @property ?CarbonInterface $seat_hold_expires_at
 * @property ?int $waitlist_position
 * @property bool $is_offline_registration
 * @property ?int $registered_by
 * @property bool $consent_given
 * @property ?CarbonInterface $consent_given_at
 * @property ?string $consent_ip
 * @property bool $prerequisites_verified
 * @property ?string $prerequisites_notes
 * @property ?string $rejection_reason
 * @property ?string $drop_reason
 * @property ?string $cancellation_reason
 * @property ?string $internal_notes
 * @property ?array<string, mixed> $metadata
 * @property-read ?User $student
 * @property-read ?Batch $batch
 * @property-read ?Course $course
 * @property-read ?User $registeredBy
 * @property-read Collection<int, EnrollmentStatusHistory> $statusHistories
 * @property-read Collection<int, EnrollmentTransfer> $transfers
 */
class Enrollment extends Model
{
    use HasFactory;
    use HasStateMachine;
    use HasUlid;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'student_id',
        'batch_id',
        'course_id',
        'status',
        'seat_hold_expires_at',
        'waitlist_position',
        'is_offline_registration',
        'registered_by',
        'consent_given',
        'consent_given_at',
        'consent_ip',
        'prerequisites_verified',
        'prerequisites_notes',
        'rejection_reason',
        'drop_reason',
        'cancellation_reason',
        'internal_notes',
        'metadata',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment): void {
            if (empty($enrollment->course_id) && ! empty($enrollment->batch_id)) {
                $batch = Batch::find($enrollment->batch_id);
                if ($batch) {
                    $enrollment->course_id = $batch->course_id;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'seat_hold_expires_at' => 'datetime',
            'consent_given' => 'boolean',
            'consent_given_at' => 'datetime',
            'is_offline_registration' => 'boolean',
            'prerequisites_verified' => 'boolean',
            'waitlist_position' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\Support\LogOptions
    {
        return \Spatie\Activitylog\Support\LogOptions::defaults()
            ->logOnly(['status', 'seat_hold_expires_at', 'waitlist_position', 'internal_notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
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
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return HasMany<EnrollmentStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class)->orderBy('created_at', 'asc');
    }

    /**
     * @return HasMany<EnrollmentTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(EnrollmentTransfer::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if a time-limited seat hold has expired.
     */
    public function hasExpiredHold(): bool
    {
        if (! in_array($this->status, [EnrollmentStatus::Approved, EnrollmentStatus::PaymentPending], true)) {
            return false;
        }

        return $this->seat_hold_expires_at !== null && $this->seat_hold_expires_at->isPast();
    }

    /**
     * Check whether this enrollment is currently occupying a seat.
     */
    public function isSeatOccupied(): bool
    {
        if ($this->status === EnrollmentStatus::Active) {
            return true;
        }

        if (in_array($this->status, [EnrollmentStatus::Approved, EnrollmentStatus::PaymentPending], true)) {
            return ! $this->hasExpiredHold();
        }

        return false;
    }

    /**
     * Record a status transition event in the history timeline.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordStatusHistory(
        ?string $fromStatus,
        string $toStatus,
        ?User $changedBy = null,
        ?string $reason = null,
        array $metadata = []
    ): EnrollmentStatusHistory {
        return $this->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy?->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Scope query to enrollments that currently occupy seats.
     */
    public function scopeSeatOccupying(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', EnrollmentStatus::Active->value)
                ->orWhere(function (Builder $sub): void {
                    $sub->whereIn('status', [
                        EnrollmentStatus::Approved->value,
                        EnrollmentStatus::PaymentPending->value,
                    ])->where(function (Builder $time): void {
                        $time->whereNull('seat_hold_expires_at')
                            ->orWhere('seat_hold_expires_at', '>', now());
                    });
                });
        });
    }

    /**
     * Scope query to waitlisted students in FIFO order.
     */
    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Waitlisted->value)
            ->orderBy('waitlist_position', 'asc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Scope query to enrollments pending moderator review.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EnrollmentStatus::Requested->value,
            EnrollmentStatus::UnderReview->value,
        ])->orderBy('created_at', 'asc');
    }
}
