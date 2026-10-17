<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;

/**
 * Policy for User model — all checks are permission-based, never role-based.
 */
class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->can('user.list');
    }

    public function view(User $authUser, User $user): bool
    {
        // Users can always view themselves
        if ($authUser->id === $user->id) {
            return true;
        }

        return $authUser->can('user.view');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('user.create');
    }

    public function update(User $authUser, User $user): bool
    {
        // Users can update their own profile
        if ($authUser->id === $user->id) {
            return true;
        }

        return $authUser->can('user.update');
    }

    public function delete(User $authUser, User $user): bool
    {
        // Cannot delete yourself
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->can('user.delete');
    }

    public function suspend(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->can('user.suspend');
    }

    public function impersonate(User $authUser, User $user): bool
    {
        if ($authUser->id === $user->id) {
            return false;
        }

        return $authUser->can('user.impersonate');
    }

    public function assignRole(User $authUser): bool
    {
        return $authUser->can('role.assign');
    }

    public function revokeRole(User $authUser): bool
    {
        return $authUser->can('role.revoke');
    }
}
