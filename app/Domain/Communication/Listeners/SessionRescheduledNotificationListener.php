<?php

declare(strict_types=1);

namespace App\Domain\Communication\Listeners;

use App\Domain\Communication\Services\NotificationService;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\SessionRescheduled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Event-Driven Notification Listener for SessionRescheduled (Section H).
 * Maps domain event to 'session_rescheduled' multi-channel notification template.
 * Sends exactly one notification per student per enabled channel with idempotency.
 */
class SessionRescheduledNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(SessionRescheduled $event): void
    {
        $session = $event->session;
        $batch = $session->batch;

        if (! $batch) {
            return;
        }

        // Fetch all actively enrolled students in the batch
        $enrolledStudents = User::query()
            ->whereIn('id', function ($query) use ($batch): void {
                $query->select('student_id')
                    ->from('enrollments')
                    ->where('batch_id', $batch->id)
                    ->where('status', EnrollmentStatus::Active->value);
            })
            ->get();

        $oldTimeStr = $event->oldStartsAt->format('M d, Y h:i A');
        $newTimeStr = $session->starts_at->format('M d, Y h:i A');

        foreach ($enrolledStudents as $student) {
            // Unique idempotency key combining session ID, update timestamp, and student ID
            $idempotencyPrefix = sprintf(
                'reschedule_session_%d_std_%d_to_%s',
                $session->id,
                $student->id,
                $session->starts_at->timestamp
            );

            $this->notificationService->send(
                user: $student,
                templateKey: 'session_rescheduled',
                variables: [
                    'student_name' => $student->full_name,
                    'session_title' => $session->title,
                    'course_name' => $batch->course->title,
                    'old_starts_at' => $oldTimeStr,
                    'new_starts_at' => $newTimeStr,
                    'reason' => $event->reason ?? 'Academic schedule optimization',
                ],
                idempotencyPrefix: $idempotencyPrefix,
                sync: true
            );
        }
    }
}
