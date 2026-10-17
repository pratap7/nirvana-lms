<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property string $scope_type
 * @property int $scope_id
 * @property string $token
 * @property ?CarbonInterface $starts_at
 * @property CarbonInterface $expires_at
 * @property int $invited_by
 * @property ?CarbonInterface $accepted_at
 */
class GuestTeacherInvitation extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'email',
        'first_name',
        'last_name',
        'scope_type',
        'scope_id',
        'token',
        'starts_at',
        'expires_at',
        'invited_by',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
