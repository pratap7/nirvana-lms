<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\ValueObjects\CompletionRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dedicated persistence model for course completion rules.
 *
 * @property int $id
 * @property int $course_id
 * @property int $min_attendance_percent
 * @property int $min_score_percent
 * @property ?array<int|string> $required_lessons
 * @property int $assignments_required
 * @property bool $manual_approval
 * @property ?array<string, mixed> $rules_json
 * @property-read ?Course $course
 */
class CompletionRuleModel extends Model
{
    use HasFactory;

    protected $table = 'completion_rules';

    protected $fillable = [
        'course_id',
        'min_attendance_percent',
        'min_score_percent',
        'required_lessons',
        'assignments_required',
        'manual_approval',
        'rules_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_attendance_percent' => 'integer',
            'min_score_percent' => 'integer',
            'required_lessons' => 'array',
            'assignments_required' => 'integer',
            'manual_approval' => 'boolean',
            'rules_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function toValueObject(): CompletionRule
    {
        return new CompletionRule(
            minAttendancePercent: $this->min_attendance_percent,
            minScorePercent: $this->min_score_percent,
            requiredLessons: $this->required_lessons ?? [],
            assignmentsRequired: $this->assignments_required,
            manualApproval: $this->manualApproval(),
        );
    }

    public function manualApproval(): bool
    {
        return $this->manual_approval;
    }
}
