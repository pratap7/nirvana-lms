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
 * @property int $id
 * @property string $ulid
 * @property string $certificate_number
 * @property int $user_id
 * @property int $course_id
 * @property ?int $batch_id
 * @property CarbonInterface $issue_date
 * @property string $template_name
 * @property ?array<string, mixed> $metadata
 * @property-read ?User $user
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 */
class Certificate extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'certificate_number',
        'user_id',
        'course_id',
        'batch_id',
        'issue_date',
        'template_name',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'metadata' => 'array',
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
}
