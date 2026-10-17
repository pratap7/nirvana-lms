<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class DropEnrollmentAction extends BaseAction
{
    public function __construct(
        protected TransitionEnrollmentAction $transitionAction,
        protected ReleaseExpiredSeatHoldsAction $releaseHoldsAction,
    ) {}

    /**
     * Drop a student from an active enrollment and trigger waitlist promotion.
     */
    public function execute(
        Enrollment $enrollment,
        ?User $actor = null,
        ?string $reason = null
    ): Enrollment {
        return DB::transaction(function () use ($enrollment, $actor, $reason): Enrollment {
            $batch = Batch::where('id', $enrollment->batch_id)->lockForUpdate()->first();

            $dropped = $this->transitionAction->execute(
                enrollment: $enrollment,
                newStatus: EnrollmentStatus::Dropped,
                actor: $actor,
                reason: $reason ?? 'Student dropped from batch'
            );

            // If batch has waitlist promotion enabled, promote next in line
            if ($batch && $batch->auto_promote_waitlist) {
                $this->releaseHoldsAction->promoteNextWaitlistStudent($batch);
            }

            return $dropped;
        });
    }
}
