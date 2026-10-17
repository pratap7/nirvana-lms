<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionTeacher;
use App\Domain\Scheduling\Services\ConflictDetector;
use App\Support\Actions\BaseAction;

class RequestSubstituteAction extends BaseAction
{
    public function __construct(
        protected ConflictDetector $conflictDetector,
    ) {}

    /**
     * Teacher requests a substitute faculty for a specific session.
     */
    public function execute(
        Session $session,
        User $substituteTeacher,
        User $requestingTeacher,
        ?string $reason = null
    ): SessionTeacher {
        // Assert substitute teacher is free
        $this->conflictDetector->assertNoConflicts(
            roomId: null,
            teacherId: $substituteTeacher->id,
            startsAt: $session->starts_at,
            endsAt: $session->ends_at,
            ignoreSessionId: $session->id,
        );

        $assignment = SessionTeacher::updateOrCreate(
            [
                'session_id' => $session->id,
                'user_id' => $substituteTeacher->id,
                'role' => SessionTeacherRole::Substitute,
            ],
            [
                'status' => SubstituteRequestStatus::Requested,
                'requested_by' => $requestingTeacher->id,
                'reason' => $reason,
            ]
        );

        activity()
            ->causedBy($requestingTeacher)
            ->performedOn($session)
            ->withProperties([
                'substitute_id' => $substituteTeacher->id,
                'reason' => $reason,
            ])
            ->log('substitute_teacher_requested');

        return $assignment;
    }
}
