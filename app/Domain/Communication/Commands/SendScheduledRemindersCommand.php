<?php

declare(strict_types=1);

namespace App\Domain\Communication\Commands;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Communication\Services\NotificationService;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Console\Command;

class SendScheduledRemindersCommand extends Command
{
    protected $signature = 'lms:send-scheduled-reminders';

    protected $description = 'Send scheduled multi-channel reminders (class 24h/1h, billing, assignments, seat holds, inactivity)';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Starting scheduled reminders processing...');

        $stats = [
            'class_24h' => $this->sendClassReminders24h($notificationService),
            'class_1h' => $this->sendClassReminders1h($notificationService),
            'payments' => $this->sendPaymentReminders($notificationService),
            'assignments' => $this->sendAssignmentDeadlineReminders($notificationService),
            'seat_holds' => $this->sendSeatHoldExpiryReminders($notificationService),
            'inactivity' => $this->sendInactivityNudges($notificationService),
        ];

        $this->info(sprintf(
            'Reminders dispatched: Class 24h: %d | Class 1h: %d | Payments: %d | Assignments: %d | Holds: %d | Inactivity: %d',
            $stats['class_24h'],
            $stats['class_1h'],
            $stats['payments'],
            $stats['assignments'],
            $stats['seat_holds'],
            $stats['inactivity']
        ));

