<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Events\EnrollmentApproved;
use App\Domain\Enrollment\Events\EnrollmentRequested;
use App\Domain\Enrollment\Events\EnrollmentStatusChanged;
use App\Domain\Enrollment\Events\EnrollmentWaitlisted;
use App\Domain\Enrollment\Exceptions\BatchFullException;
use App\Domain\Enrollment\Exceptions\DuplicateEnrollmentException;
use App\Domain\Enrollment\Exceptions\PrerequisiteNotMetException;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Services\EnrollmentGuard;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RequestEnrollmentAction extends BaseAction
{
    public function __construct(
        protected EnrollmentGuard $guard,
    ) {}

    /**
     * Request or create an enrollment with concurrency-safe capacity locking and prerequisite validation.
     *
     * @throws DuplicateEnrollmentException
     * @throws PrerequisiteNotMetException
     * @throws BatchFullException
     */
    public function execute(
        User $student,
        Batch $batch,
        bool $consentGiven = true,
        ?string $consentIp = null,
        ?string $notes = null,
        bool $forceApprove = false,
        ?User $registeredBy = null
    ): Enrollment {
        return DB::transaction(function () use (
            $student,
            $batch,
            $consentGiven,
            $consentIp,
            $notes,
            $forceApprove,
            $registeredBy
        ): Enrollment {
            // 1. Pessimistic row locking on Batch to prevent overbooking across concurrent processes
            /** @var Batch $lockedBatch */
            $lockedBatch = Batch::query()
                ->where('id', $batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validate duplicate and prerequisite guards
            $this->guard->assertCanEnroll($student, $lockedBatch);

            // 3. Count occupied seats with active holds or active status
            $occupiedSeats = Enrollment::query()
                ->where('batch_id', $lockedBatch->id)
                ->seatOccupying()
                ->lockForUpdate()
                ->count();

            $capacity = $lockedBatch->capacity;

            // 4. If seats available, allocate seat hold or place in review
            if ($occupiedSeats < $capacity) {
                $status = ($lockedBatch->requires_approval && ! $forceApprove)
                    ? EnrollmentStatus::Requested
                    : EnrollmentStatus::Approved;

                $holdHours = $lockedBatch->seat_hold_hours ?? 24;
                $seatHoldExpiresAt = ($status === EnrollmentStatus::Approved)
                    ? CarbonImmutable::now()->addHours($holdHours)
                    : null;

                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'batch_id' => $lockedBatch->id,
                    'course_id' => $lockedBatch->course_id,
                    'status' => $status,
                    'seat_hold_expires_at' => $seatHoldExpiresAt,
                    'waitlist_position' => null,
                    'is_offline_registration' => $registeredBy !== null,
                    'registered_by' => $registeredBy?->id,
                    'consent_given' => $consentGiven,
                    'consent_given_at' => now(),
                    'consent_ip' => $consentIp,
                    'internal_notes' => $notes,
                ]);

                $enrollment->recordStatusHistory(
                    fromStatus: null,
                    toStatus: $status->value,
                    changedBy: $registeredBy ?? $student,
                    reason: 'Initial enrollment request',
                    metadata: ['hold_hours' => $holdHours, 'seat_hold_expires_at' => $seatHoldExpiresAt?->toIso8601String()]
                );

                if ($status === EnrollmentStatus::Approved) {
                    event(new EnrollmentApproved($enrollment, $seatHoldExpiresAt, $registeredBy));
                } else {
                    event(new EnrollmentRequested($enrollment));
                }

                event(new EnrollmentStatusChanged(
                    enrollment: $enrollment,
                    fromStatus: null,
                    toStatus: $status,
                    actor: $registeredBy ?? $student,
                    reason: 'Enrollment initiated'
                ));

                return $enrollment;
            }

            // 5. Batch is full: check waitlist policy
            if ($lockedBatch->allow_waitlist) {
                $lastPosition = (int) Enrollment::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->where('status', EnrollmentStatus::Waitlisted->value)
                    ->max('waitlist_position');

                $nextPosition = $lastPosition + 1;

                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'batch_id' => $lockedBatch->id,
                    'course_id' => $lockedBatch->course_id,
                    'status' => EnrollmentStatus::Waitlisted,
                    'seat_hold_expires_at' => null,
                    'waitlist_position' => $nextPosition,
                    'is_offline_registration' => $registeredBy !== null,
                    'registered_by' => $registeredBy?->id,
                    'consent_given' => $consentGiven,
                    'consent_given_at' => now(),
                    'consent_ip' => $consentIp,
                    'internal_notes' => $notes,
                ]);

                $enrollment->recordStatusHistory(
                    fromStatus: null,
                    toStatus: EnrollmentStatus::Waitlisted->value,
                    changedBy: $registeredBy ?? $student,
                    reason: "Batch full ({$capacity} seats occupied). Placed on waitlist position #{$nextPosition}."
                );

                event(new EnrollmentWaitlisted($enrollment, $nextPosition));
                event(new EnrollmentStatusChanged(
                    enrollment: $enrollment,
                    fromStatus: null,
                    toStatus: EnrollmentStatus::Waitlisted,
                    actor: $registeredBy ?? $student,
                    reason: 'Placed on waitlist'
                ));

                return $enrollment;
            }

            throw new BatchFullException("Batch '{$lockedBatch->name}' is at full capacity ({$capacity} seats) and waitlist is disabled.");
        });
    }
}
