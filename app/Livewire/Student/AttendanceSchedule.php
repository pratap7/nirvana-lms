<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AttendanceSchedule extends Component
{
    public function render(): View
    {
        /** @var User $student */
        $student = auth()->user();

        $enrollments = Enrollment::query()
            ->with(['batch.course'])
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->get();

        $activeBatchIds = $enrollments->pluck('batch_id')->filter()->unique();

        // Upcoming and recent sessions
        $sessions = Session::query()
            ->with(['batch.course', 'venue', 'room'])
            ->whereIn('batch_id', $activeBatchIds)
            ->orderBy('starts_at', 'desc')
            ->get();

        // Student's attendance records
        $attendances = SessionAttendance::query()
            ->where('user_id', $student->id)
            ->get()
            ->keyBy('session_id');

        $totalHeld = $sessions->where('starts_at', '<=', now())->count();
        $attendedCount = 0;
        foreach ($sessions->where('starts_at', '<=', now()) as $s) {
            $att = $attendances->get($s->id);
            if ($att && in_array($att->status, ['present', 'late'], true)) {
                $attendedCount++;
            }
        }

        $attendancePercentage = $totalHeld > 0 ? round(($attendedCount / $totalHeld) * 100, 2) : 100.00;

        return view('livewire.student.attendance-schedule', [
            'sessions' => $sessions,
            'attendances' => $attendances,
            'totalHeld' => $totalHeld,
            'attendedCount' => $attendedCount,
            'attendancePercentage' => $attendancePercentage,
        ])->layout('layouts.student', ['title' => 'Schedule & Attendance']);
    }
}
