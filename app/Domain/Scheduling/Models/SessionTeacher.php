<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher assignment or substitute request for a specific Session.
 *
 * @property int $id
 * @property int $session_id
 * @property int $user_id
 * @property SessionTeacherRole $role
 * @property SubstituteRequestStatus $status
 * @property ?int $requested_by
 * @property ?int $approved_by
 * @property ?string $reason
 * @property-read ?Session $session
 * @property-read ?User $teacher
 * @property-read ?User $requester
 * @property-read ?User $approver
 */
class SessionTeacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'role',
        'status',
        'requested_by',
        'approved_by',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SessionTeacherRole::class,
            'status' => SubstituteRequestStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
