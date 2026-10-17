<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\GuestTeacherInvitation;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class InviteGuestTeacher extends BaseAction
{
    /**
     * Send email invitation for a guest teacher to access named batches/sessions.
     */
    public function execute(
        string $email,
        string $firstName,
        string $lastName,
        string $scopeType,
        int $scopeId,
        int $invitedBy,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $expiresAt = null
    ): GuestTeacherInvitation {
        $token = Str::random(48);

        $invitation = GuestTeacherInvitation::create([
            'email' => strtolower(trim($email)),
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'scope_type' => strtolower($scopeType),
            'scope_id' => $scopeId,
            'token' => $token,
            'starts_at' => $startsAt ?? now(),
            'expires_at' => $expiresAt ?? now()->addDays(30),
            'invited_by' => $invitedBy,
        ]);

        activity()
            ->performedOn($invitation)
            ->causedBy(User::find($invitedBy))
            ->withProperties([
                'email' => $email,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ])
            ->log('guest_teacher_invited');

        return $invitation;
    }
}
