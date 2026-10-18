<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $course_id
 * @property ?int $batch_id
 * @property float $overall_percentage
 * @property int $lessons_completed
 * @property int $total_required_lessons
 * @property float $attendance_percentage
 * @property float $quiz_average
 * @property ?int $last_accessed_lesson_id
 * @property ?CarbonInterface $completed_at
 * @property-read ?User $user
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?Lesson $lastAccessedLesson
 */
class CourseProgress extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'course_progress';

    protected $fillable = [
        'ulid',
        'user_id',
        'course_id',
        'batch_id',
        'overall_percentage',
        'lessons_completed',
        'total_required_lessons',
        'attendance_percentage',
        'quiz_average',
        'last_accessed_lesson_id',
        'completed_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_percentage' => 'float',
            'lessons_completed' => 'integer',
            'total_required_lessons' => 'integer',
            'attendance_percentage' => 'float',
            'quiz_average' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public function lastAccessedLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_accessed_lesson_id');
    }

    public function getLessonCompletionPercentageAttribute(): float
    {
        if ($this->total_required_lessons <= 0) {
            return 100.0;
        }

        return round(($this->lessons_completed / $this->total_required_lessons) * 100, 2);
    }
}
