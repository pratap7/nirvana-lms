<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SessionResource\Pages;

use App\Filament\Admin\Resources\SessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSession extends CreateRecord
{
    protected static string $resource = SessionResource::class;
}
