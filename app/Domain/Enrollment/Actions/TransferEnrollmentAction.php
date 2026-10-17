<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Exceptions\BatchFullException;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Enrollment\Services\EnrollmentGuard;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferEnrollmentAction extends BaseAction
{
    public function __construct(
        protected EnrollmentGuard $guard,
        protected ReleaseExpiredSeatHoldsAction $releaseHoldsAction,
    ) {}

    /**
     * Transfer an enrollment to another batch of the same or compatible course with pro-rata financial ledger.
     */
    public function execute(
        Enrollment $sourceEnrollment,
        Batch $targetBatch,
        ?User $actor = null,
        ?string $reason = null,
        ?int $proRataRefundAmount = null,
        ?int $proRataFeeDueAmount = null
    ): Enrollment {
        if ($sourceEnrollment->batch_id === $targetBatch->id) {
            throw new InvalidArgumentException('Source and target batch must be different for a transfer.');
        }

        return DB::transaction(function () use (
            $sourceEnrollment,
            $targetBatch,
            $actor,
            $reason,
            $proRataRefundAmount,
            $proRataFeeDueAmount
        ): Enrollment {
            // Lock target batch and check capacity
            /** @var Batch $lockedTarget */
            $lockedTarget = Batch::where('id', $targetBatch->id)->lockForUpdate()->firstOrFail();
            $occupiedSeats = Enrollment::where('batch_id', $lockedTarget->id)->seatOccupying()->lockForUpdate()->count();

            if ($occupiedSeats >= $lockedTarget->capacity) {
                throw new BatchFullException("Cannot transfer: Target batch '{$lockedTarget->name}' is at full capacity.");
            }

            /** @var User $student */
            $student = $sourceEnrollment->student ?? User::findOrFail($sourceEnrollment->student_id);

            // Lock source batch
            $sourceBatch = Batch::where('id', $sourceEnrollment->batch_id)->lockForUpdate()->first();

            // 1. Transition source enrollment to Dropped with transfer note
            $oldStatus = $sourceEnrollment->status;
            $sourceEnrollment->update([
                'status' => EnrollmentStatus::Dropped,
                'drop_reason' => "Transferred to batch '{$lockedTarget->name}'".($reason ? ": {$reason}" : ''),
                'seat_hold_expires_at' => null,
            ]);

            $sourceEnrollment->recordStatusHistory(
                fromStatus: $oldStatus->value,
                toStatus: EnrollmentStatus::Dropped->value,
                changedBy: $actor,
                reason: "Transferred to batch '{$lockedTarget->name}'"
            );

            // 2. Create target enrollment in Active status (or same active status)
            $targetStatus = ($oldStatus === EnrollmentStatus::Active)
                ? EnrollmentStatus::Active
                : EnrollmentStatus::Approved;

            $targetEnrollment = Enrollment::create([
                'student_id' => $student->id,
                'batch_id' => $lockedTarget->id,
                'course_id' => $lockedTarget->course_id,
                'status' => $targetStatus,
                'consent_given' => true,
                'consent_given_at' => now(),
                'internal_notes' => "Transferred from batch '{$sourceBatch?->name}'",
            ]);

            $targetEnrollment->recordStatusHistory(
                fromStatus: null,
                toStatus: $targetStatus->value,
                changedBy: $actor,
                reason: "Transfer accepted from batch '{$sourceBatch?->name}'"
            );

            // 3. Record transfer audit ledger with pro-rata calculations
            EnrollmentTransfer::create([
                'enrollment_id' => $targetEnrollment->id,
                'source_batch_id' => $sourceEnrollment->batch_id,
                'target_batch_id' => $lockedTarget->id,
                'transferred_by' => $actor?->id,
                'pro_rata_refund_amount' => $proRataRefundAmount,
                'pro_rata_fee_due_amount' => $proRataFeeDueAmount,
                'currency' => $lockedTarget->fee_override_currency ?? 'INR',
                'notes' => $reason,
            ]);

            // 4. Promote waitlist on the source batch if any
            if ($sourceBatch && $sourceBatch->auto_promote_waitlist) {
                $this->releaseHoldsAction->promoteNextWaitlistStudent($sourceBatch);
            }

            return $targetEnrollment;
        });
    }
}
