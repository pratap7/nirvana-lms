<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContactInquiryResource\Pages;

use App\Filament\Admin\Resources\ContactInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactInquiry extends EditRecord
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
