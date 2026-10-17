<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

enum BatchStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open for Enrollment',
            self::InProgress => 'In Progress (Active)',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'info',
            self::InProgress => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }
}
