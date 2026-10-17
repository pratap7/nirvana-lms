<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {}
}
