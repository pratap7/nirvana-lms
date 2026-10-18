<?php

declare(strict_types=1);

namespace App\Domain\Certification\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Enums\CertificateStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Certificate Model (Section G).
 * Stores human-readable certificate number, random public token, rules snapshot,
 * revocation/reissue audit trails, and Open Badges / LinkedIn links.
 *
 * @property int $id
 * @property string $ulid
 * @property string $certificate_number
 * @property string $public_token
 * @property int $user_id
 * @property ?int $course_id
 * @property ?int $batch_id
 * @property ?int $event_id
 * @property ?string $certifiable_type
 * @property ?int $certifiable_id
 * @property ?int $template_id
 * @property CarbonInterface $issue_date
 * @property CertificateStatus $status
 * @property ?CarbonInterface $revoked_at
 * @property ?int $revoked_by
 * @property ?string $revocation_reason
 * @property ?int $original_certificate_id
 * @property ?int $reissued_certificate_id
 * @property string $template_name
 * @property ?string $pdf_path
 * @property ?string $qr_code_svg
 * @property ?array<string, mixed> $rules_snapshot
 * @property ?array<string, mixed> $evaluation_snapshot
 * @property ?array<string, mixed> $metadata
 * @property-read ?User $user
 * @property-read ?Course $course
 * @property-read ?Batch $batch
 * @property-read ?Event $event
 * @property-read ?CertificateTemplate $template
 * @property-read ?User $revokedBy
 * @property-read ?Certificate $originalCertificate
 * @property-read ?Certificate $reissuedCertificate
 */
