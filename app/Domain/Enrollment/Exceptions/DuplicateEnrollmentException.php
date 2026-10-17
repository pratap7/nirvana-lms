<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Exceptions;

use RuntimeException;

class DuplicateEnrollmentException extends RuntimeException
{
    public function __construct(
        string $message = 'Student already has an active or pending enrollment in this course/batch.'
    ) {
        parent::__construct($message);
    }
}
