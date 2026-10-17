<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;

/**
 * Extends Spatie's role system with scope (course/batch) and expiry.
 *
 * This supplements (does not replace) the standard `model_has_roles` pivot.
 * It enables:
 * - Guest Teachers with auto-expiring, batch-scoped role assignments
 * - Branch-specific role assignments
 * - Audit trail of who granted/revoked each role
 */
/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $role_id
 * @property ?string $scopeable_type
 * @property ?int $scopeable_id
 * @property ?int $branch_id
 * @property int $granted_by
 * @property ?CarbonInterface $expires_at
 * @property ?CarbonInterface $revoked_at
 * @property ?int $revoked_by
 */
class ScopedRoleAssignment extends Model
{
    use HasUlid;
    use LogsActivity;

    protected $fillable = [
        'ulid',
        'user_id',
        'role_id',
        'scopeable_type',
        'scopeable_id',
        'branch_id',
        'granted_by',
        'expires_at',
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
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // ──────────────────────────────────────────────
    // Query scopes
    // ──────────────────────────────────────────────

    /**
     * Only active (non-revoked, non-expired) assignments.
     */
    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Expired but not yet revoked assignments (for cleanup).
     */
    public function scopeExpired($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Assignments scoped to a specific entity.
     */
    public function scopeForScope($query, string $scopeableType, int $scopeableId)
    {
        return $query
            ->where('scopeable_type', $scopeableType)
            ->where('scopeable_id', $scopeableId);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
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

    // ──────────────────────────────────────────────
    // Activity log
    // ──────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
