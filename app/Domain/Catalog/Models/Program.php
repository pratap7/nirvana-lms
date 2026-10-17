<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Identity\Models\Branch;
use App\Support\Concerns\HasUlid;
use App\Support\Concerns\ScopedByBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory;
    use HasUlid;
    use ScopedByBranch;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'branch_id',
        'name',
        'slug',
        'description',
        'duration_months',
        'status',
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
            'duration_months' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
