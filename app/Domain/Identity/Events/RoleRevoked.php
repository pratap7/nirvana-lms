<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\ScopedRoleAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ScopedRoleAssignment $assignment,
    ) {}
}
