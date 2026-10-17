<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Domain\Scheduling\Events\SubstituteAssigned;
use App\Domain\Scheduling\Models\SessionTeacher;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class ApproveSubstituteAction extends BaseAction
{
    /**
     * Moderator approves substitute teacher assignment and dispatches SubstituteAssigned event.
     */
    public function execute(SessionTeacher $assignment, User $moderator): SessionTeacher
    {
        return DB::transaction(function () use ($assignment, $moderator): SessionTeacher {
            $assignment->update([
                'status' => SubstituteRequestStatus::Approved,
                'approved_by' => $moderator->id,
            ]);

            $session = $assignment->session;
            $substitute = $assignment->teacher;
            $originalTeacher = $assignment->requester;

            activity()
                ->causedBy($moderator)
                ->performedOn($session)
                ->withProperties([
                    'substitute_id' => $substitute?->id,
                    'original_teacher_id' => $originalTeacher?->id,
                ])
                ->log('substitute_teacher_approved');

            if ($session && $substitute) {
                event(new SubstituteAssigned(
                    session: $session,
                    substituteTeacher: $substitute,
                    originalTeacher: $originalTeacher,
                    approvedBy: $moderator,
                    reason: $assignment->reason,
                ));
            }

            return $assignment->fresh();
        });
    }
}
