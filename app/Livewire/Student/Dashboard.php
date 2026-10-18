<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Services\StudentProgressService;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(StudentProgressService $progressService): View
    {
        /** @var User $student */
        $student = auth()->user();

        // 1. Enrolled Courses
        $enrollments = Enrollment::query()
            ->with(['course.modules.lessons', 'batch'])
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->get();

        $enrolledCoursesData = $enrollments->map(function (Enrollment $enrollment) use ($student, $progressService): array {
            $course = $enrollment->course;
            $batch = $enrollment->batch;
            $progress = $course ? $progressService->calculateProgress($student, $course, $batch) : null;

            return [
                'enrollment' => $enrollment,
                'course' => $course,
                'batch' => $batch,
                'progress' => $progress,
                'overall_percentage' => $progress ? (float) $progress->overall_percentage : 0.0,
                'lessons_completed' => $progress ? $progress->lessons_completed : 0,
                'total_lessons' => $progress ? $progress->total_required_lessons : 0,
                'last_lesson_id' => $progress?->last_accessed_lesson_id,
            ];
        });

        // 2. Next Upcoming Sessions (for active batches)
        $activeBatchIds = $enrollments->pluck('batch_id')->filter()->unique();
        $nextSessions = Session::query()
            ->with(['batch.course'])
            ->whereIn('batch_id', $activeBatchIds)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->take(3)
            ->get();

        // 3. Due Assignments
        $courseIds = $enrollments->pluck('course_id')->filter()->unique();
        $dueAssignments = Assignment::query()
            ->with(['course', 'submissions' => fn ($q) => $q->where('user_id', $student->id)])
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->take(4)
            ->get();

        // 4. Announcements
        $announcements = Announcement::query()
            ->where(function ($q) use ($courseIds, $activeBatchIds): void {
                $q->whereIn('course_id', $courseIds)
                    ->orWhereIn('batch_id', $activeBatchIds)
                    ->orWhereNull('course_id');
            })
            ->latest('published_at')
            ->take(3)
            ->get();

        // 5. Outstanding Fees & Installments
        $outstandingInvoices = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoicePaymentStatus::Pending->value, InvoicePaymentStatus::PartiallyPaid->value])
            ->get();

        $totalOutstandingMinor = $outstandingInvoices->sum(fn (Invoice $inv): int => $inv->getRemainingBalance()->getAmount());

        return view('livewire.student.dashboard', [
            'student' => $student,
            'enrolledCourses' => $enrolledCoursesData,
            'nextSessions' => $nextSessions,
            'dueAssignments' => $dueAssignments,
            'announcements' => $announcements,
            'outstandingInvoices' => $outstandingInvoices,
            'totalOutstandingMinor' => $totalOutstandingMinor,
        ])->layout('layouts.student', ['title' => 'Dashboard']);
    }
}
