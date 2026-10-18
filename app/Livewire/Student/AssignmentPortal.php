<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssignmentPortal extends Component
{
    use WithFileUploads;

    public ?Assignment $selectedAssignment = null;

    public string $textContent = '';

    public string $linkUrl = '';

    /** @var mixed */
    public $fileUpload = null;

    public string $filter = 'all'; // all, pending, submitted

    public function selectAssignment(string $assignmentUlid): void
    {
        $this->selectedAssignment = Assignment::query()
            ->with(['course', 'submissions' => fn ($q) => $q->where('user_id', auth()->id())])
            ->where('ulid', $assignmentUlid)
            ->firstOrFail();

        $this->textContent = '';
        $this->linkUrl = '';
        $this->fileUpload = null;
    }

    public function submitAssignment(): void
    {
        if ($this->selectedAssignment === null) {
            return;
        }

        /** @var User $student */
        $student = auth()->user();

        $filePath = null;
        if ($this->fileUpload) {
            $filePath = $this->fileUpload->store('assignments', 'public');
        }

        $latestSubmission = $this->selectedAssignment->latestSubmissionFor($student);
        $nextNumber = ($latestSubmission ? $latestSubmission->submission_number + 1 : 1);

        if ($latestSubmission && $nextNumber > $this->selectedAssignment->max_resubmissions) {
            session()->flash('error', 'Maximum resubmissions reached for this assignment.');

            return;
        }

        $isLate = now()->isAfter($this->selectedAssignment->due_at);
        if ($isLate && ! $this->selectedAssignment->allow_late) {
            session()->flash('error', 'This assignment does not accept late submissions.');

            return;
        }

        AssignmentSubmission::create([
            'assignment_id' => $this->selectedAssignment->id,
            'user_id' => $student->id,
            'submission_number' => $nextNumber,
            'text_content' => $this->textContent ?: null,
            'file_path' => $filePath,
            'link_url' => $this->linkUrl ?: null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'is_late' => $isLate,
        ]);

        session()->flash('success', 'Assignment submitted successfully.');
        $this->selectAssignment($this->selectedAssignment->ulid);
    }

    public function render(): View
    {
        /** @var User $student */
        $student = auth()->user();

        $courseIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->pluck('course_id');

        $assignments = Assignment::query()
            ->with(['course', 'submissions' => fn ($q) => $q->where('user_id', $student->id)])
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->orderBy('due_at')
            ->get();

        return view('livewire.student.assignment-portal', [
            'assignments' => $assignments,
        ])->layout('layouts.student', ['title' => 'Assignments']);
    }
}
