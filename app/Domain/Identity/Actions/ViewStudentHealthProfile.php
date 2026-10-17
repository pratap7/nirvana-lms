<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\StudentProfile;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Auth\Access\AuthorizationException;

class ViewStudentHealthProfile extends BaseAction
{
    /**
     * Access student's encrypted health profile and emergency contact details.
     * Enforces 'student.health.view' permission and writes an audit log entry on every read.
     *
     * @throws AuthorizationException
     */
    public function execute(User $viewer, User $student): StudentProfile
    {
        $isSelf = $viewer->id === $student->id;

        if (! $isSelf && ! $viewer->can('student.health.view')) {
            throw new AuthorizationException('Access denied: You do not have permission to view sensitive student health declarations.');
        }

        /** @var StudentProfile $profile */
        $profile = $student->studentProfile ?? StudentProfile::firstOrCreate(['user_id' => $student->id]);

        // Audit the access to sensitive medical/health information
        activity()
            ->causedBy($viewer)
            ->performedOn($profile)
            ->withProperties([
                'student_id' => $student->id,
                'viewer_id' => $viewer->id,
                'accessed_at' => now()->toIso8601String(),
            ])
            ->log('student_health_profile_accessed');

        return $profile;
    }
}
