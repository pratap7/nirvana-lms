<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branch.manage') || $user->can('user.list');
    }

    public function view(User $user, Branch $branch): bool
    {
        // Users can view their own branch
        if ($user->primary_branch_id === $branch->id) {
            return true;
        }

        return $user->can('branch.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('branch.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branch.manage');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branch.manage');
    }
}
