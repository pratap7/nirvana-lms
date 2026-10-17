<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use App\Domain\Catalog\Enums\DifficultyLevel;
use Illuminate\Support\Str;

final readonly class CreateCourseData
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $branchId,
        public int $createdBy,
        public ?int $programId = null,
        public ?string $shortDescription = null,
        public ?string $longDescription = null,
        public DifficultyLevel $difficultyLevel = DifficultyLevel::AllLevels,
        public int $durationHours = 0,
        public ?int $maxStudents = null,
        public int $baseFeeAmount = 0,
        public string $baseFeeCurrency = 'INR',
        public int $sortOrder = 0,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'] ?? Str::slug($data['name']),
            branchId: (int) $data['branch_id'],
            createdBy: $userId,
            programId: isset($data['program_id']) && $data['program_id'] !== '' ? (int) $data['program_id'] : null,
            shortDescription: $data['short_description'] ?? null,
            longDescription: $data['long_description'] ?? null,
            difficultyLevel: isset($data['difficulty_level'])
                ? ($data['difficulty_level'] instanceof DifficultyLevel ? $data['difficulty_level'] : DifficultyLevel::from($data['difficulty_level']))
                : DifficultyLevel::AllLevels,
            durationHours: (int) ($data['duration_hours'] ?? 0),
            maxStudents: isset($data['max_students']) && $data['max_students'] !== '' ? (int) $data['max_students'] : null,
            baseFeeAmount: (int) ($data['base_fee_amount'] ?? 0),
            baseFeeCurrency: $data['base_fee_currency'] ?? 'INR',
            sortOrder: (int) ($data['sort_order'] ?? 0),
            metadata: $data['metadata'] ?? null,
        );
    }
}
