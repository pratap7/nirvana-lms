<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property ?int $batch_id
 * @property ?int $lesson_id
 * @property string $title
 * @property ?string $description
 * @property array<string> $submission_types
 * @property int $max_score
 * @property CarbonInterface $due_at
 * @property bool $allow_late
 * @property int $max_resubmissions
 * @property bool $is_published
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?Lesson $lesson
 * @property-read Collection<int, AssignmentSubmission> $submissions
 */
class Assignment extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'course_id',
        'batch_id',
        'lesson_id',
        'title',
        'description',
        'submission_types',
        'max_score',
        'due_at',
        'allow_late',
        'max_resubmissions',
        'is_published',
        'rubric',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_types' => 'array',
            'rubric' => 'array',
            'max_score' => 'integer',
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
            'max_resubmissions' => 'integer',
            'is_published' => 'boolean',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function latestSubmissionFor(User $user): ?AssignmentSubmission
    {
        return $this->submissions()
            ->where('user_id', $user->id)
            ->latest('submission_number')
            ->first();
    }
}
