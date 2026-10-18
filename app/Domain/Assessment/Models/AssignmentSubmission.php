<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $assignment_id
 * @property int $user_id
 * @property int $submission_number
 * @property ?string $text_content
 * @property ?string $file_path
 * @property ?string $link_url
 * @property string $status
 * @property ?int $score
 * @property ?string $feedback
 * @property ?int $graded_by
 * @property ?CarbonInterface $graded_at
 * @property CarbonInterface $submitted_at
 * @property bool $is_late
 * @property-read ?Assignment $assignment
 * @property-read ?User $user
 * @property-read ?User $grader
 */
class AssignmentSubmission extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'assignment_id',
        'user_id',
        'submission_number',
        'text_content',
        'file_path',
        'link_url',
        'status',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
        'submitted_at',
        'is_late',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_number' => 'integer',
            'score' => 'integer',
            'graded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
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
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
