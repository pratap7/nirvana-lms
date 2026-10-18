<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use App\Support\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $ulid
 * @property int $session_id
 * @property int $user_id
 * @property string $status
 * @property ?string $notes
 * @property ?string $correction_reason
 * @property ?int $marked_by
 * @property ?int $edited_by
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?Session $session
 * @property-read ?User $user
 * @property-read ?User $marker
 * @property-read ?User $editor
 */
class SessionAttendance extends Model
{
    use HasFactory;
    use HasUlid;
    use LogsActivity;

    protected $table = 'session_attendances';

    protected $fillable = [
        'ulid',
        'session_id',
        'user_id',
        'status',
        'notes',
        'correction_reason',
        'marked_by',
        'edited_by',
    ];

    protected $hidden = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'notes', 'correction_reason', 'edited_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Attendance record was {$eventName}");
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Determine if this attendance record is past the allowed edit window and locked.
     */
    public function isLocked(?int $windowDays = null): bool
    {
        if (! $this->session) {
            return false;
        }

        if ($windowDays === null) {
            /** @var SettingsService $settings */
            $settings = app(SettingsService::class);
            $windowDays = (int) $settings->get('attendance_edit_window_days', 3);
        }

        $sessionTime = $this->session->ends_at ?? $this->session->starts_at;

        return now()->isAfter($sessionTime->copy()->addDays($windowDays));
    }
}
