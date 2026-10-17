<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CourseReviewResource\Pages;

use App\Filament\Admin\Resources\CourseReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListCourseReviews extends ListRecords
{
    protected static string $resource = CourseReviewResource::class;
}
