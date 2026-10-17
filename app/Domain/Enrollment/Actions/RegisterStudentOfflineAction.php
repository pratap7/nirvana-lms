<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterStudentOfflineAction extends BaseAction
{
    public function __construct(
        protected RequestEnrollmentAction $requestEnrollmentAction,
    ) {}

    /**
     * Offline enrollment registered by a moderator on behalf of a student.
     * Automatically creates a student account if one does not already exist.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: ?string, address?: ?string}  $studentData
     */
    public function execute(
        User $moderator,
        Batch $batch,
        array $studentData,
        ?string $notes = null,
        bool $autoApprove = true
    ): Enrollment {
        return DB::transaction(function () use ($moderator, $batch, $studentData, $notes, $autoApprove) {
            /** @var User|null $student */
            $student = User::where('email', $studentData['email'])->first();

            if ($student === null) {
                $student = User::create([
                    'first_name' => $studentData['first_name'],
                    'last_name' => $studentData['last_name'],
                    'email' => $studentData['email'],
                    'phone' => $studentData['phone'] ?? '+91'.random_int(7000000000, 9999999999),
                    'password' => Hash::make(Str::random(24)),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'primary_branch_id' => $batch->branch_id,
                ]);

                $student->assignRole('Student');

                activity()
                    ->causedBy($moderator)
                    ->performedOn($student)
                    ->log('student_account_created_offline');
            }

            return $this->requestEnrollmentAction->execute(
                student: $student,
                batch: $batch,
                consentGiven: true,
                consentIp: 'offline-registration',
                notes: $notes ?? 'Offline registration by moderator '.$moderator->full_name,
                forceApprove: $autoApprove,
                registeredBy: $moderator
            );
        });
    }
}
