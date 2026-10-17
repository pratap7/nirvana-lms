<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum DifficultyLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case AllLevels = 'all_levels';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Beginner',
            self::Intermediate => 'Intermediate',
            self::Advanced => 'Advanced',
            self::AllLevels => 'All Levels',
        };
    }
}
