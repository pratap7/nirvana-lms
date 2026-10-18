<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Post-Event Attendee Feedback.
 *
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property int $user_id
 * @property int $registration_id
 * @property int $rating
 * @property ?int $recommendation_score
 * @property ?string $comments
 * @property ?string $highlights
 * @property-read ?Event $event
 * @property-read ?User $user
 * @property-read ?EventRegistration $registration
 */
class EventFeedback extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'event_feedbacks';

    protected $fillable = [
        'ulid',
        'event_id',
        'user_id',
        'registration_id',
        'rating',
        'recommendation_score',
        'comments',
        'highlights',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'recommendation_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}
