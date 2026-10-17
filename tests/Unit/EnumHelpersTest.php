<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;

it('retrieves all enum values', function (): void {
    expect(UserStatus::values())->toBe(['active', 'suspended', 'archived']);
});

it('retrieves all enum names', function (): void {
    expect(UserStatus::names())->toBe(['Active', 'Suspended', 'Archived']);
});

it('generates select options array', function (): void {
    $options = UserStatus::options();

    expect($options)->toBe([
        'active' => 'Active',
        'suspended' => 'Suspended',
        'archived' => 'Archived',
    ]);
});

it('finds enum case by label', function (): void {
    expect(UserStatus::tryFromLabel('Active'))->toBe(UserStatus::Active)
        ->and(UserStatus::tryFromLabel('suspended'))->toBe(UserStatus::Suspended)
        ->and(UserStatus::tryFromLabel('non_existent'))->toBeNull();
});
