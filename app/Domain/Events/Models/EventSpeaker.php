<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event Speaker / Guest Teacher profile.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property ?int $user_id
 * @property string $name
 * @property ?string $title
 * @property ?string $bio
 * @property ?string $photo_url
 * @property bool $is_lead
 * @property ?array<string, mixed> $social_links
 * @property-read ?Event $event
 * @property-read ?User $user
 */
class EventSpeaker extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'event_speakers';

    protected $fillable = [
        'ulid',
        'event_id',
        'user_id',
        'name',
        'title',
        'bio',
        'photo_url',
        'is_lead',
        'social_links',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'social_links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
