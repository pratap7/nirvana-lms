<?php

declare(strict_types=1);

namespace App\Domain\Cms\Models;

use App\Domain\Catalog\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $author_name
 * @property ?string $author_title
 * @property ?string $author_location
 * @property ?string $avatar_path
 * @property int $rating
 * @property string $quote
 * @property ?int $course_id
 * @property bool $is_featured
 * @property int $sort_order
 * @property string $locale
 * @property-read ?Course $course
 */
class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_name',
        'author_title',
        'author_location',
        'avatar_path',
        'rating',
        'quote',
        'course_id',
        'is_featured',
        'sort_order',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @param  Builder<Testimonial>  $query
     * @return Builder<Testimonial>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)->orderBy('sort_order');
    }

    /**
     * @param  Builder<Testimonial>  $query
     * @return Builder<Testimonial>
     */
    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        $targetLocale = $locale ?? app()->getLocale();

        return $query->where('locale', $targetLocale);
    }
}
