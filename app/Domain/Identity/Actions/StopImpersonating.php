<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\Auth;

class StopImpersonating extends BaseAction
{
    /**
     * Stop impersonating and restore original administrator session.
     */
    public function execute(): ?User
    {
        $adminId = session()->pull('impersonated_by');

        if (! $adminId) {
            return null;
        }

        /** @var User|null $admin */
        $admin = User::find($adminId);

        if ($admin !== null) {
            activity()
                ->causedBy($admin)
                ->log('user_impersonation_ended');

            Auth::login($admin);
        }

        return $admin;
    }
}
