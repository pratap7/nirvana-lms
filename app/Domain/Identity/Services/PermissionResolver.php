<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class PermissionResolver
{
    /**
     * Resolve whether a user possesses the requested permission, optionally scoped to a model.
     *
     * @param  Model|string|null  $scope  (e.g., Course instance, Batch instance, or 'course')
     * @param  int|string|null  $scopeId
     */
    public function can(User $user, string $permission, mixed $scope = null, mixed $scopeId = null): bool
    {
        if ($user->isDeactivated()) {
            return false;
        }

        // 1. Super Admin bypass: users with Super Admin role have unrestricted access
        if ($this->hasSuperAdminPrivilege($user)) {
            return true;
        }

        // Extract scopeType and scopeIdentifier
        $scopeType = 'global';
        $targetScopeId = null;

        if ($scope instanceof Model) {
            $scopeType = $this->normalizeScopeType($scope);
            $targetScopeId = $scope->getKey();
        } elseif (is_string($scope) && $scope !== 'global') {
            $scopeType = strtolower($scope);
            $targetScopeId = $scopeId !== null ? (int) $scopeId : null;
        }

        $candidatePermissions = [$permission];
        if (! str_contains($permission, '.') && $scopeType !== 'global') {
            $candidatePermissions[] = "{$scopeType}.{$permission}";
        }

        // 2. Fetch all currently active, non-expired role assignments for this user
        $activeAssignments = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->active()
            ->with('role.permissions')
            ->get();

        foreach ($activeAssignments as $assignment) {
            // Check if assignment is applicable:
            // For global scope check: global assignment matches
            // For specific scope check: only matching scope_type and scope_id match
            $isScopeMatch = ($scopeType === 'global' && $assignment->scope_type === 'global')
                || ($assignment->scope_type === $scopeType && ($targetScopeId === null || (int) $assignment->scope_id === (int) $targetScopeId));

            if (! $isScopeMatch) {
                continue;
            }

            /** @var Role $role */
            $role = $assignment->role;
            $rolePermissions = $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')
                : $role->permissions()->pluck('name');

            foreach ($candidatePermissions as $candidate) {
                if ($rolePermissions->contains($candidate)) {
                    return true;
                }
            }
        }

        // 3. Fallback: check Spatie direct global roles/permissions if global scope
        if ($scopeType === 'global') {
            foreach ($candidatePermissions as $candidate) {
                try {
                    if ($user->hasPermissionTo($candidate, 'web')) {
                        return true;
                    }
                } catch (PermissionDoesNotExist) {
                    // Not a registered Spatie permission
                }
            }
        }

        return false;
    }

    private function hasSuperAdminPrivilege(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'super_admin', 'super-admin']);
    }

    private function normalizeScopeType(Model $model): string
    {
        $class = class_basename($model);

        return strtolower($class);
    }
}
