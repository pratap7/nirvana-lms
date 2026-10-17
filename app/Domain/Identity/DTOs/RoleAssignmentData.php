<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use Carbon\CarbonImmutable;

/**
 * Data Transfer Object for assigning a role with scope and optional expiry.
 */
final readonly class RoleAssignmentData
{
    public function __construct(
        public int $userId,
        public int $roleId,
        public int $grantedBy,
        public ?string $scopeableType = null,
        public ?int $scopeableId = null,
        public ?int $branchId = null,
        public ?CarbonImmutable $expiresAt = null,
    ) {}

    /**
     * Create from a validated request array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            roleId: $data['role_id'],
            grantedBy: $data['granted_by'],
            scopeableType: $data['scopeable_type'] ?? null,
            scopeableId: $data['scopeable_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            expiresAt: isset($data['expires_at'])
                ? CarbonImmutable::parse($data['expires_at'])
                : null,
        );
    }
}
