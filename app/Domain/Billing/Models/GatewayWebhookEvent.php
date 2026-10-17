<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Raw webhook event received from a payment gateway (e.g. Stripe, Razorpay) for idempotent deduplication and replay safety.
 *
 * @property int $id
 * @property string $ulid
 * @property string $gateway
 * @property string $gateway_event_id
 * @property string $event_type
 * @property ?string $signature
 * @property array<string, mixed> $payload
 * @property bool $is_processed
 * @property ?CarbonInterface $processed_at
 * @property ?string $error_message
 */
class GatewayWebhookEvent extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'gateway',
        'gateway_event_id',
        'event_type',
        'signature',
        'payload',
        'is_processed',
        'processed_at',
        'error_message',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
