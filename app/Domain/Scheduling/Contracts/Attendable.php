<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Contracts;

use Carbon\CarbonInterface;

interface Attendable
{
    public function getAttendableTitle(): string;

    public function getStartsAt(): CarbonInterface;

    public function getEndsAt(): CarbonInterface;
}
