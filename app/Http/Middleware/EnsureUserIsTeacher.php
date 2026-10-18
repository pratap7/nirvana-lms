<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Catalog\Models\CourseTeacher;
use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\SessionTeacher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Allow explicit roles
        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator', 'Teacher', 'teacher', 'admin', 'super_admin'])) {
            return $next($request);
        }

        // Allow teachers actively assigned to courses, sessions, or scoped roles
        $isTeacher = CourseTeacher::where('user_id', $user->id)->exists()
            || SessionTeacher::where('user_id', $user->id)->exists()
            || RoleAssignment::where('user_id', $user->id)->active()->exists();

        if (! $isTeacher) {
            abort(403, 'Access denied. You do not have faculty / teacher privileges.');
        }

        return $next($request);
    }
}
