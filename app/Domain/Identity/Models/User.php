<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Enums\Gender;
use App\Domain\Identity\Enums\UserStatus;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property string $first_name
 * @property string $last_name
 * @property string $full_name
 * @property string $email
 * @property ?string $phone
 * @property string $password
 * @property ?string $two_factor_secret
 * @property ?array<string> $two_factor_recovery_codes
 * @property ?CarbonInterface $two_factor_confirmed_at
 * @property ?string $address_line_1
 * @property ?string $address_line_2
 * @property ?string $city
 * @property ?string $state
 * @property string $country
 * @property ?string $postal_code
 * @property ?CarbonInterface $date_of_birth
 * @property ?Gender $gender
 * @property ?string $avatar_path
 * @property ?int $primary_branch_id
 * @property UserStatus $status
 * @property bool $is_deactivated
 * @property ?CarbonInterface $deactivated_at
 * @property string $timezone
 * @property string $locale
 * @property ?array<string, mixed> $metadata
 * @property ?CarbonInterface $last_login_at
 * @property-read ?StudentProfile $studentProfile
 * @property-read Collection<int, RoleAssignment> $roleAssignments
 * @property-read Collection<int, UserConsent> $consents
 * @property-read Collection<int, UserDeletionRequest> $deletionRequests
 */
class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
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
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'status',
        'is_deactivated',
        'deactivated_at',
        'timezone',
        'locale',
        'calendar_token',
        'headline',
        'bio',
        'lineage',
        'is_featured_faculty',
        'social_links',
        'metadata',
        'last_login_at',
    ];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'calendar_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'is_deactivated' => 'boolean',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_featured_faculty' => 'boolean',
            'social_links' => 'array',
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

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(UserDeletionRequest::class);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function taughtCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_teachers')
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeFaculty(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->where('is_featured_faculty', true)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'Teacher'))
                ->orWhereHas('taughtCourses');
        });
    }

    // ──────────────────────────────────────────────
    // Accessors & Helpers
    // ──────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isDeactivated(): bool
    {
        return $this->is_deactivated
            || $this->deactivated_at !== null
            || $this->status === UserStatus::Suspended;
    }

    public function deactivate(): void
    {
        $this->update([
            'is_deactivated' => true,
            'status' => UserStatus::Suspended,
            'deactivated_at' => now(),
        ]);
    }

    public function getCalendarToken(): string
    {
        if (empty($this->calendar_token)) {
            $token = bin2hex(random_bytes(32));
            $this->updateQuietly(['calendar_token' => $token]);

            return $token;
        }

        return $this->calendar_token;
    }

    public function getIcsFeedUrl(): string
    {
        return url('/calendar/feed/'.$this->getCalendarToken().'.ics');
    }

    public function reactivate(): void
    {
        $this->update([
            'is_deactivated' => false,
            'status' => UserStatus::Active,
            'deactivated_at' => null,
        ]);
    }

    // ──────────────────────────────────────────────
    // Filament contracts
    // ──────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isDeactivated()) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(['Super Admin', 'Admin', 'admin', 'super_admin']),
            'moderator' => $this->hasAnyRole(['Super Admin', 'Admin', 'Moderator', 'admin', 'moderator']),
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
        return $query->where('status', UserStatus::Active)->where('is_deactivated', false);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('primary_branch_id', $branchId);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    // ──────────────────────────────────────────────
    // Activity log configuration
    // ──────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'phone', 'status', 'is_deactivated', 'primary_branch_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
