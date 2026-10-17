<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Read all module permissions from config/permissions.php
        /** @var array<string, array<string, string>> $modules */
        $modules = config('permissions.modules', []);

        foreach ($modules as $permissions) {
            foreach (array_keys($permissions) as $permissionName) {
                Permission::firstOrCreate([
                    'name' => (string) $permissionName,
                    'guard_name' => 'web',
                ]);
            }
        }

        // 2. Read role configurations from config/permissions.php
        /** @var array<string, array{description?: string, is_system?: bool, all_permissions?: bool, permissions?: array<string>}> $roles */
        $roles = config('permissions.roles', []);

        foreach ($roles as $roleName => $roleConfig) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if (! empty($roleConfig['all_permissions'])) {
                $role->syncPermissions(Permission::all());
            } elseif (! empty($roleConfig['permissions'])) {
                $role->syncPermissions($roleConfig['permissions']);
            }
        }

        // Also ensure lowercase aliases exist for backwards compatibility (e.g. 'admin', 'teacher')
        $aliases = [
            'admin' => 'Admin',
            'moderator' => 'Moderator',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'guest_teacher' => 'Guest Teacher',
            'guest-teacher' => 'Guest Teacher',
            'super_admin' => 'Super Admin',
            'super-admin' => 'Super Admin',
        ];

        foreach ($aliases as $alias => $sourceRoleName) {
            $sourceRole = Role::findByName($sourceRoleName, 'web');
            $aliasRole = Role::firstOrCreate(['name' => $alias, 'guard_name' => 'web']);
            $aliasRole->syncPermissions($sourceRole->permissions);
        }
    }
}
