<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\Branch;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Physical premises (Ashram, Retreat Centre, City Studio).
 *
 * @property int $id
 * @property string $ulid
 * @property int $branch_id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property ?string $address_line_1
 * @property ?string $address_line_2
 * @property ?string $city
 * @property ?string $state
 * @property string $country
 * @property ?string $postal_code
 * @property string $timezone
 * @property bool $is_active
 * @property-read ?Branch $branch
 * @property-read Collection<int, Room> $rooms
 * @property-read Collection<int, Batch> $batches
 * @property-read Collection<int, Session> $sessions
 */
class Venue extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Venue $venue): void {
            if (empty($venue->slug) && ! empty($venue->name)) {
                $venue->slug = Str::slug($venue->name);
            }
        });
    }

    protected $fillable = [
        'ulid',
        'branch_id',
        'name',
        'slug',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'is_active',
        'code',
        'address',
        'capacity',
    ];

    public function setCapacityAttribute(mixed $value): void
    {
        // virtual / premise capacity
    }

    public function setAddressAttribute(mixed $value): void
    {
        $this->attributes['address_line_1'] = is_string($value) ? $value : '';
    }

    public function setCodeAttribute(mixed $value): void
    {
        if (empty($this->attributes['slug']) && ! empty($value)) {
            $this->attributes['slug'] = (string) $value;
        }
    }

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<Batch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
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
