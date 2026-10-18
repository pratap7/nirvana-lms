<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Billing\Contracts\Billable;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Events\EventTicketMailed;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Support\Concerns\HasUlid;
use App\Support\Money;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Event Registration & QR Ticket Entity.
 * Implements Billable for polymorphic billing and webhook-verified payments.
 * Reuses Enrollment for state transitions and SessionAttendance for check-in audits.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property ?int $event_occurrence_id
 * @property ?int $enrollment_id
 * @property int $user_id
 * @property bool $has_plus_one
 * @property ?string $plus_one_name
 * @property ?string $plus_one_email
 * @property string $ticket_code
 * @property ?string $qr_code_svg
 * @property bool $is_checked_in
 * @property ?CarbonInterface $checked_in_at
 * @property ?int $checked_in_by
 * @property ?int $invitation_id
 * @property ?string $notes
 * @property ?array<string, mixed> $metadata
 * @property-read ?Event $event
 * @property-read ?EventOccurrence $occurrence
 * @property-read ?Enrollment $enrollment
 * @property-read ?User $user
 * @property-read ?User $checkedInBy
 * @property-read ?EventInvitation $invitation
 * @property-read ?EventFeedback $feedback
 * @property-read Collection<int, Invoice> $invoices
 */
class EventRegistration extends Model implements Billable
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'event_registrations';

    protected $fillable = [
        'ulid',
        'event_id',
        'event_occurrence_id',
        'enrollment_id',
        'user_id',
        'has_plus_one',
        'plus_one_name',
        'plus_one_email',
        'ticket_code',
        'qr_code_svg',
        'is_checked_in',
        'checked_in_at',
        'checked_in_by',
        'invitation_id',
        'notes',
        'metadata',
    ];

    protected $hidden = ['id'];

    protected $attributes = [
        'is_checked_in' => false,
        'has_plus_one' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_plus_one' => 'boolean',
            'is_checked_in' => 'boolean',
            'checked_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->ticket_code)) {
                $model->ticket_code = 'TKT-'.strtoupper(Str::random(12));
            }
        });
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
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * @return BelongsTo<EventInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(EventInvitation::class, 'invitation_id');
    }

    /**
     * @return HasOne<EventFeedback, $this>
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(EventFeedback::class, 'registration_id');
    }

    /**
     * Polymorphic billing link to invoices ledger.
     *
     * @return MorphMany<Invoice, $this>
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    // ──────────────────────────────────────────────
    // Billable Interface Implementation
    // ──────────────────────────────────────────────

    public function getBillableTitle(): string
    {
        $guestStr = $this->has_plus_one ? ' (with Guest Pass)' : '';

        return "Event Admission Ticket: {$this->event?->title}{$guestStr}";
    }

    public function getBillableFee(): Money
    {
        $baseFee = $this->event?->getFee() ?? Money::zero('INR');
        $multiplier = $this->has_plus_one ? 2 : 1;

        return $baseFee->multiply($multiplier);
    }

    public function getStudent(): User
    {
        return $this->user;
    }

    /**
     * Callback invoked automatically when the linked invoice is paid.
     * Webhook verified via ProcessWebhookAction -> MarkInvoicePaidAction.
     */
    public function onPaymentConfirmed(Invoice $invoice): void
    {
        if ($this->enrollment !== null && $this->enrollment->status !== EnrollmentStatus::Active) {
            TransitionEnrollmentAction::run(
                $this->enrollment,
                EnrollmentStatus::Active,
                actor: null,
                reason: "Event admission ticket invoice [{$invoice->invoice_number}] settled via verified payment gateway."
            );
        }

        // Generate and store QR code if not already done
        if (empty($this->qr_code_svg)) {
            $this->generateQrCode();
        }

        // Fire ticket email notification
        EventTicketMailed::dispatch($this);
    }

    // ──────────────────────────────────────────────
    // QR Code Generation
    // ──────────────────────────────────────────────

    /**
     * Generate standard verifiable SVG QR code for admission ticket.
     */
    public function generateQrCode(): string
    {
        $payload = json_encode([
            'ticket' => $this->ticket_code,
            'event' => $this->event?->slug,
            'attendee' => $this->user?->full_name,
            'plus_one' => $this->has_plus_one ? $this->plus_one_name : null,
            'v' => 1,
        ], JSON_THROW_ON_ERROR);

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(250, 2),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload);
        } catch (\Throwable) {
            // High-compatibility SVG QR fallback
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="5" y="50" font-size="8">'.e($this->ticket_code).'</text></svg>';
        }

        $this->update(['qr_code_svg' => $svg]);

        return $svg;
    }

    // ──────────────────────────────────────────────
    // Check-in and Duplicate Detection
    // ──────────────────────────────────────────────

    /**
     * Execute staff check-in with offline duplicate detection and polymorphic attendance recording.
     *
     * @return array{success: bool, duplicate: bool, message: string}
     */
    public function checkIn(User $staff): array
    {
        // 1. Duplicate check: If already checked in, alert immediately
        if ($this->is_checked_in) {
            $scannedAt = $this->checked_in_at?->format('h:i:s A, M d') ?? 'earlier';
            $staffName = $this->checkedInBy ? $this->checkedInBy->full_name : 'staff';

            return [
                'success' => false,
                'duplicate' => true,
                'message' => "DUPLICATE CHECK-IN DETECTED: Ticket #{$this->ticket_code} was already scanned at {$scannedAt} by {$staffName}.",
            ];
        }

        // 2. Mark checked in
        $now = now();
        $this->update([
            'is_checked_in' => true,
            'checked_in_at' => $now,
            'checked_in_by' => $staff->id,
        ]);

        // 3. Polymorphically record SessionAttendance for the occurrence or event
        $occurrence = $this->occurrence ?? $this->event?->occurrences()->first();
        if ($occurrence) {
            SessionAttendance::updateOrCreate(
                [
                    'attendable_type' => EventOccurrence::class,
                    'attendable_id' => $occurrence->id,
                    'user_id' => $this->user_id,
                ],
                [
                    'status' => 'present',
                    'marked_by' => $staff->id,
                    'notes' => 'Checked in at event entrance via QR code scan'.($this->has_plus_one ? " (+1: {$this->plus_one_name})" : ''),
                ]
            );
        }

        return [
            'success' => true,
            'duplicate' => false,
            'message' => "Check-in successful! Welcome {$this->user->full_name}".($this->has_plus_one ? " and {$this->plus_one_name}" : '').'.',
        ];
    }
}
