<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Actions\ExportUserData;
use App\Domain\Identity\Actions\GeneratePhoneOtp;
use App\Domain\Identity\Actions\ImpersonateUser;
use App\Domain\Identity\Actions\RegisterUserWithConsent;
use App\Domain\Identity\Actions\RequestUserDeletion;
use App\Domain\Identity\Actions\StopImpersonating;
use App\Domain\Identity\Actions\VerifyPhoneOtp;
use App\Domain\Identity\Actions\ViewStudentHealthProfile;
use App\Domain\Identity\DTOs\CreateUserData;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\RoleAssignment;
use App\Domain\Identity\Models\StudentProfile;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserConsent;
use App\Domain\Identity\Services\TotpService;
use Carbon\CarbonImmutable;
use Database\Seeders\BranchSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->branch = Branch::first();
});

it('proves a teacher cannot access another teacher\'s course, but can access own course or scoped assignment', function (): void {
    $teacherRole = Role::findByName('teacher');

    $teacherA = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $teacherA->assignRole($teacherRole);

    $teacherB = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $teacherB->assignRole($teacherRole);

    $courseA = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $teacherA->id,
        'status' => CourseStatus::Draft,
    ]);

    $courseB = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $teacherB->id,
        'status' => CourseStatus::Draft,
    ]);

    // Teacher A can update their own course
    expect(Gate::forUser($teacherA)->allows('update', $courseA))->toBeTrue();

    // Teacher A CANNOT update Teacher B's course
    expect(Gate::forUser($teacherA)->allows('update', $courseB))->toBeFalse();

    // Now assign Teacher A a scoped role on Course B
    RoleAssignment::create([
        'user_id' => $teacherA->id,
        'role_id' => $teacherRole->id,
        'scope_type' => 'course',
        'scope_id' => $courseB->id,
        'starts_at' => CarbonImmutable::now()->subHour(),
        'expires_at' => CarbonImmutable::now()->addDays(7),
        'assigned_by' => $teacherB->id,
    ]);

    // Now Teacher A CAN update Course B due to active scoped assignment
    expect(Gate::forUser($teacherA->fresh())->allows('update', $courseB))->toBeTrue();
});

it('proves an expired guest teacher is denied course access', function (): void {
    $teacherRole = Role::findByName('guest-teacher');

    $guestTeacher = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $admin = User::factory()->create(['primary_branch_id' => $this->branch->id]);

    $course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $admin->id,
        'status' => CourseStatus::Draft,
    ]);

    // Active assignment
    $activeAssignment = RoleAssignment::create([
        'user_id' => $guestTeacher->id,
        'role_id' => $teacherRole->id,
        'scope_type' => 'course',
        'scope_id' => $course->id,
        'starts_at' => CarbonImmutable::now()->subDays(2),
        'expires_at' => CarbonImmutable::now()->addDays(5),
        'assigned_by' => $admin->id,
    ]);

    expect(Gate::forUser($guestTeacher->fresh())->allows('view', $course))->toBeTrue();

    // Expire the assignment
    $activeAssignment->update([
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    // Now denied
    expect(Gate::forUser($guestTeacher->fresh())->allows('view', $course))->toBeFalse();
    expect(Gate::forUser($guestTeacher->fresh())->allows('update', $course))->toBeFalse();
});

it('proves a new role created in UI immediately enforces its permissions without deploy', function (): void {
    $user = User::factory()->create(['primary_branch_id' => $this->branch->id]);

    // Initially cannot publish courses
    expect(Gate::forUser($user)->allows('course.publish'))->toBeFalse();

    // Dynamically create a new role and attach permission (simulating Filament RoleResource form submit)
    $curatorRole = Role::create([
        'name' => 'content-curator',
        'guard_name' => 'web',
    ]);
    $curatorRole->givePermissionTo('course.publish');

    $user->assignRole($curatorRole);

    // Gate immediately evaluates to true without cache issues or code deploy
    expect(Gate::forUser($user->fresh())->allows('course.publish'))->toBeTrue();
});

