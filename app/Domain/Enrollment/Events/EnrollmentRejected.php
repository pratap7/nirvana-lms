<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Events;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentRejected
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly string $reason,
        public readonly ?User $actor = null
    ) {}
}
