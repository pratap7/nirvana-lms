<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Communication\Enums\NotificationChannel;
use App\Domain\Communication\Enums\OutboxStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification Outbox (Section H).
 * Ensures resilient queuing, retry with exponential backoff, delivery status, and idempotency tracking.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property NotificationChannel $channel
 * @property string $template_key
 * @property string $notification_type
 * @property string $recipient
 * @property ?string $subject
 * @property string $content
 * @property ?array<string, mixed> $payload
 * @property OutboxStatus $status
 * @property bool $is_mandatory
 * @property int $attempts
 * @property int $max_attempts
 * @property ?string $last_error
 * @property ?string $idempotency_key
 * @property ?CarbonInterface $scheduled_at
 * @property ?CarbonInterface $sent_at
 * @property ?CarbonInterface $delivered_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?User $user
 */
class NotificationOutbox extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'notification_outbox';

    protected $fillable = [
        'ulid',
        'user_id',
        'channel',
        'template_key',
        'notification_type',
        'recipient',
        'subject',
        'content',
        'payload',
        'status',
        'is_mandatory',
        'attempts',
        'max_attempts',
        'last_error',
        'idempotency_key',
        'scheduled_at',
        'sent_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => OutboxStatus::class,
            'payload' => 'array',
            'is_mandatory' => 'boolean',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(): void
    {
        $this->update([
            'status' => OutboxStatus::Sent,
            'sent_at' => now(),
            'attempts' => $this->attempts + 1,
            'last_error' => null,
        ]);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => OutboxStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $newAttempts = $this->attempts + 1;
        $isExhausted = $newAttempts >= $this->max_attempts;

        $this->update([
            'status' => $isExhausted ? OutboxStatus::Failed : OutboxStatus::Queued,
            'attempts' => $newAttempts,
            'last_error' => $error,
            // Exponential backoff: 2^attempts * 60 seconds
            'scheduled_at' => $isExhausted ? null : now()->addSeconds((int) pow(2, $newAttempts) * 60),
        ]);
    }

    public function retry(): void
    {
        $this->update([
            'status' => OutboxStatus::Queued,
            'scheduled_at' => now(),
            'last_error' => null,
        ]);
    }
}
