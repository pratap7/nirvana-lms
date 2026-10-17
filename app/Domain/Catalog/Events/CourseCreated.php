<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Events;

use App\Domain\Catalog\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Course $course,
    ) {}
}
