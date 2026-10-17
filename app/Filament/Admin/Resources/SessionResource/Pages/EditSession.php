<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SessionResource\Pages;

use App\Filament\Admin\Resources\SessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSession extends EditRecord
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
