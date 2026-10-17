<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ContentBlockType;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonContent extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'lesson_id',
        'type',
        'body',
        'media_path',
        'external_url',
        'duration_seconds',
        'sort_order',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContentBlockType::class,
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
