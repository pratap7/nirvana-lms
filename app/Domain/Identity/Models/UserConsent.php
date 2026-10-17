<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $consent_type
 * @property string $version
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property CarbonInterface $consented_at
 */
class UserConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'ip_address',
        'user_agent',
        'consented_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
