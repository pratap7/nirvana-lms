<?php

declare(strict_types=1);

namespace App\Domain\Wellbeing\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Confidential student wellbeing escalation report.
 * Strictly access-controlled, encrypted at rest, and fully audited.
 *
 * @property int $id
 * @property string $ulid
 * @property int $reporter_id
 * @property ?int $student_id
 * @property ?int $batch_id
 * @property string $category
 * @property string $urgency
 * @property string $description
 * @property string $status
 * @property ?int $assigned_to
 * @property ?string $resolution_notes
 * @property ?CarbonInterface $resolved_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?User $reporter
 * @property-read ?User $student
 * @property-read ?Batch $batch
 * @property-read ?User $assignee
 */
class WellbeingConcern extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $fillable = [
        'ulid',
        'reporter_id',
        'student_id',
        'batch_id',
        'category',
        'urgency',
        'description',
        'status',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'resolution_notes' => 'encrypted',
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'urgency', 'resolved_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Wellbeing concern was {$eventName}");
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Determine whether the given user has permission to review full details.
     */
    public function canBeReviewedBy(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin', 'Moderator']) || $user->hasPermissionTo('concern.review');
    }
}
