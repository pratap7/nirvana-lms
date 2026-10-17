<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property ?string $emergency_contact_name
 * @property ?string $emergency_contact_phone
 * @property ?string $emergency_contact_relationship
 * @property ?array<string, mixed> $parq_answers
 * @property ?string $medical_conditions
 * @property ?string $allergies
 * @property ?string $dietary_requirements
 */
class StudentProfile extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $fillable = [
        'ulid',
        'user_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'parq_answers',
        'medical_conditions',
        'allergies',
        'dietary_requirements',
    ];

    protected $hidden = ['id'];

    /**
     * Emergency contact and PAR-Q health declarations are encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'emergency_contact_relationship' => 'encrypted',
            'parq_answers' => 'encrypted:array',
            'medical_conditions' => 'encrypted',
            'allergies' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Don't log decrypted PII in activity log to avoid leaking sensitive health info
        return LogOptions::defaults()
            ->logOnly(['user_id', 'dietary_requirements'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
