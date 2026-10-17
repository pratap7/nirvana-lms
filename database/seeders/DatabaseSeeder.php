<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            BranchSeeder::class,
            SettingSeeder::class,
        ]);

        $rishikeshBranch = Branch::where('code', 'RSH')->first();

        // 1. Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pramana-institute.org'],
            [
                'first_name' => 'Acharya',
                'last_name' => 'Pramana',
                'password' => Hash::make('Pramana@2026!'),
                'phone' => '+919876543210',
                'primary_branch_id' => $rishikeshBranch?->id,
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
            ]
        );
        $admin->assignRole('admin');

        // 2. Demo Teacher
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@pramana-institute.org'],
            [
                'first_name' => 'Swami',
                'last_name' => 'Vidyananda',
                'password' => Hash::make('Pramana@2026!'),
                'phone' => '+919876543211',
                'primary_branch_id' => $rishikeshBranch?->id,
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
            ]
        );
        $teacher->assignRole('teacher');

        // 3. Demo Student
        $student = User::firstOrCreate(
            ['email' => 'student@pramana-institute.org'],
            [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'password' => Hash::make('Pramana@2026!'),
                'phone' => '+919876543212',
                'primary_branch_id' => $rishikeshBranch?->id,
                'status' => UserStatus::Active,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
            ]
        );
        $student->assignRole('student');
    }
}
