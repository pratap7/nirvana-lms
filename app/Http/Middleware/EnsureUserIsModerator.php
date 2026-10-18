<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsModerator
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->hasRole(['Admin', 'Super Admin', 'Moderator', 'moderator', 'admin', 'super_admin'])
            || $user->can('access-moderator-console')) {
            return $next($request);
        }

        abort(403, 'Access denied. You do not have academic moderator privileges.');
    }
}
