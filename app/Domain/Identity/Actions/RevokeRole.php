<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Events\RoleRevoked;
use App\Domain\Identity\Models\ScopedRoleAssignment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Revokes a scoped role assignment.
 *
 * Marks the ScopedRoleAssignment as revoked and removes the Spatie role
 * only if no other active scoped assignments exist for that role.
 */
final class RevokeRole
{
    public function execute(ScopedRoleAssignment $assignment, int $revokedBy): void
    {
        DB::transaction(function () use ($assignment, $revokedBy): void {
            $assignment->update([
                'revoked_at' => now(),
                'revoked_by' => $revokedBy,
            ]);

            // Only remove the Spatie role if no other active scoped assignments exist
            $otherActiveAssignments = ScopedRoleAssignment::query()
                ->where('user_id', $assignment->user_id)
                ->where('role_id', $assignment->role_id)
                ->where('id', '!=', $assignment->id)
                ->active()
                ->exists();

            if (! $otherActiveAssignments) {
                $user = User::findOrFail($assignment->user_id);
                $role = Role::findOrFail($assignment->role_id);
                $user->removeRole($role);
            }

            event(new RoleRevoked($assignment));
        });
    }
}
