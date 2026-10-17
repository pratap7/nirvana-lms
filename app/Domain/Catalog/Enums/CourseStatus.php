<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Course lifecycle state machine.
 *
 * Transitions:
 *   Draft → InReview, Cancelled
 *   InReview → Draft, Published, Cancelled
 *   Published → Archived, Cancelled
 *   Archived → Draft
 *   Cancelled → []
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview, self::Cancelled],
            self::InReview => [self::Draft, self::Published, self::Cancelled],
            self::Published => [self::Archived, self::Cancelled],
            self::Archived => [self::Draft],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In Review',
            self::Published => 'Published',
            self::Archived => 'Archived',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Published => 'success',
            self::Archived => 'danger',
            self::Cancelled => 'danger',
        };
    }
}
