<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Installment plan associated with an invoice.
 *
 * @property int $id
 * @property string $ulid
 * @property int $invoice_id
 * @property int $total_installments
 * @property int $frequency_days
 * @property int $grace_days
 * @property string $status
 * @property-read ?Invoice $invoice
 * @property-read Collection<int, Installment> $installments
 */
class InstallmentPlan extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'ulid',
        'invoice_id',
        'total_installments',
        'frequency_days',
        'grace_days',
        'status',
    ];

    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_installments' => 'integer',
            'frequency_days' => 'integer',
            'grace_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number');
    }
}
