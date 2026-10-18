<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class TeacherDashboard extends Component
{
    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($teacher);

        $batches = Batch::query()
            ->whereIn('id', $assignedBatchIds)
            ->with(['course', 'venue'])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', EnrollmentStatus::Active->value)])
            ->get();

        $activeStudentEnrollments = Enrollment::query()
            ->whereIn('batch_id', $assignedBatchIds)
            ->where('status', EnrollmentStatus::Active->value)
            ->with(['student', 'batch.course'])
            ->get();

        $totalActiveStudents = $activeStudentEnrollments->pluck('student_id')->unique()->count();

        // Upcoming Sessions for this teacher
        $upcomingSessions = Session::query()
            ->whereIn('batch_id', $assignedBatchIds)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->take(5)
            ->with('batch.course')
            ->get();

        // Submissions awaiting grading
        $pendingSubmissions = AssignmentSubmission::query()
            ->where('status', 'submitted')
            ->whereHas('assignment', fn ($q) => $q->whereIn('batch_id', $assignedBatchIds))
            ->count();

        // Detect At-Risk Students across assigned batches (attendance < 75% or 0 submissions)
        $atRiskStudents = [];
        foreach ($activeStudentEnrollments as $enrollment) {
            $student = $enrollment->student;
            if (! $student) {
                continue;
            }

            /** @var ?CourseProgress $progress */
            $progress = CourseProgress::query()
                ->where('user_id', $student->id)
                ->where('course_id', $enrollment->course_id)
                ->first();

            $attendanceRate = $progress ? $progress->attendance_percentage : 100.0;
            $isLowAttendance = $attendanceRate < 75.0;

            if ($isLowAttendance) {
                $atRiskStudents[] = [
                    'student' => $student,
                    'batch' => $enrollment->batch,
                    'attendance' => $attendanceRate,
                    'reason' => 'Low Attendance ('.$attendanceRate.'%)',
                ];
            }
        }

        return view('livewire.teacher.teacher-dashboard', [
            'batches' => $batches,
            'totalActiveStudents' => $totalActiveStudents,
            'upcomingSessions' => $upcomingSessions,
            'pendingSubmissions' => $pendingSubmissions,
            'atRiskStudents' => array_slice($atRiskStudents, 0, 6),
        ]);
    }
}
