<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit timeline entry tracking every state transition on an Enrollment.
 *
 * @property int $id
 * @property string $ulid
 * @property int $enrollment_id
 * @property ?string $from_status
 * @property string $to_status
 * @property ?int $changed_by
 * @property ?string $reason
 * @property ?array<string, mixed> $metadata
 * @property-read ?Enrollment $enrollment
 * @property-read ?User $changer
 */
class EnrollmentStatusHistory extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'enrollment_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
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
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
