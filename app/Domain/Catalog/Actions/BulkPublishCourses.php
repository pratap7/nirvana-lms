<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkPublishCourses extends BaseAction
{
    /**
     * Bulk publish multiple courses.
     *
     * @param  array<int>|Collection<int, Course>  $courses
     * @return int Count of published courses
     */
    public function execute(array|Collection $courses, ?User $actor = null): int
    {
        $actorId = $actor !== null ? $actor->id : auth()->id();
        $publishedCount = 0;

        $records = $courses instanceof Collection
            ? $courses
            : Course::whereIn('id', $courses)->get();

        DB::transaction(function () use ($records, $actorId, &$publishedCount): void {
            foreach ($records as $course) {
                if ($course->status === CourseStatus::Published) {
                    continue;
                }

                $course->update([
                    'status' => CourseStatus::Published,
                    'published_at' => now(),
                    'approved_by' => $actorId,
                ]);

                event(new CoursePublished($course));
                $publishedCount++;
            }
        });

        return $publishedCount;
    }
}
