<?php

declare(strict_types=1);

namespace App\Domain\Identity\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    /**
     * Handle an incoming request.
     * Ensures Admin and Moderator users have mandatory 2FA enabled and confirmed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Check if user holds privileged role requiring mandatory 2FA
        $requires2FA = $user->hasAnyRole(['Super Admin', 'Admin', 'Moderator', 'admin', 'moderator', 'super_admin']);

        if ($requires2FA && ! $user->hasTwoFactorEnabled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication (TOTP) is mandatory for privileged accounts. Please complete 2FA setup.',
                    'action_required' => 'two_factor_setup',
                ], 403);
            }

            // In Web context, redirect or abort with clear explanation
            abort(403, 'Two-factor authentication (TOTP) is mandatory for Administrator and Moderator portals. Please enable 2FA in your account settings.');
        }

        return $next($request);
    }
}
