<?php

declare(strict_types=1);

namespace App\Domain\Certification\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Course Completion Evaluation audit record.
 * Snapshots the exact course rules and per-requirement checklist results.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $course_id
 * @property ?int $batch_id
 * @property bool $is_passed
 * @property bool $is_manually_approved
 * @property bool $requires_manual_approval
 * @property ?int $manual_approved_by
 * @property ?CarbonInterface $manual_approved_at
 * @property array<string, mixed> $rules_snapshot
 * @property array<int, array<string, mixed>> $checklist_results
 * @property-read ?User $user
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?User $manualApprovedBy
 */
class CompletionEvaluation extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'completion_evaluations';

    protected $fillable = [
        'ulid',
        'user_id',
        'course_id',
        'batch_id',
        'is_passed',
        'is_manually_approved',
        'requires_manual_approval',
        'manual_approved_by',
        'manual_approved_at',
        'rules_snapshot',
        'checklist_results',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_passed' => 'boolean',
            'is_manually_approved' => 'boolean',
            'requires_manual_approval' => 'boolean',
            'manual_approved_at' => 'datetime',
            'rules_snapshot' => 'array',
            'checklist_results' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function manualApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_approved_by');
    }
}
