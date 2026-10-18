<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Contracts;

use App\Support\Money;

interface Enrollable
{
    public function getCapacity(): int;

    public function getEnrolledCount(): int;

    public function allowsWaitlist(): bool;

    public function requiresApproval(): bool;

    public function getFee(): Money;
}
