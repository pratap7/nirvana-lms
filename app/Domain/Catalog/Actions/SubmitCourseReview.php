<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\CourseReview;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Validation\ValidationException;

class SubmitCourseReview extends BaseAction
{
    /**
     * Submit a course review.
     * Enforces:
     *  1. Enrolled student with >= 25% progress
     *  2. Exactly one review per student per course
     *  3. Enters moderation queue with Pending status
     *
     * @param  int  $rating  (1 - 5)
     *
     * @throws ValidationException
     */
    public function execute(
        Course $course,
        User $student,
        int $rating,
        string $reviewText,
        float $studentProgressPercent,
        ?string $title = null
    ): CourseReview {
        // 1. One review per student per course
        $alreadyReviewed = CourseReview::query()
            ->where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'user_id' => 'You have already submitted a review for this course.',
            ]);
        }

        // 2. Minimum 25% course progress required
        if ($studentProgressPercent < 25.0) {
            throw ValidationException::withMessages([
                'progress' => 'You must complete at least 25% of the course lessons to submit a review.',
            ]);
        }

        // 3. Rating range validation
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating must be an integer between 1 and 5.',
            ]);
        }

        return CourseReview::create([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'rating' => $rating,
            'title' => $title,
            'review_text' => trim($reviewText),
            'status' => ReviewStatus::Pending,
        ]);
    }
}
