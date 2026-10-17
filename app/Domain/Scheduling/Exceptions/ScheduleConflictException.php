<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

class ScheduleConflictException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $conflictType = 'general',
        public readonly ?int $conflictingEntityId = null,
        public readonly ?int $conflictingSessionId = null,
    ) {
        parent::__construct($message);
    }
}
