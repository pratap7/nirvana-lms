<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\ValueObjects\CompletionRule;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasStateMachine;
use App\Support\Concerns\HasUlid;
use App\Support\Concerns\ScopedByBranch;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property ?int $category_id
 * @property ?int $program_id
 * @property int $branch_id
 * @property string $name
 * @property string $title
 * @property string $slug
 * @property CourseType $type
 * @property DeliveryMode $default_delivery_mode
 * @property ?string $short_description
 * @property ?string $long_description
 * @property ?string $description
 * @property ?array<string> $outcomes
 * @property ?string $prerequisites_text
 * @property ?string $thumbnail_path
 * @property ?string $cover_image_path
 * @property DifficultyLevel $difficulty_level
 * @property int $duration_hours
 * @property ?int $max_students
 * @property ?int $enrollment_limit
 * @property ?CarbonInterface $start_date
 * @property ?CarbonInterface $end_date
 * @property int $base_fee_amount
 * @property string $base_fee_currency
 * @property CourseStatus $status
 * @property ?Carbon $published_at
 * @property ?Carbon $cancelled_at
 * @property ?string $cancellation_reason
 * @property string $seo_title
 * @property ?string $seo_description
 * @property string $language
 * @property float $rating_average
 * @property int $reviews_count
 * @property int $sort_order
 * @property ?array<string, mixed> $metadata
 * @property ?CompletionRule $completion_rules
 * @property int $created_by
 * @property ?int $approved_by
 * @property-read ?Category $category
 * @property-read ?Program $program
 * @property-read Branch $branch
 * @property-read User $creator
 * @property-read ?User $approver
 * @property-read Collection<int, Module> $modules
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, User> $teachers
 * @property-read Collection<int, CourseReview> $reviews
 * @property-read ?CompletionRuleModel $completionRuleRecord
 */
class Course extends Model implements HasMedia
{
    use HasFactory;
    use HasStateMachine;
    use HasUlid;
    use InteractsWithMedia;
    use LogsActivity;
    use ScopedByBranch;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'program_id',
        'category_id',
        'branch_id',
        'name',
        'slug',
        'type',
        'default_delivery_mode',
        'short_description',
        'long_description',
        'outcomes',
        'prerequisites_text',
        'thumbnail_path',
        'cover_image_path',
        'difficulty_level',
        'duration_hours',
        'max_students',
        'enrollment_limit',
        'start_date',
        'end_date',
        'base_fee_amount',
        'base_fee_currency',
        'status',
        'published_at',
        'cancelled_at',
        'cancellation_reason',
        'seo_title',
        'seo_description',
        'language',
        'rating_average',
        'reviews_count',
        'sort_order',
        'metadata',
        'completion_rules',
        'created_by',
        'approved_by',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
            'default_delivery_mode' => DeliveryMode::class,
            'difficulty_level' => DifficultyLevel::class,
            'status' => CourseStatus::class,
            'outcomes' => 'array',
            'duration_hours' => 'integer',
            'max_students' => 'integer',
            'enrollment_limit' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'base_fee_amount' => 'integer',
            'rating_average' => 'float',
            'reviews_count' => 'integer',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'completion_rules' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Accessors & Mutators
    // ──────────────────────────────────────────────

    public function getTitleAttribute(): string
    {
        return $this->name;
    }

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->long_description ?? $this->short_description;
    }

    public function getFeeAttribute(): Money
    {
        return Money::fromMinor($this->base_fee_amount, $this->base_fee_currency);
    }

    public function setFeeAttribute(Money $money): void
    {
        $this->attributes['base_fee_amount'] = $money->getMinorAmount();
        $this->attributes['base_fee_currency'] = $money->getCurrency();
    }

    public function getCompletionRulesAttribute(?string $value): CompletionRule
    {
        if (empty($value)) {
            return CompletionRule::defaults();
        }

        return CompletionRule::fromJson($value);
    }

    public function setCompletionRulesAttribute(CompletionRule|array|string|null $value): void
    {
        if ($value instanceof CompletionRule) {
            $this->attributes['completion_rules'] = $value->toJson();
        } elseif (is_array($value)) {
            $this->attributes['completion_rules'] = (string) json_encode($value);
        } else {
            $this->attributes['completion_rules'] = $value;
        }
    }

    // ──────────────────────────────────────────────
    // Media Library
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery');
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    /**
     * @return HasManyThrough<Lesson, Module, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_teachers')
            ->withPivot('role', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<CourseReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    /**
     * @return HasMany<CourseReview, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(CourseReview::class)->approved();
    }

    /**
     * @return HasOne<CompletionRuleModel, $this>
     */
    public function completionRuleRecord(): HasOne
    {
        return $this->hasOne(CompletionRuleModel::class);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id',
        )->withPivot('is_mandatory');
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id',
        );
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', CourseStatus::Published);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ──────────────────────────────────────────────
    // Curriculum Versioning & Progress Calculation
    // ──────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    /**
     * Fetch active, non-soft-deleted, published, required lessons for progress calculation.
     * Curriculum versioning rule: progress is calculated only over currently published, required lessons.
     *
     * @return Collection<int, Lesson>
     */
    public function getPublishedRequiredLessons()
    {
        return Lesson::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $this->id))
            ->where('is_published', true)
            ->where('is_mandatory', true)
            ->get();
    }

    /**
     * Calculate student progress (0% - 100%) against currently published required lessons.
     * Reordering lessons never alters this calculation.
     *
     * @param  array<int|string>  $completedLessonIds
     */
    public function calculateProgressFor(array $completedLessonIds): float
    {
        $requiredLessons = $this->getPublishedRequiredLessons();
        $totalRequired = $requiredLessons->count();

        if ($totalRequired === 0) {
            return 100.0;
        }

        $completedSet = array_flip(array_map('strval', $completedLessonIds));
        $completedCount = 0;

        foreach ($requiredLessons as $lesson) {
            $key = (string) $lesson->id;
            if (array_key_exists($key, $completedSet)) {
                $completedCount++;
            }
        }

        return round(($completedCount / $totalRequired) * 100, 2);
    }

    public function refreshRatingCache(): void
    {
        $approvedReviews = $this->approvedReviews();
        $count = $approvedReviews->count();
        $avg = $count > 0 ? (float) $approvedReviews->avg('rating') : 0.00;

        $this->updateQuietly([
            'rating_average' => round($avg, 2),
            'reviews_count' => $count,
        ]);
    }

    // ──────────────────────────────────────────────
    // Activity Log
    // ──────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'base_fee_amount', 'published_at', 'cancelled_at', 'approved_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