class Certificate extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $table = 'certificates';

    protected $fillable = [
        'ulid',
        'certificate_number',
        'public_token',
        'user_id',
        'course_id',
        'batch_id',
        'event_id',
        'certifiable_type',
        'certifiable_id',
        'template_id',
        'issue_date',
        'status',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'original_certificate_id',
        'reissued_certificate_id',
        'template_name',
        'pdf_path',
        'qr_code_svg',
        'rules_snapshot',
        'evaluation_snapshot',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'issue_date' => 'date',
            'revoked_at' => 'datetime',
            'rules_snapshot' => 'array',
            'evaluation_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->public_token)) {
                $model->public_token = 'cert_'.Str::random(40);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['certificate_number', 'status', 'revoked_at', 'revocation_reason', 'reissued_certificate_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Certificate was {$eventName}");
    }

    public function getIssuedAtAttribute(): ?CarbonInterface
    {
        return $this->issue_date;
    }

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function originalCertificate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_certificate_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reissuedCertificate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissued_certificate_id');
    }

    /**
     * Polymorphic relation to any Certifiable target (Course, Event, etc.)
     *
     * @return MorphTo<Model, $this>
     */
    public function certifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────────────────────────
    // Helpers & Lifecycle Actions
    // ──────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->status === CertificateStatus::Valid;
    }

    public function isRevoked(): bool
    {
        return $this->status === CertificateStatus::Revoked;
    }

    public function isReissued(): bool
    {
        return $this->status === CertificateStatus::Reissued;
    }

    public function getVerificationUrl(): string
    {
        return route('certificates.verify', ['publicToken' => $this->public_token]);
    }

    public function getTargetTitle(): string
    {
        if ($this->course) {
            return $this->course->title;
        }

        if ($this->event) {
            return $this->event->title;
        }

        return (string) ($this->metadata['title'] ?? 'Program of Sadhana');
    }

    /**
     * Revoke this certificate with reason and audit trail.
     */
    public function revoke(string $reason, User $revokedBy): void
    {
        $this->update([
            'status' => CertificateStatus::Revoked,
            'revocation_reason' => $reason,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy->id,
        ]);
    }

    /**
     * Reissue this certificate with reason, linking old and new credentials.
     */
    public function reissue(string $reason, User $reissuedBy, ?string $newNumber = null): self
    {
        $year = now()->format('Y');
        $seq = strtoupper(Str::random(6));
        $newCertNumber = $newNumber ?? "YOG-{$year}-{$seq}";

        /** @var self $newCert */
        $newCert = self::create([
            'certificate_number' => $newCertNumber,
            'public_token' => 'cert_'.Str::random(40),
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'batch_id' => $this->batch_id,
            'event_id' => $this->event_id,
            'certifiable_type' => $this->certifiable_type,
            'certifiable_id' => $this->certifiable_id,
            'template_id' => $this->template_id,
            'issue_date' => now()->toDateString(),
            'status' => CertificateStatus::Valid,
            'original_certificate_id' => $this->id,
            'template_name' => $this->template_name,
            'rules_snapshot' => $this->rules_snapshot,
            'evaluation_snapshot' => $this->evaluation_snapshot,
            'metadata' => array_merge($this->metadata ?? [], [
                'reissue_reason' => $reason,
                'reissued_from' => $this->certificate_number,
            ]),
        ]);

        $newCert->generateQrCode();

        // Mark current certificate as Reissued and link to the new credential
        $this->update([
            'status' => CertificateStatus::Reissued,
            'revocation_reason' => "Reissued: {$reason}",
            'revoked_at' => now(),
            'revoked_by' => $reissuedBy->id,
            'reissued_certificate_id' => $newCert->id,
        ]);

        return $newCert;
    }

    /**
     * Generate standard verifiable SVG QR code linking to public verification page.
     */
    public function generateQrCode(): string
    {
        $url = $this->getVerificationUrl();

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(200, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($url);
        } catch (\Throwable) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="5" y="50" font-size="8">'.e($this->certificate_number).'</text></svg>';
        }

        $this->update(['qr_code_svg' => $svg]);

        return $svg;
    }

    /**
     * Generate LinkedIn "Add to Profile" direct integration URL.
     */
    public function getLinkedInShareUrl(): string
    {
        $params = http_build_query([
            'startTask' => 'CERTIFICATION_NAME',
            'name' => $this->getTargetTitle(),
            'organizationName' => config('app.name', 'Pramana Institute of Yoga & Vedanta'),
            'issueYear' => $this->issue_date->format('Y'),
            'issueMonth' => $this->issue_date->format('n'),
            'certUrl' => $this->getVerificationUrl(),
            'certId' => $this->certificate_number,
        ]);

        return 'https://www.linkedin.com/profile/add?'.$params;
    }

    /**
     * Generate 1EdTech / W3C Open Badges 2.0 / 3.0 standard verifiable JSON-LD assertion.
     *
     * @return array<string, mixed>
     */
    public function getOpenBadgesJson(): array
    {
        $userEmail = $this->user ? $this->user->email : '';
        $recipientHash = 'sha256$'.hash('sha256', strtolower(trim($userEmail)));

        return [
            '@context' => 'https://w3id.org/openbadges/v2',
            'type' => 'Assertion',
            'id' => $this->getVerificationUrl().'/badge.json',
            'recipient' => [
                'type' => 'email',
                'hashed' => true,
                'identity' => $recipientHash,
            ],
            'issuedOn' => $this->issue_date->toIso8601String(),
            'badge' => [
                'type' => 'BadgeClass',
                'id' => config('app.url').'/badges/'.Str::slug($this->getTargetTitle()),
                'name' => $this->getTargetTitle(),
                'description' => "Verified certification of competence in {$this->getTargetTitle()} granted by Pramana Institute.",
                'image' => config('app.url').'/images/badges/classical-yoga-seal.png',
                'criteria' => [
                    'narrative' => 'Recipient demonstrated mastery of required sadhana, attendance thresholds, and assessment rubrics.',
                ],
                'issuer' => [
                    'type' => 'Profile',
                    'id' => config('app.url').'/issuer.json',
                    'name' => config('app.name', 'Pramana Institute of Yoga & Vedanta'),
                    'url' => config('app.url'),
                    'email' => 'academics@pramana.org',
                ],
            ],
            'verification' => [
                'type' => 'HostedBadge',
                'url' => $this->getVerificationUrl(),
            ],
            'status' => $this->status->value,
            'revoked' => $this->status === CertificateStatus::Revoked,
            'revocationReason' => $this->revocation_reason,
        ];
    }
}
