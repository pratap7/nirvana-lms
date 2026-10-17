<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sequential atomic counter per Financial Year for gap-free tax invoice numbering.
 *
 * @property string $financial_year
 * @property int $current_number
 */
class InvoiceSequence extends Model
{
    protected $table = 'invoice_sequences';

    protected $primaryKey = 'financial_year';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'financial_year',
        'current_number',
    ];
}
