<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContactInquiryResource\Pages;

use App\Filament\Admin\Resources\ContactInquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListContactInquiries extends ListRecords
{
    protected static string $resource = ContactInquiryResource::class;
}
