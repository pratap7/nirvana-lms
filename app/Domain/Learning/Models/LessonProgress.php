<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

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
 * @property int $lesson_id
 * @property ?int $batch_id
 * @property bool $is_completed
 * @property int $watch_percentage
 * @property int $last_position_seconds
 * @property ?CarbonInterface $completed_at
 * @property-read ?User $user
 * @property-read ?Lesson $lesson
 * @property-read ?Batch $batch
 */
class LessonProgress extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'ulid',
        'user_id',
        'lesson_id',
        'batch_id',
        'is_completed',
        'watch_percentage',
        'last_position_seconds',
        'completed_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'watch_percentage' => 'integer',
            'last_position_seconds' => 'integer',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
