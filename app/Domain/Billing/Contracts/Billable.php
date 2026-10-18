<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Models\User;
use App\Support\Money;

interface Billable
{
    public function getBillableTitle(): string;

    public function getBillableFee(): Money;

    public function getStudent(): User;

    public function onPaymentConfirmed(Invoice $invoice): void;
}
