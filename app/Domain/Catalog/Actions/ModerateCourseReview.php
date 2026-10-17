<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\ReviewStatus;
use App\Domain\Catalog\Models\CourseReview;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class ModerateCourseReview extends BaseAction
{
    /**
     * Moderate a course review (Approve or Reject), and update the course average rating cache.
     *
     * @param  ReviewStatus  $decision  (Approved or Rejected)
     */
    public function execute(
        CourseReview $review,
        ReviewStatus $decision,
        ?User $moderator = null,
        ?string $moderationNote = null
    ): CourseReview {
        return DB::transaction(function () use ($review, $decision, $moderator, $moderationNote): CourseReview {
            $review->update([
                'status' => $decision,
                'moderated_by' => $moderator !== null ? $moderator->id : auth()->id(),
                'moderated_at' => now(),
                'moderation_note' => $moderationNote,
            ]);

            // Refresh course average rating and review count cache
            $course = $review->course;
            if ($course) {
                $course->refreshRatingCache();
            }

            activity()
                ->causedBy($moderator ?? auth()->user())
                ->performedOn($review)
                ->withProperties([
                    'decision' => $decision->value,
                    'course_id' => $review->course_id,
                ])
                ->log('course_review_moderated');

            return $review->fresh();
        });
    }
}
