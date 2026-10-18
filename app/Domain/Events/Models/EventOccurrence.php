<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\OccurrenceStatus;
use App\Domain\Scheduling\Contracts\Attendable;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Domain\Scheduling\Models\Venue;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Event Occurrence instance (supports individual occurrence cancellation for recurring series).
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property int $occurrence_number
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property ?int $venue_id
 * @property ?int $room_id
 * @property ?string $online_meeting_link
 * @property OccurrenceStatus $status
 * @property ?string $cancellation_reason
 * @property ?CarbonInterface $cancelled_at
 * @property-read ?Event $event
 * @property-read ?Venue $venue
 * @property-read ?Room $room
 * @property-read Collection<int, EventRegistration> $registrations
 * @property-read Collection<int, SessionAttendance> $attendances
 */
class EventOccurrence extends Model implements Attendable
{
    use HasFactory;
    use HasUlid;

    protected $table = 'event_occurrences';

    protected $fillable = [
        'ulid',
        'event_id',
        'occurrence_number',
        'starts_at',
        'ends_at',
        'venue_id',
        'room_id',
        'online_meeting_link',
        'status',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatus::class,
            'occurrence_number' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Reuses SessionAttendance via polymorphic relation.
     *
     * @return MorphMany<SessionAttendance, $this>
     */
    public function attendances(): MorphMany
    {
        return $this->morphMany(SessionAttendance::class, 'attendable');
    }

    // ──────────────────────────────────────────────
    // Attendable Interface Implementation
    // ──────────────────────────────────────────────

    public function getAttendableTitle(): string
    {
        return "{$this->event?->title} (Session #{$this->occurrence_number})";
    }

    public function getStartsAt(): CarbonInterface
    {
        return $this->starts_at;
    }

    public function getEndsAt(): CarbonInterface
    {
        return $this->ends_at;
    }

    // ──────────────────────────────────────────────
    // Occurrence Actions
    // ──────────────────────────────────────────────

    /**
     * Individually cancel this occurrence without affecting other occurrences in the recurring series.
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => OccurrenceStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }
}
