<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\Gender;
use App\Domain\Identity\Enums\UserStatus;
use App\Support\Concerns\HasUlid;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;
    use HasRoles;
    use HasUlid;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'date_of_birth',
        'gender',
        'avatar_path',
        'primary_branch_id',
        'status',
        'timezone',
        'locale',
        'metadata',
        'last_login_at',
    ];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'status' => UserStatus::class,
            'metadata' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function scopedRoleAssignments(): HasMany
    {
        return $this->hasMany(ScopedRoleAssignment::class);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ──────────────────────────────────────────────
    // Filament contracts
    // ──────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole('admin'),
            'moderator' => $this->hasAnyRole(['admin', 'moderator']),
            default => true,
        };
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    // ──────────────────────────────────────────────
    // Query scopes
    // ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::Active);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('primary_branch_id', $branchId);
    }

    // ──────────────────────────────────────────────
    // Activity log configuration
    // ──────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'phone', 'status', 'primary_branch_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
