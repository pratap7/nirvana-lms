<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Communication\Models\BatchMessage;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Scheduling\Models\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class BatchMessenger extends Component
{
    public ?string $selectedBatchUlid = null;

    public ?string $selectedStudentUlid = null;

    public string $messageText = '';

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(?string $student, ?string $batch, TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($batch) {
            /** @var Batch $targetBatch */
            $targetBatch = Batch::query()->where('ulid', $batch)->firstOrFail();
            if (! $scopeService->canAccessBatch($user, $targetBatch)) {
                abort(403, 'Unauthorized batch.');
            }
            $this->selectedBatchUlid = $targetBatch->ulid;
        } else {
            $firstAssignedId = $scopeService->getAssignedBatchIds($user)->first();
            if ($firstAssignedId) {
                $defaultBatch = Batch::query()->find($firstAssignedId);
                $this->selectedBatchUlid = $defaultBatch?->ulid;
            }
        }

        if ($student) {
            $this->selectedStudentUlid = $student;
        }
    }

    public function selectStudent(string $studentUlid): void
    {
        $this->selectedStudentUlid = $studentUlid;
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function sendMessage(TeacherScopeService $scopeService): void
    {
        $this->validate([
            'messageText' => 'required|string|max:2000',
        ]);

        /** @var User $teacher */
        $teacher = Auth::user();

        /** @var Batch $batch */
        $batch = Batch::query()->where('ulid', $this->selectedBatchUlid)->firstOrFail();

        // 1. Check teacher belongs to this batch
        if (! $scopeService->canAccessBatch($teacher, $batch)) {
            abort(403, 'Unauthorized batch.');
        }

        /** @var ?User $recipient */
        $recipient = $this->selectedStudentUlid
            ? User::query()->where('ulid', $this->selectedStudentUlid)->first()
            : null;

        if ($recipient) {
            // 2. CRITICAL CHECK: Verify recipient is enrolled in this shared batch
            $isEnrolled = Enrollment::query()
                ->where('batch_id', $batch->id)
                ->where('student_id', $recipient->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->exists();

            if (! $isEnrolled) {
                $this->errorMessage = 'Security policy violation: You can only message students in shared batches.';

                return;
            }
        }

        BatchMessage::create([
            'batch_id' => $batch->id,
            'sender_id' => $teacher->id,
            'recipient_id' => $recipient?->id,
            'message' => $this->messageText,
        ]);

        $this->messageText = '';
        $this->feedbackMessage = 'Message sent to student.';
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($teacher);

        /** @var Collection<int, Batch> $batches */
        $batches = Batch::query()->whereIn('id', $assignedBatchIds)->get();

        $currentBatch = $this->selectedBatchUlid
            ? Batch::query()->where('ulid', $this->selectedBatchUlid)->first()
            : $batches->first();

        $students = [];
        $messages = collect();

        if ($currentBatch) {
            $students = User::query()
                ->whereHas('enrollments', fn ($q) => $q->where('batch_id', $currentBatch->id)->where('status', EnrollmentStatus::Active->value))
                ->get();

            $messages = BatchMessage::query()
                ->where('batch_id', $currentBatch->id)
                ->when($this->selectedStudentUlid, function ($q): void {
                    /** @var ?User $selected */
                    $selected = User::query()->where('ulid', $this->selectedStudentUlid)->first();
                    if ($selected) {
                        $q->where(fn ($sq) => $sq->where('recipient_id', $selected->id)->orWhere('sender_id', $selected->id));
                    }
                })
                ->with(['sender', 'recipient'])
                ->latest()
                ->take(40)
                ->get()
                ->reverse();
        }

        return view('livewire.teacher.batch-messenger', [
            'batches' => $batches,
            'currentBatch' => $currentBatch,
            'students' => $students,
            'messages' => $messages,
        ]);
    }
}