        return self::SUCCESS;
    }

    /**
     * 1A. Class reminders 24h ahead.
     */
    protected function sendClassReminders24h(NotificationService $service): int
    {
        $count = 0;
        $startWindow = now()->addHours(23);
        $endWindow = now()->addHours(25);

        $sessions = Session::query()
            ->where('status', SessionStatus::Scheduled)
            ->whereBetween('starts_at', [$startWindow, $endWindow])
            ->with(['batch.enrollments.student', 'batch.course'])
            ->get();

        foreach ($sessions as $session) {
            $batch = $session->batch;
            if (! $batch) {
                continue;
            }

            foreach ($batch->enrollments as $enrollment) {
                if ($enrollment->status !== EnrollmentStatus::Active || ! $enrollment->student) {
                    continue;
                }

                $student = $enrollment->student;
                $idempotencyKey = "remind_24h_sess_{$session->id}_std_{$student->id}";

                $service->send(
                    user: $student,
                    templateKey: 'session_reminder_24h',
                    variables: [
                        'student_name' => $student->full_name,
                        'session_title' => $session->title,
                        'course_name' => $batch->course->title,
                        'starts_at' => $session->starts_at->format('M d, Y h:i A'),
                    ],
                    idempotencyPrefix: $idempotencyKey,
                    sync: true
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * 1B. Class reminders 1h ahead.
     */
    protected function sendClassReminders1h(NotificationService $service): int
    {
        $count = 0;
        $startWindow = now()->addMinutes(50);
        $endWindow = now()->addMinutes(70);

        $sessions = Session::query()
            ->where('status', SessionStatus::Scheduled)
            ->whereBetween('starts_at', [$startWindow, $endWindow])
            ->with(['batch.enrollments.student', 'batch.course'])
            ->get();

        foreach ($sessions as $session) {
            $batch = $session->batch;
            if (! $batch) {
                continue;
            }

            foreach ($batch->enrollments as $enrollment) {
                if ($enrollment->status !== EnrollmentStatus::Active || ! $enrollment->student) {
                    continue;
                }

                $student = $enrollment->student;
                $idempotencyKey = "remind_1h_sess_{$session->id}_std_{$student->id}";

                $service->send(
                    user: $student,
                    templateKey: 'session_reminder_1h',
                    variables: [
                        'student_name' => $student->full_name,
                        'session_title' => $session->title,
                        'course_name' => $batch->course->title,
                        'starts_at' => $session->starts_at->format('h:i A'),
                    ],
                    idempotencyPrefix: $idempotencyKey,
                    sync: true
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * 2. Payment due / overdue reminders.
     */
    protected function sendPaymentReminders(NotificationService $service): int
    {
        $count = 0;

        $unpaidInvoices = Invoice::query()
            ->whereIn('status', [
                InvoicePaymentStatus::Pending,
                InvoicePaymentStatus::PartiallyPaid,
            ])
            ->with('student')
            ->get();

        foreach ($unpaidInvoices as $invoice) {
            $user = $invoice->student;
            if (! $user) {
                continue;
            }

            $isOverdue = $invoice->due_date->isPast();
            $templateKey = $isOverdue ? 'payment_overdue' : 'payment_due';
            $idempotencyKey = "bill_{$templateKey}_inv_{$invoice->id}_date_".now()->toDateString();

            $service->send(
                user: $user,
                templateKey: $templateKey,
                variables: [
                    'student_name' => $user->full_name,
                    'invoice_number' => $invoice->invoice_number,
                    'due_amount' => $invoice->amount_due ?? 0,
                    'due_date' => $invoice->due_date->format('M d, Y'),
                ],
                idempotencyPrefix: $idempotencyKey,
                sync: true
            );
            $count++;
        }

        return $count;
    }

    /**
     * 3. Assignment deadline reminders (24h before due).
     */
    protected function sendAssignmentDeadlineReminders(NotificationService $service): int
    {
        $count = 0;
        $start = now()->addHours(23);
        $end = now()->addHours(25);

        $assignments = Assignment::query()
            ->whereBetween('due_at', [$start, $end])
            ->with(['course.batches.enrollments.student'])
            ->get();

        foreach ($assignments as $assignment) {
            $course = $assignment->course;
            if (! $course) {
                continue;
            }

            foreach ($course->batches as $batch) {
                foreach ($batch->enrollments as $enrollment) {
                    if ($enrollment->status !== EnrollmentStatus::Active || ! $enrollment->student) {
                        continue;
                    }

                    $student = $enrollment->student;

                    // Skip if student already submitted
                    $hasSubmitted = $assignment->submissions()
                        ->where('user_id', $student->id)
                        ->exists();

                    if ($hasSubmitted) {
                        continue;
                    }

                    $idempotencyKey = "asgn_deadline_{$assignment->id}_std_{$student->id}";

                    $service->send(
                        user: $student,
                        templateKey: 'assignment_due',
                        variables: [
                            'student_name' => $student->full_name,
                            'assignment_title' => $assignment->title,
                            'course_name' => $course->title,
                            'due_at' => $assignment->due_at->format('M d, Y h:i A'),
                        ],
                        idempotencyPrefix: $idempotencyKey,
                        sync: true
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * 4. Seat-hold expiry reminders (within 2 hours).
     */
    protected function sendSeatHoldExpiryReminders(NotificationService $service): int
    {
        $count = 0;

        $expiringHolds = Enrollment::query()
            ->where('status', EnrollmentStatus::Approved)
            ->whereNotNull('seat_hold_expires_at')
            ->whereBetween('seat_hold_expires_at', [now(), now()->addHours(2)])
            ->with(['student', 'batch.course'])
            ->get();

        foreach ($expiringHolds as $enrollment) {
            $student = $enrollment->student;
            if (! $student) {
                continue;
            }

            $idempotencyKey = "hold_exp_{$enrollment->id}_date_".now()->toDateString();

            $service->send(
                user: $student,
                templateKey: 'seat_hold_expiring',
                variables: [
                    'student_name' => $student->full_name,
                    'batch_name' => $enrollment->batch->name,
                    'expires_at' => $enrollment->seat_hold_expires_at?->format('h:i A') ?? 'Soon',
                ],
                idempotencyPrefix: $idempotencyKey,
                sync: true
            );
            $count++;
        }

        return $count;
    }

    /**
     * 5. Inactivity nudges (7+ days inactive).
     */
    protected function sendInactivityNudges(NotificationService $service): int
    {
        $count = 0;
        $weekKey = now()->format('Y_W');

        $inactiveStudents = User::query()
            ->whereHas('enrollments', fn ($q) => $q->where('status', EnrollmentStatus::Active))
            ->where(function ($q): void {
                $q->whereNull('last_login_at')
                    ->orWhere('last_login_at', '<', now()->subDays(7));
            })
            ->get();

        foreach ($inactiveStudents as $student) {
            $idempotencyKey = "inactivity_std_{$student->id}_wk_{$weekKey}";

            $service->send(
                user: $student,
                templateKey: 'inactivity_nudge',
                variables: [
                    'student_name' => $student->full_name,
                    'institute_name' => 'Pramana Institute',
                ],
                idempotencyPrefix: $idempotencyKey,
                sync: true
            );
            $count++;
        }

        return $count;
    }
}
