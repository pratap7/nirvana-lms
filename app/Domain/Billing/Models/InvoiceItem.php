<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Concerns\HasUlid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item on an Invoice.
 *
 * @property int $id
 * @property string $ulid
 * @property int $invoice_id
 * @property string $item_type
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_amount
 * @property int $total_amount
 * @property string $currency
 * @property ?array<string, mixed> $metadata
 * @property-read ?Invoice $invoice
 */
class InvoiceItem extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_price_amount',
        'total_amount',
        'currency',
        'metadata',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_amount' => 'integer',
            'total_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getUnitPrice(): Money
    {
        return Money::fromMinor($this->unit_price_amount, $this->currency);
    }

    public function getTotal(): Money
    {
        return Money::fromMinor($this->total_amount, $this->currency);
    }
}
