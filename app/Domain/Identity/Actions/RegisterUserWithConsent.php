<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\CreateUserData;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserConsent;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new user only after validating mandatory versioned consents.
 */
class RegisterUserWithConsent extends BaseAction
{
    public const array REQUIRED_CONSENTS = [
        'terms_and_conditions' => '1.0',
        'privacy_policy' => '1.0',
        'health_data_processing' => '1.0',
    ];

    /**
     * @param  array<string, bool>  $consentCheckboxes  ['terms_and_conditions' => true, ...]
     *
     * @throws ValidationException
     */
    public function execute(
        CreateUserData $userData,
        array $consentCheckboxes,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): User {
        // Enforce mandatory versioned consents
        $missingConsents = [];

        foreach (self::REQUIRED_CONSENTS as $type => $version) {
            if (empty($consentCheckboxes[$type])) {
                $missingConsents[$type] = 'You must accept the '.str_replace('_', ' ', $type).' to continue.';
            }
        }

        if (! empty($missingConsents)) {
            throw ValidationException::withMessages($missingConsents);
        }

        return DB::transaction(function () use ($userData, $ipAddress, $userAgent): User {
            // 1. Create the user account
            $createUser = new CreateUser;
            $user = $createUser->execute($userData);

            // 2. Persist versioned consent records with IP and User Agent
            foreach (self::REQUIRED_CONSENTS as $type => $version) {
                UserConsent::create([
                    'user_id' => $user->id,
                    'consent_type' => $type,
                    'version' => $version,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'consented_at' => now(),
                ]);
            }

            return $user;
        });
    }
}
