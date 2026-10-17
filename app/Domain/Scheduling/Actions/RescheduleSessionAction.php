<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Events\SessionRescheduled;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Services\ConflictDetector;
use App\Domain\Scheduling\Services\Meeting\MeetingProvider;
use App\Support\Actions\BaseAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RescheduleSessionAction extends BaseAction
{
    public function __construct(
        protected ConflictDetector $conflictDetector,
        protected MeetingProvider $meetingProvider,
    ) {}

    /**
     * Reschedules an individual session without altering other batch sessions.
     * Enforces conflict detection for the target room and active teacher.
     */
    public function execute(
        Session $session,
        CarbonInterface $newStartsAt,
        CarbonInterface $newEndsAt,
        ?int $newRoomId = null,
        ?User $actor = null,
        ?string $reason = null
    ): Session {
        $roomId = $newRoomId ?? $session->room_id;
        $activeTeacher = $session->getActiveTeacher();

        // 1. Conflict detection on the new time slot
        $this->conflictDetector->assertNoConflicts(
            roomId: $roomId,
            teacherId: $activeTeacher?->id,
            startsAt: $newStartsAt,
            endsAt: $newEndsAt,
            ignoreSessionId: $session->id,
        );

        return DB::transaction(function () use ($session, $newStartsAt, $newEndsAt, $roomId, $actor, $reason): Session {
            $oldStartsAt = $session->starts_at;
            $oldEndsAt = $session->ends_at;

            $session->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'room_id' => $roomId,
                'status' => SessionStatus::Rescheduled,
            ]);

            // Sync with meeting provider if online
            if ($session->is_online) {
                $this->meetingProvider->updateMeeting($session);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($session)
                ->withProperties([
                    'old_starts_at' => $oldStartsAt->toIso8601String(),
                    'new_starts_at' => $newStartsAt->toIso8601String(),
                    'reason' => $reason,
                ])
                ->log('session_rescheduled');

            event(new SessionRescheduled(
                session: $session,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                actor: $actor,
                reason: $reason,
            ));

            return $session->fresh();
        });
    }
}
