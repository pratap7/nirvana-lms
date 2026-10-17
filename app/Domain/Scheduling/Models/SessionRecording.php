<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session recording video attached to a completed session.
 *
 * @property int $id
 * @property string $ulid
 * @property int $session_id
 * @property string $title
 * @property string $provider
 * @property string $video_id_or_url
 * @property ?int $duration_seconds
 * @property bool $is_processed
 * @property-read ?Session $session
 */
class SessionRecording extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'session_id',
        'title',
        'provider',
        'video_id_or_url',
        'duration_seconds',
        'is_processed',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'is_processed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
