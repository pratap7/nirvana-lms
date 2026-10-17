<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionResolver;

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

        if ($course->created_by === $user->id) {
            return true;
        }

        return app(PermissionResolver::class)->can($user, 'course.view', $course);
    }

    public function create(User $user): bool
    {
        return $user->can('course.create');
    }

    public function update(User $user, Course $course): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'super_admin'])) {
            return true;
        }

        if ($course->created_by === $user->id) {
            return $user->can('course.update');
        }

        return app(PermissionResolver::class)->can($user, 'course.update', $course);
    }

    public function delete(User $user, Course $course): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'super_admin'])) {
            return true;
        }

        if ($course->created_by === $user->id) {
            return $user->can('course.delete');
        }

        return app(PermissionResolver::class)->can($user, 'course.delete', $course);
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
