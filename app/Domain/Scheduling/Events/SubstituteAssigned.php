<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubstituteAssigned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Session $session,
        public readonly User $substituteTeacher,
        public readonly ?User $originalTeacher = null,
        public readonly ?User $approvedBy = null,
        public readonly ?string $reason = null,
    ) {}
}
