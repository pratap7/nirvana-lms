<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Catalog\Models\Module;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateCourse extends BaseAction
{
    /**
     * Deep duplicate a Course along with its modules, lessons, resources, and completion rules.
     */
    public function execute(Course $original, ?string $newTitle = null): Course
    {
        return DB::transaction(function () use ($original, $newTitle): Course {
            $title = $newTitle ?? ($original->name.' (Copy)');
            $baseSlug = Str::slug($title);
            $slug = $baseSlug;
            $counter = 1;

            while (Course::where('branch_id', $original->branch_id)->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            // 1. Replicate base course
            /** @var Course $clone */
            $clone = $original->replicate([
                'ulid',
                'published_at',
                'cancelled_at',
                'cancellation_reason',
                'rating_average',
                'reviews_count',
            ]);

            $clone->name = $title;
            $clone->slug = $slug;
            $clone->status = CourseStatus::Draft;
            $clone->rating_average = 0.00;
            $clone->reviews_count = 0;
            $clone->save();

            // 2. Clone modules, lessons, and resources
            foreach ($original->modules as $origModule) {
                /** @var Module $clonedModule */
                $clonedModule = $origModule->replicate(['ulid']);
                $clonedModule->course_id = $clone->id;
                $clonedModule->save();

                foreach ($origModule->lessons as $origLesson) {
                    /** @var Lesson $origLesson */
                    /** @var Lesson $clonedLesson */
                    $clonedLesson = $origLesson->replicate(['ulid']);
                    $clonedLesson->module_id = $clonedModule->id;
                    $clonedLesson->save();

                    foreach ($origLesson->resources as $origResource) {
                        /** @var LessonResource $clonedResource */
                        $clonedResource = $origResource->replicate(['ulid']);
                        $clonedResource->lesson_id = $clonedLesson->id;
                        $clonedResource->save();
                    }
                }
            }

            // 3. Clone completion rule record if exists
            if ($original->completionRuleRecord) {
                $clonedRule = $original->completionRuleRecord->replicate();
                $clonedRule->course_id = $clone->id;
                $clonedRule->save();
            }

            activity()
                ->causedBy(auth()->user())
                ->performedOn($clone)
                ->withProperties([
                    'original_course_id' => $original->id,
                    'cloned_course_id' => $clone->id,
                ])
                ->log('course_duplicated');

            return $clone;
        });
    }
}
