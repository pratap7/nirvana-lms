<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Exceptions;

use RuntimeException;

class BatchFullException extends RuntimeException
{
    public function __construct(
        string $message = 'Batch has reached maximum capacity and waitlist is closed or full.'
    ) {
        parent::__construct($message);
    }
}
