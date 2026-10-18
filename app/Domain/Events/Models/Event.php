<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Certification\Contracts\Certifiable;
use App\Domain\Enrollment\Contracts\Enrollable;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Enums\EventFormat;
use App\Domain\Events\Enums\EventPricingType;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Models\Venue;
use App\Support\Concerns\HasUlid;
use App\Support\Concerns\ScopedByBranch;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Event Domain Model (Section C).
 * Supports one-time, recurring (RRULE), free, paid, or invitation-only formats.
 * Reuses enrollment, billing, attendance, and certification components.
 *
 * @property int $id
 * @property string $ulid
 * @property int $branch_id
 * @property int $created_by
 * @property string $title
 * @property string $slug
 * @property ?string $description
 * @property ?string $cover_image_url
 * @property EventFormat $format
 * @property EventPricingType $pricing_type
 * @property int $fee_amount
 * @property string $fee_currency
 * @property bool $is_recurring
 * @property ?string $rrule
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property string $timezone
 * @property ?int $venue_id
 * @property ?int $room_id
 * @property ?string $online_meeting_link
 * @property int $capacity
 * @property ?CarbonInterface $registration_deadline
 * @property bool $allow_waitlist
 * @property bool $allow_plus_one
 * @property bool $issue_certificates
 * @property string $certificate_rule
 * @property EventStatus $status
 * @property ?array<string, mixed> $metadata
 * @property-read ?Branch $branch
 * @property-read ?User $creator
 * @property-read ?Venue $venue
 * @property-read ?Room $room
 * @property-read Collection<int, EventOccurrence> $occurrences
 * @property-read Collection<int, EventSpeaker> $speakers
 * @property-read Collection<int, EventInvitation> $invitations
 * @property-read Collection<int, EventRegistration> $registrations
 * @property-read Collection<int, EventFeedback> $feedbacks
 * @property-read Collection<int, Enrollment> $enrollments
 */
