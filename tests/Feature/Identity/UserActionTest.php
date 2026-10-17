<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Actions\ExpireGuestTeachers;
use App\Domain\Identity\Actions\RevokeRole;
use App\Domain\Identity\DTOs\CreateUserData;
use App\Domain\Identity\DTOs\RoleAssignmentData;
use App\Domain\Identity\Enums\Gender;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\UserCreated;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\ScopedRoleAssignment;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->branch = Branch::factory()->create();
});

it('creates a user with valid ULID, hashed password, and dispatches event', function (): void {
    Event::fake([UserCreated::class]);

    $data = new CreateUserData(
        firstName: 'Patanjali',
        lastName: 'Muni',
        email: 'patanjali@pramana-institute.org',
        password: 'SecretPassword123!',
        phone: '+919876543299',
        gender: Gender::Male,
        primaryBranchId: $this->branch->id,
    );

    $action = new CreateUser;
    $user = $action->execute($data);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->ulid)->toBeString()->toHaveLength(26)
        ->and($user->first_name)->toBe('Patanjali')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(Hash::check('SecretPassword123!', $user->password))->toBeTrue();

    Event::assertDispatched(UserCreated::class, fn ($e) => $e->user->id === $user->id);
});

it('assigns a scoped role with expiry and grants Spatie permissions', function (): void {
    $admin = User::factory()->create();
    $guestTeacher = User::factory()->create();
    $teacherRole = Role::findByName('teacher');

    $expiresAt = CarbonImmutable::now()->addDays(30);

    $data = new RoleAssignmentData(
        userId: $guestTeacher->id,
        roleId: $teacherRole->id,
        grantedBy: $admin->id,
        branchId: $this->branch->id,
        expiresAt: $expiresAt,
    );

    $action = new AssignRole;
    $assignment = $action->execute($data);

    expect($assignment)->toBeInstanceOf(ScopedRoleAssignment::class)
        ->and($assignment->ulid)->toBeString()->toHaveLength(26)
        ->and($assignment->isActive())->toBeTrue()
        ->and($guestTeacher->fresh()->hasRole('teacher'))->toBeTrue()
        ->and($guestTeacher->fresh()->can('attendance.mark'))->toBeTrue();
});

it('revokes a scoped role and removes Spatie role when no other active assignments remain', function (): void {
    $admin = User::factory()->create();
    $teacher = User::factory()->create();
    $role = Role::findByName('teacher');

    $assignment = (new AssignRole)->execute(new RoleAssignmentData(
        userId: $teacher->id,
        roleId: $role->id,
        grantedBy: $admin->id,
    ));

    expect($teacher->fresh()->hasRole('teacher'))->toBeTrue();

    (new RevokeRole)->execute($assignment, revokedBy: $admin->id);

    expect($assignment->fresh()->revoked_at)->not->toBeNull()
        ->and($assignment->fresh()->isActive())->toBeFalse()
        ->and($teacher->fresh()->hasRole('teacher'))->toBeFalse();
});

it('auto-expires guest teacher role assignments via scheduled action', function (): void {
    $admin = User::factory()->create();
    $guest = User::factory()->create();
    $role = Role::findByName('teacher');

    // Create an assignment that expired yesterday
    $assignment = (new AssignRole)->execute(new RoleAssignmentData(
        userId: $guest->id,
        roleId: $role->id,
        grantedBy: $admin->id,
        expiresAt: CarbonImmutable::now()->subDay(),
    ));

    expect($assignment->isExpired())->toBeTrue();

    $expiredCount = (new ExpireGuestTeachers)->execute();

    expect($expiredCount)->toBe(1)
        ->and($guest->fresh()->hasRole('teacher'))->toBeFalse();
});
