<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ResourceType;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Downloadable assets, PDFs, audio guides, or external resources attached to a lesson.
 *
 * @property int $id
 * @property string $ulid
 * @property int $lesson_id
 * @property string $title
 * @property ResourceType $type
 * @property string $url_or_path
 * @property ?int $file_size_bytes
 * @property int $sort_order
 * @property-read ?Lesson $lesson
 */
class LessonResource extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'lesson_id',
        'title',
        'type',
        'url_or_path',
        'file_size_bytes',
        'sort_order',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'file_size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
