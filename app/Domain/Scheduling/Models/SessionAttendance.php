<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $session_id
 * @property int $user_id
 * @property string $status
 * @property ?string $notes
 * @property ?int $marked_by
 * @property-read ?Session $session
 * @property-read ?User $user
 * @property-read ?User $marker
 */
class SessionAttendance extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'session_attendances';

    protected $fillable = [
        'ulid',
        'session_id',
        'user_id',
        'status',
        'notes',
        'marked_by',
    ];

    protected $hidden = ['id'];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
