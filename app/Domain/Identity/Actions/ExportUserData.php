<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\StudentProfile;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserConsent;
use App\Support\Actions\BaseAction;

class ExportUserData extends BaseAction
{
    /**
     * Export all personal data stored for a user in GDPR / DPDP compliant JSON format.
     *
     * @return array<string, mixed>
     */
    public function execute(User $user): array
    {
        $consentsList = [];
        /** @var UserConsent $consent */
        foreach ($user->consents as $consent) {
            $consentsList[] = [
                'type' => $consent->consent_type,
                'version' => $consent->version,
                'consented_at' => $consent->consented_at->toIso8601String(),
                'ip_address' => $consent->ip_address,
            ];
        }

        $rolesList = [];
        /** @var RoleAssignment $assignment */
        foreach ($user->roleAssignments as $assignment) {
            $rolesList[] = [
                'role' => $assignment->role?->name,
                'scope_type' => $assignment->scope_type,
                'scope_id' => $assignment->scope_id,
                'starts_at' => $assignment->starts_at?->toIso8601String(),
                'expires_at' => $assignment->expires_at?->toIso8601String(),
            ];
        }

        $export = [
            'personal_profile' => [
                'ulid' => $user->ulid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'gender' => $user->gender?->value,
                'address' => [
                    'line_1' => $user->address_line_1,
                    'line_2' => $user->address_line_2,
                    'city' => $user->city,
                    'state' => $user->state,
                    'country' => $user->country,
                    'postal_code' => $user->postal_code,
                ],
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'consents' => $consentsList,
            'roles' => $rolesList,
        ];

        $profile = $user->studentProfile;
        if ($profile instanceof StudentProfile) {
            $export['student_profile'] = [
                'emergency_contact_name' => $profile->emergency_contact_name,
                'emergency_contact_phone' => $profile->emergency_contact_phone,
                'emergency_contact_relationship' => $profile->emergency_contact_relationship,
                'parq_health_declaration' => $profile->parq_answers,
                'medical_conditions' => $profile->medical_conditions,
                'allergies' => $profile->allergies,
                'dietary_requirements' => $profile->dietary_requirements,
            ];
        }

        activity()
            ->causedBy(auth()->user() ?? $user)
            ->performedOn($user)
            ->log('gdpr_user_data_exported');

        return $export;
    }
}
