<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Support Ticket for student inquiries and issues handled by Moderators.
 *
 * @property int $id
 * @property string $ulid
 * @property string $ticket_number
 * @property int $user_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property ?int $assigned_to
 * @property ?CarbonInterface $resolved_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?User $user
 * @property-read ?User $assignee
 * @property-read Collection<int, SupportTicketMessage> $messages
 */
class SupportTicket extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'ticket_number',
        'user_id',
        'subject',
        'category',
        'priority',
        'status',
        'assigned_to',
        'resolved_at',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->oldest();
    }
}
