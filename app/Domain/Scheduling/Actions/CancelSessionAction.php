<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Events\SessionCancelled;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Services\Meeting\MeetingProvider;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class CancelSessionAction extends BaseAction
{
    public function __construct(
        protected MeetingProvider $meetingProvider,
    ) {}

    /**
     * Cancels an individual session while leaving all other batch sessions intact.
     */
    public function execute(Session $session, string $reason, ?User $actor = null): Session
    {
        return DB::transaction(function () use ($session, $reason, $actor): Session {
            $session->update([
                'status' => SessionStatus::Cancelled,
                'cancellation_reason' => $reason,
            ]);

            if ($session->is_online) {
                $this->meetingProvider->deleteMeeting($session);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($session)
                ->withProperties([
                    'reason' => $reason,
                    'batch_id' => $session->batch_id,
                ])
                ->log('session_cancelled');

            event(new SessionCancelled(
                session: $session,
                actor: $actor,
                reason: $reason,
            ));

            return $session->fresh();
        });
    }
}
