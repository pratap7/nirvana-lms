<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Enums\Gender;

/**
 * Data Transfer Object for creating a new user.
 */
final readonly class CreateUserData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public ?string $phone = null,
        public ?string $dateOfBirth = null,
        public ?Gender $gender = null,
        public ?int $primaryBranchId = null,
        public string $timezone = 'Asia/Kolkata',
        public string $locale = 'en',
        public ?array $metadata = null,
    ) {}

    /**
     * Create from a validated request array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'],
            password: $data['password'],
            phone: $data['phone'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            gender: isset($data['gender']) ? Gender::from($data['gender']) : null,
            primaryBranchId: $data['primary_branch_id'] ?? null,
            timezone: $data['timezone'] ?? 'Asia/Kolkata',
            locale: $data['locale'] ?? 'en',
            metadata: $data['metadata'] ?? null,
        );
    }
}
