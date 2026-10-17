<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

enum SessionTeacherRole: string
{
    case Lead = 'lead';
    case Substitute = 'substitute';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead Teacher / Acharya',
            self::Substitute => 'Substitute Teacher',
            self::Assistant => 'Teaching Assistant',
        };
    }
}
