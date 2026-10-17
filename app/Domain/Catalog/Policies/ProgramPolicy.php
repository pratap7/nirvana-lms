<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Program;
use App\Domain\Identity\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('program.list');
    }

    public function view(User $user, Program $program): bool
    {
        return $user->can('program.list');
    }

    public function create(User $user): bool
    {
        return $user->can('program.manage');
    }

    public function update(User $user, Program $program): bool
    {
        return $user->can('program.manage');
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->can('program.manage');
    }
}
