<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class BatchRoster extends Component
{
    public ?string $selectedBatchUlid = null;

    public string $search = '';

    public function mount(?string $batch, TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($batch) {
            /** @var Batch $targetBatch */
            $targetBatch = Batch::query()->where('ulid', $batch)->firstOrFail();

            // CRITICAL ACCEPTANCE CHECK: A teacher cannot view another teacher's batch roster
            if (! $scopeService->canAccessBatch($user, $targetBatch)) {
                abort(403, "Access Denied: You are not assigned to instruct the roster for {$targetBatch->name}.");
            }

            $this->selectedBatchUlid = $targetBatch->ulid;
        } else {
            // Default to first assigned batch
            $firstAssignedId = $scopeService->getAssignedBatchIds($user)->first();
            if ($firstAssignedId) {
                $defaultBatch = Batch::query()->find($firstAssignedId);
                $this->selectedBatchUlid = $defaultBatch?->ulid;
            }
        }
    }

    public function selectBatch(string $batchUlid, TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Batch $targetBatch */
        $targetBatch = Batch::query()->where('ulid', $batchUlid)->firstOrFail();

        // CRITICAL ACCEPTANCE CHECK
        if (! $scopeService->canAccessBatch($user, $targetBatch)) {
            abort(403, "Access Denied: You cannot view another teacher's batch roster.");
        }

        $this->selectedBatchUlid = $targetBatch->ulid;
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $user */
        $user = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($user);

        /** @var Collection<int, Batch> $assignedBatches */
        $assignedBatches = Batch::query()
            ->whereIn('id', $assignedBatchIds)
            ->with('course')
            ->get();

        $currentBatch = $this->selectedBatchUlid
            ? Batch::query()->where('ulid', $this->selectedBatchUlid)->first()
            : $assignedBatches->first();

        $rosterData = [];

        if ($currentBatch) {
            // Re-verify access
            if (! $scopeService->canAccessBatch($user, $currentBatch)) {
                abort(403, "Access Denied: You cannot view another teacher's batch roster.");
            }

            $enrollmentsQuery = Enrollment::query()
                ->where('batch_id', $currentBatch->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->with('student');

            if ($this->search !== '') {
                $enrollmentsQuery->whereHas('student', function ($q): void {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            }

            $enrollments = $enrollmentsQuery->get();

            $totalAssignments = Assignment::query()
                ->where('course_id', $currentBatch->course_id)
                ->where('is_published', true)
                ->count();

            foreach ($enrollments as $enr) {
                $student = $enr->student;
                if (! $student) {
                    continue;
                }

                /** @var ?CourseProgress $progress */
                $progress = CourseProgress::query()
                    ->where('user_id', $student->id)
                    ->where('course_id', $currentBatch->course_id)
                    ->first();

                $attendanceRate = $progress ? $progress->attendance_percentage : 100.0;
                $submittedCount = AssignmentSubmission::query()
                    ->where('user_id', $student->id)
                    ->whereHas('assignment', fn ($q) => $q->where('course_id', $currentBatch->course_id))
                    ->count();

                $isAtRisk = ($attendanceRate < 75.0) || ($totalAssignments > 0 && $submittedCount === 0);

                $rosterData[] = [
                    'student' => $student,
                    'enrollment' => $enr,
                    'attendanceRate' => $attendanceRate,
                    'submissions' => $submittedCount.' / '.$totalAssignments,
                    'isAtRisk' => $isAtRisk,
                ];
            }
        }

        return view('livewire.teacher.batch-roster', [
            'assignedBatches' => $assignedBatches,
            'currentBatch' => $currentBatch,
            'rosterData' => $rosterData,
        ]);
    }
}
