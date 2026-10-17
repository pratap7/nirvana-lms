<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDeletionRequest;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class RequestUserDeletion extends BaseAction
{
    /**
     * Submit an account deletion request with a 30-day grace period.
     * Immediately deactivates the account to prevent unauthorized access.
     */
    public function execute(User $user, ?string $reason = null): UserDeletionRequest
    {
        return DB::transaction(function () use ($user, $reason): UserDeletionRequest {
            $request = UserDeletionRequest::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => $reason,
                'requested_at' => now(),
                'scheduled_for' => now()->addDays(30),
            ]);

            $user->deactivate();

            activity()
                ->causedBy($user)
                ->performedOn($request)
                ->withProperties([
                    'scheduled_for' => $request->scheduled_for?->toIso8601String(),
                    'reason' => $reason,
                ])
                ->log('gdpr_deletion_requested');

            return $request;
        });
    }
}
