<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Exceptions;

use RuntimeException;

class PrerequisiteNotMetException extends RuntimeException
{
    /**
     * @param  array<int, string>  $missingCourses
     */
    public function __construct(
        public readonly array $missingCourses,
        string $message = 'Student has not met the required prerequisite courses.'
    ) {
        $courseList = implode(', ', $missingCourses);
        parent::__construct("{$message} Missing: [{$courseList}]");
    }
}
