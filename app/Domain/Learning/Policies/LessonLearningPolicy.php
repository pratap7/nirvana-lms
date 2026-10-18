<?php

declare(strict_types=1);

namespace App\Domain\Learning\Policies;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;

class LessonLearningPolicy
{
    /**
     * Determine whether the student can open and view the lesson.
     * Enforces that student MUST have an ACTIVE enrollment in the course/batch.
     */
    public function view(User $user, Lesson $lesson, ?Batch $batch = null): bool
    {
        // Admins and teachers with permission can always view
        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            return true;
        }

        $courseId = $lesson->module?->course_id;
        if ($courseId === null) {
            return false;
        }

        $query = Enrollment::query()
            ->where('student_id', $user->id)
            ->where('course_id', $courseId)
            ->where('status', EnrollmentStatus::Active->value);

        if ($batch !== null) {
            $query->where('batch_id', $batch->id);
        }

        return $query->exists();
    }

    /**
     * Determine whether the student can download a lesson resource file.
     */
    public function downloadResource(User $user, LessonResource $resource, ?Batch $batch = null): bool
    {
        if ($resource->lesson === null) {
            return false;
        }

        return $this->view($user, $resource->lesson, $batch);
    }
}
