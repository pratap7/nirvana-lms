<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\ScopedRoleAssignment;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled action to revoke expired role assignments.
 *
 * Designed to be called from a scheduled command (e.g. daily).
 * Handles Guest Teacher auto-expiry cleanly.
 */
final class ExpireGuestTeachers
{
    public function execute(): int
    {
        $revokeRole = new RevokeRole;
        $count = 0;

        ScopedRoleAssignment::expired()
            ->with('user:id,first_name,last_name,email')
            ->chunk(100, function ($assignments) use ($revokeRole, &$count): void {
                foreach ($assignments as $assignment) {
                    $revokeRole->execute($assignment, revokedBy: $assignment->granted_by);
                    $count++;

                    Log::info('Expired scoped role assignment', [
                        'assignment_ulid' => $assignment->ulid,
                        'user_id' => $assignment->user_id,
                        'role_id' => $assignment->role_id,
                    ]);
                }
            });

        return $count;
    }
}
