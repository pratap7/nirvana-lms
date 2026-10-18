<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Wellbeing\Models\WellbeingConcern;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class WellbeingEscalation extends Component
{
    public ?string $student_id = null;

    public ?string $batch_id = null;

    public string $category = 'academic_stress';

    public string $urgency = 'medium';

    public string $description = '';

    public ?string $feedbackMessage = null;

    public ?string $receiptTrackingCode = null;

    // Moderator review controls (only active if user has concern.review)
    public ?string $reviewingConcernUlid = null;

    public string $reviewStatus = 'under_review';

    public string $resolutionNotes = '';

    public function mount(?string $student = null, ?string $batch = null): void
    {
        if ($student) {
            $user = User::query()->where('ulid', $student)->first();
            $this->student_id = $user ? (string) $user->id : null;
        }

        if ($batch) {
            $this->batch_id = $batch;
        }
    }

    public function reportConcern(): void
    {
        $this->validate([
            'category' => 'required|string|in:academic_stress,mental_health,physical_safety,substance_use,harassment,other',
            'urgency' => 'required|string|in:low,medium,high,critical',
            'description' => 'required|string|min:10|max:5000',
            'student_id' => 'nullable|exists:users,id',
        ]);

        /** @var User $reporter */
        $reporter = Auth::user();

        $concern = WellbeingConcern::create([
            'reporter_id' => $reporter->id,
            'student_id' => $this->student_id ? (int) $this->student_id : null,
            'category' => $this->category,
            'urgency' => $this->urgency,
            'description' => $this->description,
            'status' => 'received',
        ]);

        activity('wellbeing')
            ->causedBy($reporter)
            ->performedOn($concern)
            ->withProperties([
                'category' => $this->category,
                'urgency' => $this->urgency,
                'student_id' => $this->student_id,
            ])
            ->log('Teacher lodged confidential wellbeing concern');

        $this->receiptTrackingCode = 'CONCERN-'.substr($concern->ulid, -8);
        $this->feedbackMessage = "Your confidential concern report has been lodged securely (Tracking ID: {$this->receiptTrackingCode}). It is encrypted and routed exclusively to designated wellbeing officers.";
        $this->description = '';
        $this->student_id = null;
    }

    public function selectConcernForReview(string $ulid): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->hasRole(['Admin', 'Super Admin', 'Moderator']) && ! $currentUser->hasPermissionTo('concern.review')) {
            abort(403, 'Unauthorized to review full concern records.');
        }

        /** @var WellbeingConcern $c */
        $c = WellbeingConcern::query()->where('ulid', $ulid)->firstOrFail();
        $this->reviewingConcernUlid = $c->ulid;
        $this->reviewStatus = $c->status;
        $this->resolutionNotes = (string) ($c->resolution_notes ?? '');
    }

    public function updateConcernReview(): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->hasRole(['Admin', 'Super Admin', 'Moderator']) && ! $currentUser->hasPermissionTo('concern.review')) {
            abort(403, 'Unauthorized.');
        }

        if (! $this->reviewingConcernUlid) {
            return;
        }

        /** @var WellbeingConcern $c */
        $c = WellbeingConcern::query()->where('ulid', $this->reviewingConcernUlid)->firstOrFail();

        $c->update([
            'status' => $this->reviewStatus,
            'assigned_to' => $currentUser->id,
            'resolution_notes' => $this->resolutionNotes ?: null,
            'resolved_at' => in_array($this->reviewStatus, ['resolved', 'closed'], true) ? CarbonImmutable::now() : null,
        ]);

        $this->feedbackMessage = 'Concern status and resolution notes updated.';
        $this->reviewingConcernUlid = null;
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $hasReviewPermission = $currentUser->hasRole(['Admin', 'Super Admin', 'Moderator'])
            || $currentUser->hasPermissionTo('concern.review');

        // CRITICAL ACCEPTANCE RULE:
        // Concern reports are invisible to teachers other than the reporter's own submission receipt.
        if ($hasReviewPermission) {
            /** @var Collection<int, WellbeingConcern> $concerns */
            $concerns = WellbeingConcern::query()
                ->with(['reporter', 'student', 'assignee'])
                ->latest()
                ->take(50)
                ->get();
        } else {
            /** @var Collection<int, WellbeingConcern> $concerns */
            $concerns = WellbeingConcern::query()
                ->where('reporter_id', $currentUser->id)
                ->latest()
                ->take(50)
                ->get();
        }

        // Students in assigned batches for dropdown
        $assignedBatchIds = $scopeService->getAssignedBatchIds($currentUser);
        $enrolledStudents = User::query()
            ->whereHas('enrollments', fn ($q) => $q->whereIn('batch_id', $assignedBatchIds)->where('status', EnrollmentStatus::Active->value))
            ->orderBy('first_name')
            ->get();

        $activeReview = ($this->reviewingConcernUlid && $hasReviewPermission)
            ? WellbeingConcern::query()->with(['reporter', 'student', 'assignee'])->where('ulid', $this->reviewingConcernUlid)->first()
            : null;

        return view('livewire.teacher.wellbeing-escalation', [
            'concerns' => $concerns,
            'enrolledStudents' => $enrolledStudents,
            'hasReviewPermission' => $hasReviewPermission,
            'activeReview' => $activeReview,
        ]);
    }
}
