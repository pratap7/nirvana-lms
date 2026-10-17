<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonateUser extends BaseAction
{
    /**
     * Start impersonating target user.
     * Only admins with 'user.impersonate' can perform this.
     * Cannot impersonate Super Admin unless impersonator is Super Admin.
     */
    public function execute(User $admin, User $target): void
    {
        if (! $admin->can('user.impersonate')) {
            throw ValidationException::withMessages([
                'impersonate' => 'Unauthorized: You do not possess permission to impersonate users.',
            ]);
        }

        if ($target->hasAnyRole(['Super Admin', 'super_admin', 'super-admin']) && ! $admin->hasAnyRole(['Super Admin', 'super_admin', 'super-admin'])) {
            throw ValidationException::withMessages([
                'impersonate' => 'Security restriction: Super Administrators cannot be impersonated.',
            ]);
        }

        if ($admin->id === $target->id) {
            throw ValidationException::withMessages([
                'impersonate' => 'Cannot impersonate yourself.',
            ]);
        }

        // Store original admin ID in session
        session()->put('impersonated_by', $admin->id);

        activity()
            ->causedBy($admin)
            ->performedOn($target)
            ->withProperties([
                'admin_id' => $admin->id,
                'target_id' => $target->id,
                'target_email' => $target->email,
            ])
            ->log('user_impersonation_started');

        Auth::login($target);
    }
}