it('blocks registration without mandatory consents and records versioned consents on success', function (): void {
    $action = new RegisterUserWithConsent;

    $userData = new CreateUserData(
        firstName: 'Shankara',
        lastName: 'Acharya',
        email: 'shankara@advaita.org',
        password: 'BrahmanSatyam123!',
        primaryBranchId: $this->branch->id,
    );

    // Attempting registration without consent throws ValidationException
    expect(fn () => $action->execute($userData, [
        'terms_and_conditions' => false,
        'privacy_policy' => true,
    ]))->toThrow(ValidationException::class);

    // With mandatory consents
    $user = $action->execute(
        $userData,
        [
            'terms_and_conditions' => true,
            'privacy_policy' => true,
            'health_data_processing' => true,
        ],
        ipAddress: '127.0.0.1',
        userAgent: 'PestTestAgent/1.0'
    );

    expect($user)->toBeInstanceOf(User::class);

    // Check versioned consents recorded
    $consents = UserConsent::where('user_id', $user->id)->get();
    expect($consents)->toHaveCount(3);

    $termsConsent = $consents->firstWhere('consent_type', 'terms_and_conditions');
    expect($termsConsent->version)->toBe('1.0')
        ->and($termsConsent->ip_address)->toBe('127.0.0.1')
        ->and($termsConsent->user_agent)->toBe('PestTestAgent/1.0');
});

it('encrypts student sensitive profile at rest, restricts read to student.health.view, and audits every read', function (): void {
    $student = User::factory()->create(['primary_branch_id' => $this->branch->id]);

    $parqQuestions = [
        'heart_condition' => false,
        'chest_pain' => false,
        'dizziness' => false,
        'joint_bone_problem' => true,
        'blood_pressure_medication' => false,
    ];

    StudentProfile::create([
        'user_id' => $student->id,
        'emergency_contact_name' => 'Vyasa Muni',
        'emergency_contact_phone' => '+919876500000',
        'emergency_contact_relationship' => 'Guru',
        'parq_answers' => $parqQuestions,
        'medical_conditions' => 'Mild lower back sensitivity during Paschimottanasana.',
    ]);

    // Verify database row contains encrypted ciphertext, NOT plaintext
    $rawRow = DB::table('student_profiles')->where('user_id', $student->id)->first();
    expect($rawRow->emergency_contact_name)->not->toBe('Vyasa Muni')
        ->and($rawRow->medical_conditions)->not->toBe('Mild lower back sensitivity during Paschimottanasana.');

    // User without student.health.view permission cannot read
    $regularTeacher = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $action = new ViewStudentHealthProfile;

    expect(fn () => $action->execute($regularTeacher, $student))
        ->toThrow(AuthorizationException::class);

    // Doctor / Wellbeing Admin with permission CAN read
    $healthAdmin = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $healthAdmin->givePermissionTo('student.health.view');

    $initialActivityCount = Activity::where('description', 'student_health_profile_accessed')->count();

    $profile = $action->execute($healthAdmin, $student);

    expect($profile->emergency_contact_name)->toBe('Vyasa Muni')
        ->and($profile->medical_conditions)->toBe('Mild lower back sensitivity during Paschimottanasana.');

    // An audit log entry was created for this read
    $newActivityCount = Activity::where('description', 'student_health_profile_accessed')->count();
    expect($newActivityCount)->toBe($initialActivityCount + 1);

    $log = Activity::where('description', 'student_health_profile_accessed')->latest('id')->first();
    expect($log->causer_id)->toBe($healthAdmin->id)
        ->and($log->properties['student_id'])->toBe($student->id);
});

