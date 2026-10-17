<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Course;
use App\Support\Actions\BaseAction;

class CalculateCourseProgress extends BaseAction
{
    /**
     * Calculate student completion percentage (0.0 to 100.0).
     *
     * Curriculum versioning rule:
     * - Only published, non-soft-deleted, required (is_mandatory=true) lessons count towards completion.
     * - Soft-deleted lessons are completely ignored.
     * - Optional preview lessons (is_mandatory=false) do not dilute completion percentage.
     * - Reordering modules or lessons does not change completion percentage.
     *
     * @param  array<int|string>  $completedLessonIds
     */
    public function execute(Course $course, array $completedLessonIds): float
    {
        return $course->calculateProgressFor($completedLessonIds);
    }
}
