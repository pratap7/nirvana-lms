<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property ?int $lesson_id
 * @property string $title
 * @property ?string $description
 * @property ?int $duration_minutes
 * @property int $pass_percentage
 * @property int $max_attempts
 * @property bool $shuffle_questions
 * @property bool $is_published
 * @property-read ?Course $course
 * @property-read ?Lesson $lesson
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
class Quiz extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'course_id',
        'lesson_id',
        'title',
        'description',
        'duration_minutes',
        'pass_percentage',
        'max_attempts',
        'shuffle_questions',
        'is_published',
        'time_limit_minutes',
        'passing_score',
    ];

    public function setTimeLimitMinutesAttribute(int|float $value): void
    {
        $this->attributes['duration_minutes'] = (int) $value;
    }

    public function setPassingScoreAttribute(int|float $value): void
    {
        $this->attributes['pass_percentage'] = (int) $value;
    }

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'pass_percentage' => 'integer',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function userAttemptsCount(User $user): int
    {
        return $this->attempts()->where('user_id', $user->id)->count();
    }

    public function canUserAttempt(User $user): bool
    {
        return $this->userAttemptsCount($user) < $this->max_attempts;
    }
}
