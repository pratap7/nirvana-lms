<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Support\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class AttendanceMarker extends Component
{
    public ?string $selectedSessionUlid = null;

    public string $correctionReason = '';

    public ?int $correctingUserId = null;

    public ?string $pendingCorrectionStatus = null;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(?string $session, TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($session) {
            /** @var Session $sess */
            $sess = Session::query()->with('batch')->where('ulid', $session)->firstOrFail();

            if (! $scopeService->canAccessBatch($user, $sess->batch)) {
                abort(403, 'Unauthorized to take attendance for this session.');
            }

            $this->selectedSessionUlid = $sess->ulid;
        } else {
            $assignedBatchIds = $scopeService->getAssignedBatchIds($user);
            $latestSession = Session::query()
                ->whereIn('batch_id', $assignedBatchIds)
                ->latest('starts_at')
                ->first();
            $this->selectedSessionUlid = $latestSession?->ulid;
        }
    }

    public function selectSession(string $sessionUlid, TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Session $sess */
        $sess = Session::query()->with('batch')->where('ulid', $sessionUlid)->firstOrFail();

        if (! $scopeService->canAccessBatch($user, $sess->batch)) {
            abort(403, 'Unauthorized to view this session.');
        }

        $this->selectedSessionUlid = $sess->ulid;
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function markStatus(int $userId, string $status, SettingsService $settingsService): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        /** @var Session $session */
        $session = Session::query()->where('ulid', $this->selectedSessionUlid)->firstOrFail();

        $windowDays = (int) $settingsService->get('attendance_edit_window_days', 3);
        $sessionTime = $session->ends_at ?? $session->starts_at;
        $isLocked = now()->isAfter($sessionTime->copy()->addDays($windowDays));

        // If locked, check if user is moderator/admin and if correction reason was supplied
        if ($isLocked) {
            $isModerator = $currentUser->hasRole(['Admin', 'Super Admin', 'Moderator']);
            if (! $isModerator) {
                $this->errorMessage = "Attendance is locked. The {$windowDays}-day edit window has expired. A moderator override with reason is required.";

                return;
            }

            // Prompt or verify reason
            if (empty(trim($this->correctionReason))) {
                $this->correctingUserId = $userId;
                $this->pendingCorrectionStatus = $status;
                $this->errorMessage = 'Attendance is locked. Please specify a mandatory correction reason to submit this override.';

                return;
            }
        }

        $this->persistAttendanceRecord($session, $userId, $status, $isLocked);
    }

    public function submitLockedCorrection(): void
    {
        if (! $this->correctingUserId || ! $this->pendingCorrectionStatus) {
            return;
        }

        if (empty(trim($this->correctionReason))) {
            $this->errorMessage = 'A valid correction reason is required to edit locked attendance.';

            return;
        }

        /** @var Session $session */
        $session = Session::query()->where('ulid', $this->selectedSessionUlid)->firstOrFail();

        $this->persistAttendanceRecord($session, $this->correctingUserId, $this->pendingCorrectionStatus, true);

        $this->correctingUserId = null;
        $this->pendingCorrectionStatus = null;
        $this->correctionReason = '';
    }

    protected function persistAttendanceRecord(Session $session, int $userId, string $status, bool $isLocked): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $attendance = SessionAttendance::query()->updateOrCreate(
            [
                'session_id' => $session->id,
                'user_id' => $userId,
            ],
            [
                'status' => $status,
                'marked_by' => $currentUser->id,
                'edited_by' => $isLocked ? $currentUser->id : null,
                'correction_reason' => $isLocked ? trim($this->correctionReason) : null,
            ]
        );

        if ($isLocked) {
            activity('attendance')
                ->causedBy($currentUser)
                ->performedOn($attendance)
                ->withProperties([
                    'session_id' => $session->id,
                    'student_id' => $userId,
                    'new_status' => $status,
                    'reason' => trim($this->correctionReason),
                ])
                ->log("Locked attendance overridden with reason: {$this->correctionReason}");
        }

        $this->feedbackMessage = 'Attendance updated to '.ucfirst($status).'.';
        $this->errorMessage = null;
    }

    public function markAllPresent(SettingsService $settingsService): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        /** @var Session $session */
        $session = Session::query()->where('ulid', $this->selectedSessionUlid)->firstOrFail();

        $windowDays = (int) $settingsService->get('attendance_edit_window_days', 3);
        $sessionTime = $session->ends_at ?? $session->starts_at;
        $isLocked = now()->isAfter($sessionTime->copy()->addDays($windowDays));

        if ($isLocked) {
            $this->errorMessage = "Cannot bulk update: Session attendance is locked ({$windowDays}-day window expired).";

            return;
        }

        $enrolledStudents = Enrollment::query()
            ->where('batch_id', $session->batch_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->pluck('student_id');

        foreach ($enrolledStudents as $studentId) {
            SessionAttendance::query()->updateOrCreate(
                [
                    'session_id' => $session->id,
                    'user_id' => $studentId,
                ],
                [
                    'status' => 'present',
                    'marked_by' => $currentUser->id,
                ]
            );
        }

        $this->feedbackMessage = 'All active students in this cohort marked as Present.';
    }

    public function render(TeacherScopeService $scopeService, SettingsService $settingsService): View
    {
        /** @var User $user */
        $user = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($user);

        /** @var Collection<int, Session> $sessions */
        $sessions = Session::query()
            ->whereIn('batch_id', $assignedBatchIds)
            ->with(['batch.course'])
            ->latest('starts_at')
            ->take(20)
            ->get();

        $currentSession = $this->selectedSessionUlid
            ? Session::query()->where('ulid', $this->selectedSessionUlid)->with('batch')->first()
            : $sessions->first();

        $roster = [];
        $isLocked = false;
        $windowDays = (int) $settingsService->get('attendance_edit_window_days', 3);

        if ($currentSession) {
            $sessionTime = $currentSession->ends_at ?? $currentSession->starts_at;
            $isLocked = now()->isAfter($sessionTime->copy()->addDays($windowDays));

            $enrollments = Enrollment::query()
                ->where('batch_id', $currentSession->batch_id)
                ->where('status', EnrollmentStatus::Active->value)
                ->with('student')
                ->get();

            $attendances = SessionAttendance::query()
                ->where('session_id', $currentSession->id)
                ->get()
                ->keyBy('user_id');

            foreach ($enrollments as $enr) {
                $student = $enr->student;
                if (! $student) {
                    continue;
                }

                $record = $attendances->get($student->id);

                $roster[] = [
                    'student' => $student,
                    'status' => $record ? $record->status : 'unmarked',
                    'record' => $record,
                ];
            }
        }

        return view('livewire.teacher.attendance-marker', [
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'roster' => $roster,
            'isLocked' => $isLocked,
            'windowDays' => $windowDays,
        ]);
    }
}