class Event extends Model implements Certifiable, Enrollable
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;
    use ScopedByBranch;
    use SoftDeletes;

    protected $table = 'events';

    protected $fillable = [
        'ulid',
        'branch_id',
        'created_by',
        'title',
        'slug',
        'description',
        'cover_image_url',
        'format',
        'pricing_type',
        'fee_amount',
        'fee_currency',
        'is_recurring',
        'rrule',
        'starts_at',
        'ends_at',
        'timezone',
        'venue_id',
        'room_id',
        'online_meeting_link',
        'capacity',
        'registration_deadline',
        'allow_waitlist',
        'allow_plus_one',
        'issue_certificates',
        'certificate_rule',
        'status',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => EventFormat::class,
            'pricing_type' => EventPricingType::class,
            'status' => EventStatus::class,
            'fee_amount' => 'integer',
            'capacity' => 'integer',
            'is_recurring' => 'boolean',
            'allow_waitlist' => 'boolean',
            'allow_plus_one' => 'boolean',
            'issue_certificates' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'starts_at', 'ends_at', 'capacity', 'fee_amount'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Event was {$eventName}");
    }

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * @return HasMany<EventOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class)->orderBy('starts_at');
    }

    /**
     * @return HasMany<EventSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class)->orderByDesc('is_lead');
    }

    /**
     * @return HasMany<EventInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * @return HasMany<EventFeedback, $this>
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'event_id');
    }

    // ──────────────────────────────────────────────
    // Enrollable Interface Implementation
    // ──────────────────────────────────────────────

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getEnrolledCount(): int
    {
        return $this->registrations()
            ->whereHas('enrollment', fn ($q) => $q->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Completed->value,
            ]))
            ->count();
    }

    public function allowsWaitlist(): bool
    {
        return $this->allow_waitlist;
    }

    public function requiresApproval(): bool
    {
        return $this->pricing_type === EventPricingType::InvitationOnly;
    }

    public function getFee(): Money
    {
        return Money::fromMinor($this->fee_amount, $this->fee_currency);
    }

    // ──────────────────────────────────────────────
    // Certifiable Interface Implementation
    // ──────────────────────────────────────────────

    public function getCertificateTitle(): string
    {
        return $this->title;
    }

    public function getCertificateRule(): string
    {
        return $this->certificate_rule;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isFree(): bool
    {
        return $this->pricing_type === EventPricingType::Free || $this->fee_amount === 0;
    }

    public function isPaid(): bool
    {
        return $this->pricing_type === EventPricingType::Paid && $this->fee_amount > 0;
    }

    public function isInvitationOnly(): bool
    {
        return $this->pricing_type === EventPricingType::InvitationOnly;
    }

    public function hasCapacityAvailable(int $seatsRequired = 1): bool
    {
        return ($this->getEnrolledCount() + $seatsRequired) <= $this->capacity;
    }

    /**
     * Generate Schema.org JSON-LD structured data.
     *
     * @return array<string, mixed>
     */
    public function getSchemaOrgJson(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $this->title,
            'description' => strip_tags($this->description ?? ''),
            'startDate' => $this->starts_at->toIso8601String(),
            'endDate' => $this->ends_at->toIso8601String(),
            'eventAttendanceMode' => $this->format->schemaAttendanceMode(),
            'eventStatus' => $this->status->schemaEventStatus(),
            'organizer' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Pramana Institute of Yoga & Vedanta'),
                'url' => config('app.url'),
            ],
        ];

        if ($this->cover_image_url) {
            $schema['image'] = [$this->cover_image_url];
        }

        if ($this->venue) {
            $schema['location'] = [
                '@type' => 'Place',
                'name' => $this->venue->name,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $this->venue->address ?? '',
                    'addressLocality' => $this->venue->city ?? '',
                    'addressCountry' => 'IN',
                ],
            ];
        } elseif ($this->format === EventFormat::Online) {
            $schema['location'] = [
                '@type' => 'VirtualLocation',
                'url' => $this->online_meeting_link ?? route('events.show', ['event' => $this->slug]),
            ];
        }

        if ($this->isPaid()) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $this->getFee()->toMajor(),
                'priceCurrency' => $this->fee_currency,
                'url' => route('events.show', ['event' => $this->slug]),
                'availability' => $this->hasCapacityAvailable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/SoldOut',
                'validFrom' => now()->toIso8601String(),
            ];
        } else {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => $this->fee_currency,
                'url' => route('events.show', ['event' => $this->slug]),
                'availability' => 'https://schema.org/InStock',
            ];
        }

        if ($this->speakers->isNotEmpty()) {
            $performers = [];
            foreach ($this->speakers as $speaker) {
                $performers[] = [
                    '@type' => 'Person',
                    'name' => $speaker->name,
                    'jobTitle' => $speaker->title ?? 'Speaker / Acharya',
                ];
            }
            $schema['performer'] = $performers;
        }

        return $schema;
    }

    /**
     * Generate standard RFC 5545 iCalendar (ICS) string.
     */
    public function generateIcsContent(): string
    {
        $dtStart = $this->starts_at->utc()->format('Ymd\THis\Z');
        $dtEnd = $this->ends_at->utc()->format('Ymd\THis\Z');
        $dtStamp = now()->utc()->format('Ymd\THis\Z');
        $uid = "event-{$this->ulid}@".parse_url(config('app.url'), PHP_URL_HOST);

        $summary = $this->cleanIcsText($this->title);
        $description = $this->cleanIcsText(strip_tags($this->description ?? ''));
        $location = $this->cleanIcsText($this->venue ? $this->venue->name.', '.($this->venue->address ?? '') : ($this->online_meeting_link ?? 'Online'));
        $url = route('events.show', ['event' => $this->slug]);

        $statusStr = $this->status === EventStatus::Cancelled ? 'CANCELLED' : 'CONFIRMED';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pramana Institute of Yoga & Vedanta//LMS Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            "LOCATION:{$location}",
            "STATUS:{$statusStr}",
            "URL:{$url}",
        ];

        if ($this->is_recurring && $this->rrule) {
            $lines[] = "RRULE:{$this->rrule}";
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function cleanIcsText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = str_replace(['\\', ',', ';'], ['\\\\', '\,', '\;'], $text);

        return trim($text);
    }
}
