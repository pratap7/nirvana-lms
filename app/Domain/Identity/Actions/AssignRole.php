<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\RoleAssignmentData;
use App\Domain\Identity\Events\RoleAssigned;
use App\Domain\Identity\Models\ScopedRoleAssignment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Assigns a role to a user with optional scope, branch, and expiry.
 *
 * This creates both:
 * 1. A standard Spatie role assignment (for fast permission checks)
 * 2. A ScopedRoleAssignment record (for scope, expiry, and audit trail)
 */
final class AssignRole
{
    public function execute(RoleAssignmentData $data): ScopedRoleAssignment
    {
        return DB::transaction(function () use ($data): ScopedRoleAssignment {
            $user = User::findOrFail($data->userId);
            $role = Role::findOrFail($data->roleId);

            // Assign via Spatie for fast permission checks
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Create scoped assignment for audit + scope + expiry
            $assignment = ScopedRoleAssignment::create([
                'user_id' => $data->userId,
                'role_id' => $data->roleId,
                'scopeable_type' => $data->scopeableType,
                'scopeable_id' => $data->scopeableId,
                'branch_id' => $data->branchId,
                'granted_by' => $data->grantedBy,
                'expires_at' => $data->expiresAt,
            ]);

            event(new RoleAssigned($assignment));

            return $assignment;
        });
    }
}
