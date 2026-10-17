<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BatchResource\Pages;

use App\Filament\Admin\Resources\BatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBatch extends EditRecord
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
