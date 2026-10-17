<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $status
 * @property ?string $reason
 * @property CarbonInterface $requested_at
 * @property ?CarbonInterface $scheduled_for
 * @property ?CarbonInterface $processed_at
 * @property ?int $processed_by
 */
class UserDeletionRequest extends Model
{
    use HasUlid;

    protected $fillable = [
        'ulid',
        'user_id',
        'status',
        'reason',
        'requested_at',
        'scheduled_for',
        'processed_at',
        'processed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
