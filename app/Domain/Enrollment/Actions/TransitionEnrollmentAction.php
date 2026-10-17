<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Events\EnrollmentActivated;
use App\Domain\Enrollment\Events\EnrollmentApproved;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentDropped;
use App\Domain\Enrollment\Events\EnrollmentRejected;
use App\Domain\Enrollment\Events\EnrollmentStatusChanged;
use App\Domain\Enrollment\Events\EnrollmentWaitlisted;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Exceptions\IllegalStateTransitionException;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TransitionEnrollmentAction extends BaseAction
{
    /**
     * Transition enrollment to a new state per the state machine map.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws IllegalStateTransitionException
     */
    public function execute(
        Enrollment $enrollment,
        EnrollmentStatus $newStatus,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = []
    ): Enrollment {
        $currentStatus = $enrollment->status;

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new IllegalStateTransitionException(
                model: Enrollment::class,
                from: $currentStatus,
                to: $newStatus
            );
        }

        return DB::transaction(function () use ($enrollment, $currentStatus, $newStatus, $actor, $reason, $metadata): Enrollment {
            $updates = ['status' => $newStatus];

            // 1. Specific lifecycle state updates
            if ($newStatus === EnrollmentStatus::Approved) {
                $holdHours = $enrollment->batch ? $enrollment->batch->seat_hold_hours : 24;
                $expiresAt = CarbonImmutable::now()->addHours($holdHours);
                $updates['seat_hold_expires_at'] = $expiresAt;
                $updates['waitlist_position'] = null;
            } elseif ($newStatus === EnrollmentStatus::Active) {
                $updates['seat_hold_expires_at'] = null;
                $updates['waitlist_position'] = null;
            } elseif ($newStatus === EnrollmentStatus::Cancelled) {
                $updates['cancellation_reason'] = $reason;
                $updates['seat_hold_expires_at'] = null;
                $updates['waitlist_position'] = null;
            } elseif ($newStatus === EnrollmentStatus::Rejected) {
                $updates['rejection_reason'] = $reason;
                $updates['seat_hold_expires_at'] = null;
                $updates['waitlist_position'] = null;
            } elseif ($newStatus === EnrollmentStatus::Dropped) {
                $updates['drop_reason'] = $reason;
                $updates['seat_hold_expires_at'] = null;
            }

            $enrollment->update($updates);

            // 2. Record timeline entry
            $enrollment->recordStatusHistory(
                fromStatus: $currentStatus->value,
                toStatus: $newStatus->value,
                changedBy: $actor,
                reason: $reason,
                metadata: $metadata
            );

            // 3. Activity log
            activity()
                ->causedBy($actor)
                ->performedOn($enrollment)
                ->withProperties([
                    'from_status' => $currentStatus->value,
                    'to_status' => $newStatus->value,
                    'reason' => $reason,
                ])
                ->log('enrollment_status_changed');

            // 4. Dispatch specific & general domain events
            match ($newStatus) {
                EnrollmentStatus::Approved => event(new EnrollmentApproved($enrollment, $enrollment->seat_hold_expires_at, $actor)),
                EnrollmentStatus::Active => event(new EnrollmentActivated($enrollment, $actor)),
                EnrollmentStatus::Waitlisted => event(new EnrollmentWaitlisted($enrollment, $enrollment->waitlist_position ?? 1)),
                EnrollmentStatus::Cancelled => event(new EnrollmentCancelled($enrollment, $reason, $actor)),
                EnrollmentStatus::Dropped => event(new EnrollmentDropped($enrollment, $reason, $actor)),
                EnrollmentStatus::Rejected => event(new EnrollmentRejected($enrollment, $reason ?? 'Rejected by review', $actor)),
                default => null,
            };

            event(new EnrollmentStatusChanged(
                enrollment: $enrollment,
                fromStatus: $currentStatus,
                toStatus: $newStatus,
                actor: $actor,
                reason: $reason
            ));

            return $enrollment;
        });
    }
}
