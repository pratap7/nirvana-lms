<?php

declare(strict_types=1);

namespace App\Domain\Catalog\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Validated completion criteria rules for courses.
 *
 * @implements Arrayable<string, mixed>
 */
final class CompletionRule implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int|string>  $requiredLessons
     */
    public function __construct(
        public readonly int $minAttendancePercent = 80,
        public readonly int $minScorePercent = 70,
        public readonly array $requiredLessons = [],
        public readonly int $assignmentsRequired = 0,
        public readonly bool $manualApproval = false,
    ) {
        $this->validate();
    }

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minAttendancePercent: isset($data['min_attendance_percent']) ? (int) $data['min_attendance_percent'] : 80,
            minScorePercent: isset($data['min_score_percent']) ? (int) $data['min_score_percent'] : 70,
            requiredLessons: isset($data['required_lessons']) && is_array($data['required_lessons']) ? array_values($data['required_lessons']) : [],
            assignmentsRequired: isset($data['assignments_required']) ? (int) $data['assignments_required'] : 0,
            manualApproval: ! empty($data['manual_approval']),
        );
    }

    public static function fromJson(?string $json): self
    {
        if (empty($json)) {
            return self::defaults();
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return self::defaults();
        }

        return self::fromArray($decoded);
    }

    /**
     * Validates completion rule boundaries.
     *
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if ($this->minAttendancePercent < 0 || $this->minAttendancePercent > 100) {
            throw new InvalidArgumentException("min_attendance_percent must be between 0 and 100, given: {$this->minAttendancePercent}");
        }

        if ($this->minScorePercent < 0 || $this->minScorePercent > 100) {
            throw new InvalidArgumentException("min_score_percent must be between 0 and 100, given: {$this->minScorePercent}");
        }

        if ($this->assignmentsRequired < 0) {
            throw new InvalidArgumentException("assignments_required must be 0 or greater, given: {$this->assignmentsRequired}");
        }
    }

    /**
     * Evaluate if student stats satisfy this completion rule.
     *
     * @param  array<int|string>  $completedLessonIds
     */
    public function isSatisfiedBy(
        int $attendancePercent,
        int $scorePercent,
        array $completedLessonIds = [],
        int $completedAssignments = 0,
        bool $isManuallyApproved = false
    ): bool {
        if ($attendancePercent < $this->minAttendancePercent) {
            return false;
        }

        if ($scorePercent < $this->minScorePercent) {
            return false;
        }

        if ($completedAssignments < $this->assignmentsRequired) {
            return false;
        }

        if ($this->manualApproval && ! $isManuallyApproved) {
            return false;
        }

        // Check if all specific mandatory lessons are completed
        if (! empty($this->requiredLessons)) {
            $completedSet = array_flip(array_map('strval', $completedLessonIds));
            foreach ($this->requiredLessons as $requiredId) {
                if (! isset($completedSet[(string) $requiredId])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_attendance_percent' => $this->minAttendancePercent,
            'min_score_percent' => $this->minScorePercent,
            'required_lessons' => $this->requiredLessons,
            'assignments_required' => $this->assignmentsRequired,
            'manual_approval' => $this->manualApproval,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
