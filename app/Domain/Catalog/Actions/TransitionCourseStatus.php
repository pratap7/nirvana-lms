<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Models\Course;
use Illuminate\Support\Facades\DB;

final class TransitionCourseStatus
{
    public function execute(Course $course, CourseStatus $targetStatus, ?int $actorId = null): Course
    {
        return DB::transaction(function () use ($course, $targetStatus, $actorId): Course {
            // State machine validation and transition
            $course->transitionTo($targetStatus);

            if ($targetStatus === CourseStatus::Published) {
                $course->update([
                    'published_at' => now(),
                    'approved_by' => $actorId,
                ]);

                event(new CoursePublished($course));
            } elseif ($targetStatus === CourseStatus::Cancelled) {
                $course->update([
                    'cancelled_at' => now(),
                ]);
            }

            return $course->fresh();
        });
    }
}
