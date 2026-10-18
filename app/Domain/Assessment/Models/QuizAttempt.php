<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Models;

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
 * @property int $quiz_id
 * @property int $user_id
 * @property ?int $batch_id
 * @property int $attempt_number
 * @property ?array<string|int, mixed> $answers
 * @property int $score
 * @property int $max_score
 * @property float $percentage
 * @property bool $passed
 * @property CarbonInterface $started_at
 * @property ?CarbonInterface $submitted_at
 * @property-read ?Quiz $quiz
 * @property-read ?User $user
 * @property-read ?Batch $batch
 */
class QuizAttempt extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'quiz_id',
        'user_id',
        'batch_id',
        'attempt_number',
        'answers',
        'score',
        'max_score',
        'percentage',
        'passed',
        'started_at',
        'submitted_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'answers' => 'array',
            'score' => 'integer',
            'max_score' => 'integer',
            'percentage' => 'float',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
