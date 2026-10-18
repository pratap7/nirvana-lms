<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\CourseTeacher;
use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\SessionTeacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherScopeService
{
    /**
     * Determine whether the given user is assigned as a teacher for this batch.
     */
    public function canAccessBatch(User $user, Batch $batch): bool
    {
        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            return true;
        }

        // 1. Check if user is a teacher of this batch's course
        $isCourseTeacher = CourseTeacher::query()
            ->where('course_id', $batch->course_id)
            ->where('user_id', $user->id)
            ->exists();

        if ($isCourseTeacher) {
            return true;
        }

        // 2. Check if user is assigned to any session in this batch
        $isSessionTeacher = SessionTeacher::query()
            ->where('user_id', $user->id)
            ->whereHas('session', fn (Builder $q) => $q->where('batch_id', $batch->id))
            ->exists();

        if ($isSessionTeacher) {
            return true;
        }

        // 3. Check scoped role assignments
        return RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->where(function (Builder $q) use ($batch): void {
                $q->where(fn (Builder $sq) => $sq->where('scope_type', 'batch')->where('scope_id', $batch->id))
                    ->orWhere(fn (Builder $sq) => $sq->where('scope_type', 'course')->where('scope_id', $batch->course_id));
            })
            ->exists();
    }

    /**
     * Determine whether the given user can access/edit curriculum for this course.
     */
    public function canAccessCourse(User $user, Course $course): bool
    {
        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            return true;
        }

        $isCourseTeacher = CourseTeacher::query()
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($isCourseTeacher) {
            return true;
        }

        return RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('scope_type', 'course')
            ->where('scope_id', $course->id)
            ->exists();
    }

    /**
     * Get IDs of all batches assigned to this teacher.
     *
     * @return Collection<int, int>
     */
    public function getAssignedBatchIds(User $user): Collection
    {
        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            return Batch::query()->pluck('id');
        }

        $courseIds = CourseTeacher::query()->where('user_id', $user->id)->pluck('course_id');

        $sessionBatchIds = Batch::query()
            ->whereHas('sessions.sessionTeachers', fn (Builder $q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $roleBatchIds = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('scope_type', 'batch')
            ->pluck('scope_id');

        $roleCourseIds = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('scope_type', 'course')
            ->pluck('scope_id');

        $allCourseIds = $courseIds->merge($roleCourseIds)->unique();
        $courseBatchIds = Batch::query()->whereIn('course_id', $allCourseIds)->pluck('id');

        return $courseBatchIds->merge($sessionBatchIds)->merge($roleBatchIds)->unique()->values();
    }
}
