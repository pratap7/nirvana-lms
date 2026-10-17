<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VenueResource\Pages;

use App\Filament\Admin\Resources\VenueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;
}
