<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Events\EnrollmentApproved;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentStatusChanged;
use App\Domain\Enrollment\Events\EnrollmentWaitlistPromoted;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredSeatHoldsAction extends BaseAction
{
    /**
     * Release expired seat holds and promote waitlisted students for eligible batches.
     *
     * @return array{released_count: int, promoted_count: int}
     */
    public function execute(): array
    {
        $releasedCount = 0;
        $promotedCount = 0;

        // 1. Find enrollments with expired seat holds
        /** @var Collection<int, Enrollment> $expiredEnrollments */
        $expiredEnrollments = Enrollment::query()
            ->whereIn('status', [
                EnrollmentStatus::Approved->value,
                EnrollmentStatus::PaymentPending->value,
            ])
            ->whereNotNull('seat_hold_expires_at')
            ->where('seat_hold_expires_at', '<=', now())
            ->get();

        foreach ($expiredEnrollments as $enrollment) {
            DB::transaction(function () use ($enrollment, &$releasedCount, &$promotedCount): void {
                /** @var Enrollment|null $lockedEnrollment */
                $lockedEnrollment = Enrollment::where('id', $enrollment->id)->lockForUpdate()->first();

                // Re-verify expiration under lock
                if ($lockedEnrollment === null || ! $lockedEnrollment->hasExpiredHold()) {
                    return;
                }

                $oldStatus = $lockedEnrollment->status;
                $batch = Batch::where('id', $lockedEnrollment->batch_id)->lockForUpdate()->first();

                // Cancel the expired enrollment
                $lockedEnrollment->update([
                    'status' => EnrollmentStatus::Cancelled,
                    'cancellation_reason' => 'Seat hold expired automatically',
                    'seat_hold_expires_at' => null,
                    'waitlist_position' => null,
                ]);

                $lockedEnrollment->recordStatusHistory(
                    fromStatus: $oldStatus->value,
                    toStatus: EnrollmentStatus::Cancelled->value,
                    reason: 'Seat hold expired automatically'
                );

                event(new EnrollmentCancelled($lockedEnrollment, 'Seat hold expired automatically'));
                event(new EnrollmentStatusChanged(
                    enrollment: $lockedEnrollment,
                    fromStatus: $oldStatus,
                    toStatus: EnrollmentStatus::Cancelled,
                    reason: 'Seat hold expired automatically'
                ));

                $releasedCount++;

                // 2. Promote next student from waitlist if auto-promote is enabled
                if ($batch && $batch->auto_promote_waitlist) {
                    $promoted = $this->promoteNextWaitlistStudent($batch);
                    if ($promoted) {
                        $promotedCount++;
                    }
                }
            });
        }

        return [
            'released_count' => $releasedCount,
            'promoted_count' => $promotedCount,
        ];
    }

    /**
     * Promotes the first waitlisted student to Approved status with a new seat hold.
     */
    public function promoteNextWaitlistStudent(Batch $batch): ?Enrollment
    {
        /** @var Enrollment|null $nextInLine */
        $nextInLine = Enrollment::query()
            ->where('batch_id', $batch->id)
            ->where('status', EnrollmentStatus::Waitlisted->value)
            ->orderBy('waitlist_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();

        if ($nextInLine === null) {
            return null;
        }

        $holdHours = $batch->seat_hold_hours ?? 24;
        $expiresAt = CarbonImmutable::now()->addHours($holdHours);

        $nextInLine->update([
            'status' => EnrollmentStatus::Approved,
            'seat_hold_expires_at' => $expiresAt,
            'waitlist_position' => null,
        ]);

        $nextInLine->recordStatusHistory(
            fromStatus: EnrollmentStatus::Waitlisted->value,
            toStatus: EnrollmentStatus::Approved->value,
            reason: "Promoted from waitlist position #1. Seat held until {$expiresAt->toIso8601String()}",
            metadata: ['seat_hold_expires_at' => $expiresAt->toIso8601String()]
        );

        event(new EnrollmentWaitlistPromoted($nextInLine, $expiresAt));
        event(new EnrollmentApproved($nextInLine, $expiresAt));
        event(new EnrollmentStatusChanged(
            enrollment: $nextInLine,
            fromStatus: EnrollmentStatus::Waitlisted,
            toStatus: EnrollmentStatus::Approved,
            reason: 'Promoted from waitlist'
        ));

        // Re-index remaining waitlisted students
        $remainingWaitlist = Enrollment::query()
            ->where('batch_id', $batch->id)
            ->where('status', EnrollmentStatus::Waitlisted->value)
            ->orderBy('waitlist_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        $pos = 1;
        foreach ($remainingWaitlist as $w) {
            $w->update(['waitlist_position' => $pos++]);
        }

        return $nextInLine;
    }
}
