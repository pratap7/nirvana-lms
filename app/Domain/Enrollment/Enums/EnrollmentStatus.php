<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Enums;

use App\Support\Concerns\HasEnumHelpers;

/**
 * State machine for Student Course Enrollment.
 *
 * Core Flow:
 *   Requested → UnderReview → Approved → PaymentPending → Active → Completed
 *
 * Exits & Alternate Flows:
 *   Requested / UnderReview → Waitlisted
 *   Waitlisted → Approved
 *   UnderReview → Rejected
 *   Approved / PaymentPending → Cancelled (e.g. hold timeout, student cancellation)
 *   Active → Dropped (student drops course mid-batch)
 */
enum EnrollmentStatus: string
{
    use HasEnumHelpers;

    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case PaymentPending = 'payment_pending';
    case Active = 'active';
    case Completed = 'completed';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
    case Dropped = 'dropped';
    case Cancelled = 'cancelled';

    /**
     * Map of legal status transitions.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [
                self::UnderReview,
                self::Waitlisted,
                self::Approved,
                self::Rejected,
                self::Cancelled,
            ],
            self::UnderReview => [
                self::Approved,
                self::Waitlisted,
                self::Rejected,
                self::Cancelled,
            ],
            self::Approved => [
                self::PaymentPending,
                self::Active,
                self::Cancelled,
                self::Waitlisted,
            ],
            self::PaymentPending => [
                self::Active,
                self::Cancelled,
            ],
            self::Active => [
                self::Completed,
                self::Dropped,
            ],
            self::Completed => [],
            self::Waitlisted => [
                self::Approved,
                self::Cancelled,
            ],
            self::Rejected => [],
            self::Dropped => [],
            self::Cancelled => [],
        };
    }

    /**
     * Check if a transition to the target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Indicates whether this enrollment status occupies a physical or virtual seat in the batch.
     */
    public function isSeatOccupying(): bool
    {
        return in_array($this, [self::Approved, self::PaymentPending, self::Active], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved (Seat Held)',
            self::PaymentPending => 'Payment Pending',
            self::Active => 'Active Enrolled',
            self::Completed => 'Completed',
            self::Waitlisted => 'Waitlisted',
            self::Rejected => 'Rejected',
            self::Dropped => 'Dropped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'info',
            self::UnderReview => 'warning',
            self::Approved => 'primary',
            self::PaymentPending => 'warning',
            self::Active => 'success',
            self::Completed => 'success',
            self::Waitlisted => 'gray',
            self::Rejected => 'danger',
            self::Dropped => 'danger',
            self::Cancelled => 'danger',
        };
    }
}
