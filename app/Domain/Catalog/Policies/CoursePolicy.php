<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('course.list');
    }

    public function view(User $user, Course $course): bool
    {
        if ($course->isPublished()) {
            return true;
        }

        return $user->can('course.view');
    }

    public function create(User $user): bool
    {
        return $user->can('course.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('course.update');
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can('course.delete');
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can('course.publish');
    }

    public function approve(User $user, Course $course): bool
    {
        return $user->can('course.approve');
    }
}
