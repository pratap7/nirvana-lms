<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\MeetingProviderType;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Individual session / class occurrence within a Batch.
 *
 * @property int $id
 * @property string $ulid
 * @property int $batch_id
 * @property string $title
 * @property string $slug
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property string $timezone
 * @property ?int $venue_id
 * @property ?int $room_id
 * @property bool $is_online
 * @property MeetingProviderType $meeting_provider
 * @property ?string $meeting_link
 * @property ?string $meeting_id
 * @property ?string $meeting_password
 * @property SessionStatus $status
 * @property ?string $cancellation_reason
 * @property ?int $rescheduled_from_id
 * @property ?string $recording_url
 * @property-read ?Batch $batch
 * @property-read ?Venue $venue
 * @property-read ?Room $room
 * @property-read Collection<int, SessionTeacher> $sessionTeachers
 * @property-read Collection<int, User> $teachers
 * @property-read Collection<int, SessionRecording> $recordings
 */
class Session extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Session $session): void {
            if (empty($session->slug) && ! empty($session->title)) {
                $session->slug = Str::slug($session->title).'-'.Str::lower(Str::random(6));
            }
        });
    }

    protected $table = 'course_sessions';

    protected $fillable = [
        'ulid',
        'batch_id',
        'title',
        'slug',
        'starts_at',
        'ends_at',
        'timezone',
        'venue_id',
        'room_id',
        'is_online',
        'meeting_provider',
        'meeting_link',
        'meeting_id',
        'meeting_password',
        'status',
        'cancellation_reason',
        'rescheduled_from_id',
        'recording_url',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'meeting_provider' => MeetingProviderType::class,
            'status' => SessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
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
     * @return BelongsTo<Session, $this>
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    /**
     * @return HasMany<SessionTeacher, $this>
     */
    public function sessionTeachers(): HasMany
    {
        return $this->hasMany(SessionTeacher::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'session_teachers')
            ->withPivot('role', 'status', 'requested_by', 'approved_by', 'reason')
            ->withTimestamps();
    }

    /**
     * @return HasMany<SessionRecording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(SessionRecording::class);
    }

    public function getMeetingUrlAttribute(): ?string
    {
        return $this->meeting_link;
    }

    public function getMeetingPasscodeAttribute(): ?string
    {
        return $this->meeting_password;
    }

    public function getActiveTeacher(): ?User
    {
        // 1. Check approved substitute
        $substitute = $this->sessionTeachers()
            ->where('role', SessionTeacherRole::Substitute)
            ->where('status', SubstituteRequestStatus::Approved)
            ->with('teacher')
            ->first();

        if ($substitute && $substitute->teacher) {
            return $substitute->teacher;
        }

        // 2. Fall back to lead teacher
        $lead = $this->sessionTeachers()
            ->where('role', SessionTeacherRole::Lead)
            ->where('status', SubstituteRequestStatus::Approved)
            ->with('teacher')
            ->first();

        return $lead?->teacher;
    }

    public function durationInMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled;
    }

    public function isRescheduled(): bool
    {
        return $this->status === SessionStatus::Rescheduled;
    }

    /**
     * Generate RFC 5545 iCalendar VEVENT block.
     */
    public function toIcsEvent(): string
    {
        $dtStart = $this->starts_at->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtEnd = $this->ends_at->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtStamp = now()->setTimezone('UTC')->format('Ymd\THis\Z');
        $summary = addcslashes($this->title, ',;\\');
        $desc = addcslashes(($this->meeting_link ? "Virtual Link: {$this->meeting_link}\n" : '')."Batch: {$this->batch?->name}", ',;\\');
        $location = $this->is_online ? 'Online (Virtual Class)' : addcslashes("{$this->room?->name}, {$this->venue?->name}", ',;\\');
        $uid = "session-{$this->ulid}@pramana-institute.org";

        $lines = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$desc}",
            "LOCATION:{$location}",
            'STATUS:CONFIRMED',
            'END:VEVENT',
        ];

        return implode("\r\n", $lines);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'starts_at', 'ends_at', 'room_id', 'status', 'meeting_link', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
