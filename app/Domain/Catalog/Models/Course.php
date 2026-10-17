<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasStateMachine;
use App\Support\Concerns\HasUlid;
use App\Support\Concerns\ScopedByBranch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property CourseStatus $status
 * @property int $base_fee_amount
 * @property string $base_fee_currency
 * @property ?Carbon $published_at
 * @property ?int $approved_by
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
        'branch_id',
        'name',
        'slug',
        'short_description',
        'long_description',
        'thumbnail_path',
        'difficulty_level',
        'duration_hours',
        'max_students',
        'base_fee_amount',
        'base_fee_currency',
        'status',
        'published_at',
        'sort_order',
        'metadata',
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
            'difficulty_level' => DifficultyLevel::class,
            'status' => CourseStatus::class,
            'duration_hours' => 'integer',
            'max_students' => 'integer',
            'base_fee_amount' => 'integer',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Media Library
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery');
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id',
        )->withPivot('is_mandatory');
    }

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
    // Helpers
    // ──────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    public function getTotalLessonsCount(): int
    {
        return $this->lessons()->count();
    }

    // ──────────────────────────────────────────────
    // Activity Log
    // ──────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'base_fee_amount', 'published_at', 'approved_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
