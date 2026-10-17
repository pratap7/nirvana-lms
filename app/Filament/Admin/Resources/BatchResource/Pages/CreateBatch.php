<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BatchResource\Pages;

use App\Filament\Admin\Resources\BatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBatch extends CreateRecord
{
    protected static string $resource = BatchResource::class;
}
