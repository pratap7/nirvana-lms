<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionResolver $resolver;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(PermissionResolver::class);

        $this->branch = Branch::create([
            'name' => 'Tapovan Rishikesh',
            'code' => 'TAP',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);
    }

    public function test_super_admin_has_unrestricted_permission_access(): void
    {
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->assignRole($role);

        $this->assertTrue($this->resolver->can($superAdmin, 'any.arbitrary.permission'));
        $this->assertTrue($this->resolver->can($superAdmin, 'finance.delete'));
    }

    public function test_deactivated_user_is_strictly_denied_all_permissions(): void
    {
        $user = User::create([
            'first_name' => 'Former',
            'last_name' => 'Staff',
            'email' => 'former@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Suspended,
            'is_deactivated' => true,
        ]);

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        // Even with Super Admin role, deactivation overrides and denies access
        $this->assertFalse($this->resolver->can($user, 'courses.view'));
    }

    public function test_scoped_role_assignment_grants_permission_only_within_target_scope(): void
    {
        $teacher = User::create([
            'first_name' => 'Acharya',
            'last_name' => 'Govinda',
            'email' => 'govinda@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $role = Role::create(['name' => 'Course Manager', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'courses.edit', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        // Assign role scoped to Course ID 42
        RoleAssignment::create([
            'user_id' => $teacher->id,
            'role_id' => $role->id,
            'scope_type' => 'course',
            'scope_id' => 42,
            'starts_at' => now()->subDay(),
        ]);

        // Has permission on Course 42
        $this->assertTrue($this->resolver->can($teacher, 'courses.edit', 'course', 42));

        // Denied permission on Course 99 (different scope)
        $this->assertFalse($this->resolver->can($teacher, 'courses.edit', 'course', 99));

        // Denied global scope
        $this->assertFalse($this->resolver->can($teacher, 'courses.edit'));
    }

    public function test_expired_role_assignments_are_ignored(): void
    {
        $user = User::create([
            'first_name' => 'Temp',
            'last_name' => 'Assistant',
            'email' => 'temp@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $role = Role::create(['name' => 'Attendance Taker', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'attendance.mark', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        // Expired yesterday
        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'global',
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->resolver->can($user, 'attendance.mark'));
    }
}
