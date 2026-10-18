<?php

declare(strict_types=1);

namespace App\Domain\Certification\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Certificate Template with drag-and-drop field placement and institute branding.
 *
 * @property int $id
 * @property string $ulid
 * @property ?int $branch_id
 * @property int $created_by
 * @property string $name
 * @property string $slug
 * @property ?int $course_id
 * @property ?int $event_id
 * @property bool $is_default
 * @property ?array<string, mixed> $layout
 * @property ?array<int, array<string, mixed>> $fields
 * @property ?array<string, mixed> $branding
 * @property-read ?Branch $branch
 * @property-read ?User $creator
 * @property-read ?Course $course
 * @property-read ?Event $event
 * @property-read Collection<int, Certificate> $certificates
 */
class CertificateTemplate extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'certificate_templates';

    protected $fillable = [
        'ulid',
        'branch_id',
        'created_by',
        'name',
        'slug',
        'course_id',
        'event_id',
        'is_default',
        'layout',
        'fields',
        'branding',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'layout' => 'array',
            'fields' => 'array',
            'branding' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    /**
     * Get default drag-and-drop fields config if not customized.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEffectiveFields(): array
    {
        if (! empty($this->fields)) {
            return $this->fields;
        }

        return [
            [
                'id' => 'institute_name',
                'label' => 'Institute Header',
                'x' => 561,
                'y' => 140,
                'font_size' => 20,
                'color' => '#78350f',
                'align' => 'center',
            ],
            [
                'id' => 'student_name',
                'label' => 'Recipient Name',
                'x' => 561,
                'y' => 330,
                'font_size' => 34,
                'color' => '#1c1917',
                'font_family' => 'serif',
                'align' => 'center',
            ],
            [
                'id' => 'course_title',
                'label' => 'Course Title / Conferment',
                'x' => 561,
                'y' => 430,
                'font_size' => 24,
                'color' => '#451a03',
                'align' => 'center',
            ],
            [
                'id' => 'issue_date',
                'label' => 'Conferment Date',
                'x' => 260,
                'y' => 620,
                'font_size' => 13,
                'color' => '#57534e',
                'align' => 'left',
            ],
            [
                'id' => 'certificate_number',
                'label' => 'Certificate Identifier',
                'x' => 260,
                'y' => 645,
                'font_size' => 12,
                'color' => '#78716c',
                'align' => 'left',
            ],
            [
                'id' => 'qr_code',
                'label' => 'Verification QR Code',
                'x' => 840,
                'y' => 570,
                'width' => 110,
                'height' => 110,
            ],
            [
                'id' => 'signature',
                'label' => 'Acharya Signatory',
                'x' => 561,
                'y' => 610,
                'title' => 'Director & Head of Faculty',
                'align' => 'center',
            ],
        ];
    }
}
