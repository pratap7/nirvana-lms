<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BatchResource\Pages;

use App\Filament\Admin\Resources\BatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBatches extends ListRecords
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
