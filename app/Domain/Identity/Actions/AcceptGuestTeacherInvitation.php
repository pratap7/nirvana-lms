<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\GuestTeacherInvitation;
use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AcceptGuestTeacherInvitation extends BaseAction
{
    /**
     * Accept a guest teacher invitation, creating or activating user and binding scoped role assignment.
     */
    public function execute(string $token, ?string $password = null): User
    {
        /** @var GuestTeacherInvitation|null $invitation */
        $invitation = GuestTeacherInvitation::where('token', $token)->first();

        if ($invitation === null || $invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation is invalid or has already been used.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'This guest teacher invitation has expired.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $password): User {
            // Find existing user or create a new user
            $user = User::firstOrCreate(
                ['email' => $invitation->email],
                [
                    'first_name' => $invitation->first_name,
                    'last_name' => $invitation->last_name,
                    'password' => Hash::make($password ?? 'PramanaGuest@'.date('Y')),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                ]
            );

            // Locate the Guest Teacher role
            $guestRole = Role::findByName('Guest Teacher', 'web');

            // Ensure Spatie role is assigned
            if (! $user->hasRole($guestRole)) {
                $user->assignRole($guestRole);
            }

            // Create custom scoped, time-limited RoleAssignment
            RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $guestRole->id,
                'scope_type' => $invitation->scope_type,
                'scope_id' => $invitation->scope_id,
                'starts_at' => $invitation->starts_at,
                'expires_at' => $invitation->expires_at,
                'assigned_by' => $invitation->invited_by,
            ]);

            // Mark invitation accepted
            $invitation->update(['accepted_at' => now()]);

            activity()
                ->performedOn($user)
                ->withProperties([
                    'invitation_id' => $invitation->id,
                    'scope_type' => $invitation->scope_type,
                    'scope_id' => $invitation->scope_id,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ])
                ->log('guest_teacher_accepted');

            return $user;
        });
    }
}
