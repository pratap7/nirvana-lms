<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message sent within a shared batch between teachers and enrolled students.
 *
 * @property int $id
 * @property string $ulid
 * @property int $batch_id
 * @property int $sender_id
 * @property ?int $recipient_id
 * @property string $message
 * @property ?CarbonInterface $read_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?Batch $batch
 * @property-read ?User $sender
 * @property-read ?User $recipient
 */
class BatchMessage extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'batch_id',
        'sender_id',
        'recipient_id',
        'message',
        'read_at',
        'flagged_at',
        'flagged_by',
        'flag_reason',
        'is_reviewed_by_admin',
        'admin_review_notes',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'archived_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'flagged_at' => 'datetime',
            'is_reviewed_by_admin' => 'boolean',
            'admin_reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adminReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    public function flag(User $flagger, string $reason): void
    {
        $this->update([
            'flagged_at' => now(),
            'flagged_by' => $flagger->id,
            'flag_reason' => $reason,
        ]);
    }

    public function review(User $admin, string $notes): void
    {
        $this->update([
            'is_reviewed_by_admin' => true,
            'admin_review_notes' => $notes,
            'admin_reviewed_by' => $admin->id,
            'admin_reviewed_at' => now(),
        ]);
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }
}
