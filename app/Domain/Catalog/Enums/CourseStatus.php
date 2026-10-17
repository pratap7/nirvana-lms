<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Course lifecycle state machine.
 *
 * Transitions:
 *   Draft → InReview (submit_for_review)
 *   InReview → Draft (request_changes)
 *   InReview → Published (approve)
 *   Published → Archived (archive)
 *   Archived → Draft (reactivate)
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::Draft, self::Published],
            self::Published => [self::Archived],
            self::Archived => [self::Draft],
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
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }
}
