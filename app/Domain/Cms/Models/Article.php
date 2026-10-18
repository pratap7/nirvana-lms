<?php

declare(strict_types=1);

namespace App\Domain\Cms\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property ?int $author_id
 * @property string $title
 * @property string $slug
 * @property ?string $excerpt
 * @property string $content
 * @property string $category
 * @property ?array<string> $tags
 * @property ?string $featured_image_path
 * @property string $status
 * @property ?CarbonInterface $published_at
 * @property string $locale
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property int $reading_time_minutes
 * @property int $view_count
 * @property bool $is_featured
 * @property-read ?User $author
 */
class Article extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'tags',
        'featured_image_path',
        'status',
        'published_at',
        'locale',
        'seo_title',
        'seo_description',
        'reading_time_minutes',
        'view_count',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'immutable_datetime',
            'is_featured' => 'boolean',
            'reading_time_minutes' => 'integer',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
            if (empty($article->reading_time_minutes) && ! empty($article->content)) {
                $words = str_word_count(strip_tags($article->content));
                $article->reading_time_minutes = max(1, (int) ceil($words / 200));
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        $targetLocale = $locale ?? app()->getLocale();

        return $query->where('locale', $targetLocale);
    }
}
