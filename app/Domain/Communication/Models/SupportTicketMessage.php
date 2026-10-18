<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message within a SupportTicket.
 *
 * @property int $id
 * @property string $ulid
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property bool $is_staff_reply
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read ?SupportTicket $ticket
 * @property-read ?User $user
 */
class SupportTicketMessage extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'ticket_id',
        'user_id',
        'message',
        'is_staff_reply',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_staff_reply' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
