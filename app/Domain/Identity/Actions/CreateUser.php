<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\CreateUserData;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserCreated;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a new user account.
 *
 * Single-purpose action class — all user creation goes through here,
 * whether triggered from admin panel, registration form, or API.
 */
final class CreateUser
{
    public function execute(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'phone' => $data->phone,
                'date_of_birth' => $data->dateOfBirth,
                'gender' => $data->gender,
                'primary_branch_id' => $data->primaryBranchId,
                'status' => UserStatus::Active,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'metadata' => $data->metadata,
            ]);

            event(new UserCreated($user));

            return $user;
        });
    }
}
