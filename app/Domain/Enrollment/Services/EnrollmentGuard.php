<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Services;

use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Exceptions\DuplicateEnrollmentException;
use App\Domain\Enrollment\Exceptions\PrerequisiteNotMetException;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Support\Facades\DB;

class EnrollmentGuard
{
    /**
     * Assert that the student satisfies all prerequisites and is not duplicate-enrolled.
     *
     * @throws DuplicateEnrollmentException
     * @throws PrerequisiteNotMetException
     */
    public function assertCanEnroll(User $student, Batch $batch): void
    {
        $this->assertNoDuplicateEnrollment($student, $batch);
        $this->assertPrerequisitesMet($student, $batch->course ?? Course::findOrFail($batch->course_id));
    }

    /**
     * Check if student is already actively or pendingly enrolled in this batch or course.
     */
    public function assertNoDuplicateEnrollment(User $student, Batch $batch): void
    {
        $existing = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batch->id)
            ->whereNotIn('status', [
                EnrollmentStatus::Cancelled->value,
                EnrollmentStatus::Rejected->value,
                EnrollmentStatus::Dropped->value,
            ])
            ->exists();

        if ($existing) {
            throw new DuplicateEnrollmentException(
                "Student is already enrolled (or pending approval/payment) for batch '{$batch->name}'."
            );
        }

        // Also guard against concurrent active enrollments in the same course if course is short-term
        $course = $batch->course ?? Course::find($batch->course_id);
        if ($course && $course->type === CourseType::ShortTerm) {
            $activeCourseEnrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->exists();

            if ($activeCourseEnrollment) {
                throw new DuplicateEnrollmentException(
                    "Student already has an active enrollment in course '{$course->name}'."
                );
            }
        }
    }

    /**
     * Check if student has successfully completed all prerequisite courses.
     */
    public function assertPrerequisitesMet(User $student, Course $course): void
    {
        // Query prerequisite course IDs from course_prerequisites
        $prerequisiteCourseIds = DB::table('course_prerequisites')
            ->where('course_id', $course->id)
            ->pluck('prerequisite_course_id')
            ->toArray();

        if (empty($prerequisiteCourseIds)) {
            return;
        }

        // Find which prerequisite courses the student has completed
        $completedCourseIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('course_id', $prerequisiteCourseIds)
            ->where('status', EnrollmentStatus::Completed->value)
            ->pluck('course_id')
            ->toArray();

        $missingIds = array_diff($prerequisiteCourseIds, $completedCourseIds);

        if (! empty($missingIds)) {
            $missingTitles = Course::whereIn('id', $missingIds)->pluck('name')->toArray();

            throw new PrerequisiteNotMetException(
                missingCourses: $missingTitles,
                message: "Cannot enroll in '{$course->name}' until prerequisites are completed."
            );
        }
    }
}
