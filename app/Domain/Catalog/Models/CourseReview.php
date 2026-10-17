<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Course reviews submitted by enrolled students with at least 25% course progress.
 *
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property int $user_id
 * @property int $rating
 * @property ?string $title
 * @property string $review_text
 * @property ReviewStatus $status
 * @property ?int $moderated_by
 * @property ?CarbonInterface $moderated_at
 * @property ?string $moderation_note
 * @property-read ?Course $course
 * @property-read ?User $user
 * @property-read ?User $moderator
 */
class CourseReview extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $fillable = [
        'ulid',
        'course_id',
        'user_id',
        'rating',
        'title',
        'review_text',
        'status',
        'moderated_by',
        'moderated_at',
        'moderation_note',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'moderated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReviewStatus::Pending);
    }

    public function isApproved(): bool
    {
        return $this->status === ReviewStatus::Approved;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rating', 'status', 'moderated_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
