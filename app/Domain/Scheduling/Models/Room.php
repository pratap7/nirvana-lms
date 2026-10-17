<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Classrooms, Yoga Shalas, and Meditation Studios within a Venue with finite capacity.
 *
 * @property int $id
 * @property string $ulid
 * @property int $venue_id
 * @property string $name
 * @property int $capacity
 * @property ?string $floor
 * @property string $room_type
 * @property ?array<string> $amenities
 * @property bool $is_active
 * @property-read ?Venue $venue
 * @property-read Collection<int, Session> $sessions
 */
class Room extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'venue_id',
        'name',
        'capacity',
        'floor',
        'room_type',
        'amenities',
        'is_active',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