it('supports admin impersonation, displays banner context, prevents unauthorized super admin impersonation, and audits', function (): void {
    $admin = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $admin->givePermissionTo('user.impersonate');

    $student = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $superAdmin = User::factory()->create(['primary_branch_id' => $this->branch->id]);
    $superAdminRole = Role::findByName('super-admin');
    $superAdmin->assignRole($superAdminRole);

    // 1. Regular admin CANNOT impersonate Super Admin
    expect(fn () => (new ImpersonateUser)->execute($admin, $superAdmin))
        ->toThrow(ValidationException::class);

    // 2. Regular admin impersonates Student
    (new ImpersonateUser)->execute($admin, $student);

    expect(session('impersonated_by'))->toBe($admin->id)
        ->and(auth()->id())->toBe($student->id);

    // Audit log created
    $log = Activity::where('description', 'user_impersonation_started')->latest('id')->first();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($student->id);

    // 3. Stop impersonating restores original admin
    (new StopImpersonating)->execute();

    expect(session()->has('impersonated_by'))->toBeFalse()
        ->and(auth()->id())->toBe($admin->id);

    $stopLog = Activity::where('description', 'user_impersonation_ended')->latest('id')->first();
    expect($stopLog->causer_id)->toBe($admin->id);
});

it('generates, verifies, and handles phone OTP flow', function (): void {
    $phone = '+919988776655';
    $user = User::factory()->create([
        'phone' => $phone,
        'primary_branch_id' => $this->branch->id,
    ]);

    $generateAction = new GeneratePhoneOtp;
    $code = $generateAction->execute($phone);

    expect($code)->toBeString()->toHaveLength(6);

    // Verify with invalid code fails
    $verifyAction = new VerifyPhoneOtp;
    expect(fn () => $verifyAction->execute($phone, '000000'))
        ->toThrow(ValidationException::class);

    // Verify with valid code returns user
    $verifiedUser = $verifyAction->execute($phone, $code);
    expect($verifiedUser)->not->toBeNull()
        ->and($verifiedUser->id)->toBe($user->id);
});

it('generates TOTP secrets and verifies OTP codes properly', function (): void {
    $totpService = new TotpService;

    $secret = $totpService->generateSecret();
    expect($secret)->toBeString()->toHaveLength(16);

    $qrUri = $totpService->getOtpAuthUri('Pramana Institute', 'patanjali@pramana.org', $secret);
    expect($qrUri)->toContain('otpauth://totp/')
        ->and($qrUri)->toContain('patanjali%40pramana.org');

    $recoveryCodes = $totpService->generateRecoveryCodes();
    expect($recoveryCodes)->toHaveCount(8);
    expect($recoveryCodes[0])->toBeString()->toHaveLength(10);

    // Verify generated code
    $currentSlice = (int) floor(time() / 30);
    $validCode = $totpService->calculateCode($secret, $currentSlice);
    expect($totpService->verify($secret, $validCode))->toBeTrue();
    expect($totpService->verify($secret, '000000'))->toBeFalse();
});

it('supports GDPR/DPDP data export and deletion request workflow', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Adi',
        'last_name' => 'Shankara',
        'email' => 'adi@shankara.org',
        'primary_branch_id' => $this->branch->id,
    ]);

    // 1. Data Export
    $exportAction = new ExportUserData;
    $data = $exportAction->execute($user);

    expect($data)->toHaveKeys(['personal_profile', 'consents', 'roles'])
        ->and($data['personal_profile']['email'])->toBe('adi@shankara.org');

    // 2. Request Account Deletion
    $deletionAction = new RequestUserDeletion;
    $request = $deletionAction->execute($user, 'Exercising right to be forgotten under DPDP.');

    expect($request->user_id)->toBe($user->id)
        ->and($request->status)->toBe('pending')
        ->and($request->reason)->toContain('DPDP');
});

it('denies permissions when a user account is deactivated', function (): void {
    $teacherRole = Role::findByName('teacher');
    $user = User::factory()->create([
        'primary_branch_id' => $this->branch->id,
        'deactivated_at' => CarbonImmutable::now()->subDay(),
        'is_deactivated' => true,
    ]);
    $user->assignRole($teacherRole);

    expect(Gate::forUser($user)->allows('course.create'))->toBeFalse();
});
