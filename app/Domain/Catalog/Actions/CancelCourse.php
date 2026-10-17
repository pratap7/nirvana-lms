<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Events\CourseCancelled;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Exceptions\IllegalStateTransitionException;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class CancelCourse extends BaseAction
{
    /**
     * Cancels a course, notifies enrolled students, and flags refunds for review.
     */
    public function execute(Course $course, string $reason, ?User $actor = null): Course
    {
        if ($course->status === CourseStatus::Cancelled) {
            throw new IllegalStateTransitionException(Course::class, $course->status, CourseStatus::Cancelled);
        }

        return DB::transaction(function () use ($course, $reason, $actor): Course {
            // Update status and cancellation details
            $course->update([
                'status' => CourseStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Audit log with flagged refunds for review
            activity()
                ->causedBy($actor)
                ->performedOn($course)
                ->withProperties([
                    'action' => 'course_cancelled',
                    'reason' => $reason,
                    'flag_refunds_for_review' => true,
                    'cancelled_at' => now()->toIso8601String(),
                ])
                ->log('course_cancelled');

            // Dispatch domain event notifying students & refund system
            event(new CourseCancelled(
                course: $course,
                cancelledBy: $actor,
                reason: $reason,
                flagRefundsForReview: true,
            ));

            return $course->fresh();
        });
    }
}
