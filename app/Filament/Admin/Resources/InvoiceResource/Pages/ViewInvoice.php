<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Domain\Billing\Models\Invoice;
use App\Filament\Admin\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('View / Print GST Invoice')
                ->icon('heroicon-o-printer')
                ->url(function (): string {
                    /** @var Invoice $invoice */
                    $invoice = $this->getRecord();

                    return route('invoices.view', ['invoice' => $invoice->ulid]);
                })
                ->openUrlInNewTab(),
        ];
    }
}
