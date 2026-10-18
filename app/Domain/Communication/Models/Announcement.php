<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\Branch;
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
 * @property ?int $branch_id
 * @property ?int $course_id
 * @property ?int $batch_id
 * @property int $author_id
 * @property string $title
 * @property string $content
 * @property string $priority
 * @property ?CarbonInterface $published_at
 * @property-read ?Branch $branch
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?User $author
 */
class Announcement extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'branch_id',
        'course_id',
        'batch_id',
        'author_id',
        'title',
        'content',
        'priority',
        'published_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
