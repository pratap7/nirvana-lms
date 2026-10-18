<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class AssignmentGrading extends Component
{
    public ?string $selectedSubmissionUlid = null;

    public int $score = 0;

    public string $feedback = '';

    public ?string $feedbackMessage = null;

    public string $filter = 'all'; // all, pending, graded

    public function selectSubmission(string $ulid): void
    {
        /** @var AssignmentSubmission $sub */
        $sub = AssignmentSubmission::query()
            ->with(['assignment', 'user'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->selectedSubmissionUlid = $sub->ulid;
        $this->score = (int) ($sub->score ?? 0);
        $this->feedback = (string) ($sub->feedback ?? '');
        $this->feedbackMessage = null;
    }

    public function gradeSubmission(): void
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        if (! $this->selectedSubmissionUlid) {
            return;
        }

        /** @var AssignmentSubmission $sub */
        $sub = AssignmentSubmission::query()
            ->with('assignment')
            ->where('ulid', $this->selectedSubmissionUlid)
            ->firstOrFail();

        $this->validate([
            'score' => "required|integer|min:0|max:{$sub->assignment->max_score}",
            'feedback' => 'nullable|string|max:2000',
        ]);

        $sub->update([
            'score' => $this->score,
            'feedback' => $this->feedback ?: null,
            'status' => 'graded',
            'graded_by' => $teacher->id,
            'graded_at' => CarbonImmutable::now(),
        ]);

        $this->feedbackMessage = "Submission graded successfully ({$this->score} / {$sub->assignment->max_score} pts).";
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($teacher);

        $query = AssignmentSubmission::query()
            ->whereHas('assignment', fn ($q) => $q->whereIn('batch_id', $assignedBatchIds))
            ->with(['assignment.course', 'user'])
            ->latest('submitted_at');

        if ($this->filter === 'pending') {
            $query->where('status', 'submitted');
        } elseif ($this->filter === 'graded') {
            $query->where('status', 'graded');
        }

        /** @var Collection<int, AssignmentSubmission> $submissions */
        $submissions = $query->take(50)->get();

        $activeSubmission = $this->selectedSubmissionUlid
            ? AssignmentSubmission::query()->with(['assignment', 'user'])->where('ulid', $this->selectedSubmissionUlid)->first()
            : null;

        return view('livewire.teacher.assignment-grading', [
            'submissions' => $submissions,
            'activeSubmission' => $activeSubmission,
        ]);
    }
}
