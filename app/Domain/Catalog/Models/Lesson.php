<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\LessonContentType;
use App\Domain\Catalog\Enums\LessonDripType;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property int $module_id
 * @property string $title
 * @property string $slug
 * @property LessonContentType $content_type
 * @property ?int $duration_minutes
 * @property bool $is_free_preview
 * @property bool $is_mandatory
 * @property LessonDripType $drip_type
 * @property int $drip_days
 * @property bool $is_published
 * @property int $sort_order
 * @property ?array<string, mixed> $metadata
 * @property-read ?Module $module
 * @property-read Collection<int, LessonResource> $resources
 * @property-read Collection<int, LessonContent> $contents
 */
class Lesson extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'module_id',
        'title',
        'slug',
        'content_type',
        'duration_minutes',
        'is_free_preview',
        'is_mandatory',
        'drip_type',
        'drip_days',
        'is_published',
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
            'content_type' => LessonContentType::class,
            'drip_type' => LessonDripType::class,
            'drip_days' => 'integer',
            'duration_minutes' => 'integer',
            'is_free_preview' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<LessonContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(LessonContent::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<LessonResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('sort_order');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Check if lesson is drip-unlocked for a student.
     *
     * @param  array<int|string>  $completedLessonIds
     */
    public function isUnlockedFor(
        User $student,
        ?CarbonInterface $enrolledAt = null,
        array $completedLessonIds = [],
        ?self $previousLesson = null
    ): bool {
        // If preview lesson, always unlocked
        if ($this->is_free_preview) {
            return true;
        }

        return match ($this->drip_type) {
            LessonDripType::Immediate => true,
            LessonDripType::DaysAfterEnrollment => $enrolledAt !== null && now()->greaterThanOrEqualTo($enrolledAt->addDays($this->drip_days)),
            LessonDripType::AfterPreviousLesson => $previousLesson === null || in_array((string) $previousLesson->id, array_map('strval', $completedLessonIds), true),
        };
    }
}
