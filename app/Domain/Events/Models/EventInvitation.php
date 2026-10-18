<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Event Invitation for invitation-only admission.
 * Generates signed single-use links and tracks expiration.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $email
 * @property string $name
 * @property string $token
 * @property string $status
 * @property ?CarbonInterface $used_at
 * @property ?CarbonInterface $expires_at
 * @property-read ?Event $event
 * @property-read Collection<int, EventRegistration> $registrations
 */
class EventInvitation extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'event_invitations';

    protected $fillable = [
        'ulid',
        'event_id',
        'email',
        'name',
        'token',
        'status',
        'used_at',
        'expires_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->token)) {
                $model->token = Str::random(48);
            }
        });
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'invitation_id');
    }

    public function isValid(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function markUsed(): void
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
        ]);
    }

    /**
     * Generate a tamper-proof signed single-use invitation URL.
     */
    public function getSignedInviteUrl(): string
    {
        $expiration = $this->expires_at ?? now()->addDays(14);

        return URL::temporarySignedRoute(
            'events.invite',
            $expiration,
            [
                'event' => $this->event->slug,
                'token' => $this->token,
            ]
        );
    }
}
