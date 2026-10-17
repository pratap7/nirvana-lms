<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $role_id
 * @property string $scope_type
 * @property ?int $scope_id
 * @property ?CarbonInterface $starts_at
 * @property ?CarbonInterface $expires_at
 * @property ?int $assigned_by
 * @property ?CarbonInterface $revoked_at
 * @property ?int $revoked_by
 * @property-read ?Role $role
 * @property-read ?User $user
 * @property-read ?User $assignedBy
 * @property-read ?User $revokedBy
 */
class RoleAssignment extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $fillable = [
        'ulid',
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
        'starts_at',
        'expires_at',
        'assigned_by',
        'revoked_at',
        'revoked_by',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($q): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeForScope($query, string $scopeType, ?int $scopeId = null)
    {
        $q = $query->where('scope_type', $scopeType);

        if ($scopeId !== null) {
            $q->where('scope_id', $scopeId);
        }

        return $q;
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
