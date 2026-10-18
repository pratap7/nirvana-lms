<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Course;
use App\Domain\Certification\Enums\CertificateStatus;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise Reporting & Business Intelligence Service (Section H).
 * Computes high-performance metrics for operational, financial, and academic dashboards.
 */
class ReportingService
{
    /**
     * 1. Enrollment Funnel Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getEnrollmentFunnel(CarbonInterface $from, CarbonInterface $to, ?int $courseId = null): array
    {
        $query = Enrollment::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $countsByStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $requested = (int) ($countsByStatus[EnrollmentStatus::Requested->value] ?? 0);
        $underReview = (int) ($countsByStatus[EnrollmentStatus::UnderReview->value] ?? 0);
        $approved = (int) ($countsByStatus[EnrollmentStatus::Approved->value] ?? 0);
        $paymentPending = (int) ($countsByStatus[EnrollmentStatus::PaymentPending->value] ?? 0);
        $active = (int) ($countsByStatus[EnrollmentStatus::Active->value] ?? 0);
        $completed = (int) ($countsByStatus[EnrollmentStatus::Completed->value] ?? 0);
        $waitlisted = (int) ($countsByStatus[EnrollmentStatus::Waitlisted->value] ?? 0);
        $rejected = (int) ($countsByStatus[EnrollmentStatus::Rejected->value] ?? 0);
        $dropped = (int) ($countsByStatus[EnrollmentStatus::Dropped->value] ?? 0);
        $cancelled = (int) ($countsByStatus[EnrollmentStatus::Cancelled->value] ?? 0);

        $totalApplications = $requested + $underReview + $approved + $paymentPending + $active + $completed + $waitlisted + $rejected + $dropped + $cancelled;
        $totalPaidOrActive = $active + $completed;

        $conversionRate = $totalApplications > 0
            ? round(($totalPaidOrActive / $totalApplications) * 100, 2)
            : 0.0;

        $reviewApprovalRate = ($requested + $underReview + $approved) > 0
            ? round(($approved / max(1, $requested + $underReview + $approved)) * 100, 2)
            : 0.0;

        return [
            'total_applications' => $totalApplications,
            'total_enrolled' => $totalPaidOrActive,
            'conversion_rate_pct' => $conversionRate,
            'review_approval_rate_pct' => $reviewApprovalRate,
            'stages' => [
                'requested' => $requested,
                'under_review' => $underReview,
                'approved' => $approved,
                'payment_pending' => $paymentPending,
                'active' => $active,
                'completed' => $completed,
                'waitlisted' => $waitlisted,
                'rejected' => $rejected,
                'dropped' => $dropped,
                'cancelled' => $cancelled,
            ],
        ];
    }

    /**
     * 2. Revenue Analytics Dashboard (Daily, Monthly, By Course, By Gateway).
     *
     * @return array<string, mixed>
     */
    public function getRevenueReport(CarbonInterface $from, CarbonInterface $to, ?int $courseId = null, ?string $gateway = null): array
    {
        $query = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        if ($courseId) {
            $query->whereHas('invoice', fn ($q) => $q->where('course_id', $courseId));
        }

        $payments = (clone $query)->with(['invoice.course'])->get();

        // Total gross in INR (amounts in DB are in minor units / paise)
        $totalGrossPaise = $payments->sum('amount');
        $totalGross = round($totalGrossPaise / 100, 2);
        $transactionsCount = $payments->count();

        // Breakdown by gateway
        $byGateway = $payments->groupBy('gateway')->map(function (Collection $group, string $gwKey) {
            $amountPaise = $group->sum('amount');

            return [
                'gateway' => $gwKey ?: 'manual',
                'transactions_count' => $group->count(),
                'total_amount' => round($amountPaise / 100, 2),
            ];
        })->values()->toArray();

        // Breakdown by course
        $byCourse = $payments->groupBy(function (Payment $p): string {
            return ($p->invoice && $p->invoice->course instanceof Course)
                ? $p->invoice->course->title
                : 'Direct / Other Fees';
        })->map(function (Collection $group, string $courseTitle) {
            $amountPaise = $group->sum('amount');

            return [
                'course_title' => $courseTitle,
                'transactions_count' => $group->count(),
                'total_amount' => round($amountPaise / 100, 2),
            ];
        })->values()->toArray();

        // Daily trend
        $dailyTrend = $payments->groupBy(fn (Payment $p) => $p->created_at->format('Y-m-d'))
            ->map(function (Collection $group, string $date) {
                return [
                    'date' => $date,
                    'transactions_count' => $group->count(),
                    'total_amount' => round($group->sum('amount') / 100, 2),
                ];
            })->sortKeys()->values()->toArray();

        return [
            'total_gross' => $totalGross,
            'transactions_count' => $transactionsCount,
            'by_gateway' => $byGateway,
            'by_course' => $byCourse,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * 3. Outstanding Dues & Aging Summary.
     *
     * @return array<string, mixed>
     */
    public function getOutstandingDues(CarbonInterface $from, CarbonInterface $to, ?string $agingBucket = null): array
    {
        $invoices = Invoice::query()
            ->whereIn('status', [
                InvoicePaymentStatus::Pending,
                InvoicePaymentStatus::PartiallyPaid,
            ])
            ->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['student', 'course'])
            ->get();

        $today = now()->startOfDay();

        $currentBucket = collect();
        $days31To60 = collect();
        $days61To90 = collect();
        $days90Plus = collect();

        $totalOutstandingPaise = 0;
        $totalOverduePaise = 0;

        foreach ($invoices as $invoice) {
            $amountDuePaise = $invoice->total_amount - (int) $invoice->payments()->where('status', PaymentStatus::Verified)->sum('amount');
            if ($amountDuePaise <= 0) {
                continue;
            }

            $totalOutstandingPaise += $amountDuePaise;
            $dueDate = $invoice->due_date;
            $isOverdue = $dueDate->isPast();

            if ($isOverdue) {
                $totalOverduePaise += $amountDuePaise;
                $daysOverdue = (int) $dueDate->diffInDays($today);

                if ($daysOverdue <= 30) {
                    $currentBucket->push($invoice);
                } elseif ($daysOverdue <= 60) {
                    $days31To60->push($invoice);
                } elseif ($daysOverdue <= 90) {
                    $days61To90->push($invoice);
                } else {
                    $days90Plus->push($invoice);
                }
            } else {
                $currentBucket->push($invoice);
            }
        }

        $bucketSummary = [
            'current' => [
                'count' => $currentBucket->count(),
                'label' => 'Current / 0-30 Days',
            ],
            '31_60' => [
                'count' => $days31To60->count(),
                'label' => '31 - 60 Days Overdue',
            ],
            '61_90' => [
                'count' => $days61To90->count(),
                'label' => '61 - 90 Days Overdue',
            ],
            '90_plus' => [
                'count' => $days90Plus->count(),
                'label' => '90+ Days Overdue (Critical)',
            ],
        ];

        return [
            'total_outstanding_amount' => round($totalOutstandingPaise / 100, 2),
            'total_overdue_amount' => round($totalOverduePaise / 100, 2),
            'invoices_count' => $invoices->count(),
            'aging_breakdown' => $bucketSummary,
            'items' => $invoices->take(50)->map(function (Invoice $inv) {
                return [
                    'invoice_number' => $inv->invoice_number,
                    'student_name' => $inv->student instanceof User ? $inv->student->full_name : $inv->billing_name,
                    'student_email' => $inv->student instanceof User ? $inv->student->email : $inv->billing_email,
                    'course_name' => $inv->course instanceof Course ? $inv->course->title : 'General Program',
                    'due_date' => $inv->due_date->format('M d, Y'),
                    'total_amount' => round($inv->total_amount / 100, 2),
                    'is_overdue' => $inv->due_date->isPast(),
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * 4. Attendance Breakdown by Batch.
     *
     * @return array<string, mixed>
     */
    public function getAttendanceByBatch(CarbonInterface $from, CarbonInterface $to, ?int $courseId = null, ?int $batchId = null): array
    {
        $batchQuery = Batch::query()->with(['course']);

        if ($courseId) {
            $batchQuery->where('course_id', $courseId);
        }

        if ($batchId) {
            $batchQuery->where('id', $batchId);
        }

        $batches = $batchQuery->get();

        $batchSummaries = [];
        $grandTotalPresent = 0;
        $grandTotalMarked = 0;

        foreach ($batches as $batch) {
            $attendances = SessionAttendance::query()
                ->whereHas('session', fn ($q) => $q->where('batch_id', $batch->id)->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()]))
                ->get();

            $present = $attendances->where('status', 'present')->count();
            $late = $attendances->where('status', 'late')->count();
            $absent = $attendances->where('status', 'absent')->count();
            $excused = $attendances->where('status', 'excused')->count();
            $totalMarked = $attendances->count();

            $rate = $totalMarked > 0
                ? round((($present + $late) / $totalMarked) * 100, 2)
                : 0.0;

            $grandTotalPresent += ($present + $late);
            $grandTotalMarked += $totalMarked;

            $batchSummaries[] = [
                'batch_id' => $batch->id,
                'batch_name' => $batch->name,
                'course_title' => $batch->course->title,
                'sessions_held' => $batch->sessions()->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()])->count(),
                'present_count' => $present,
                'late_count' => $late,
                'absent_count' => $absent,
                'excused_count' => $excused,
                'total_marked' => $totalMarked,
                'attendance_rate_pct' => $rate,
            ];
        }

        $overallRate = $grandTotalMarked > 0
            ? round(($grandTotalPresent / $grandTotalMarked) * 100, 2)
            : 0.0;

        return [
            'overall_attendance_rate_pct' => $overallRate,
            'total_marked_records' => $grandTotalMarked,
            'batches' => $batchSummaries,
        ];
    }

    /**
     * 5. Course Completion Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getCourseCompletion(CarbonInterface $from, CarbonInterface $to, ?int $courseId = null): array
    {
        $coursesQuery = Course::query();

        if ($courseId) {
            $coursesQuery->where('id', $courseId);
        }

        $courses = $coursesQuery->get();
        $courseStats = [];
        $totalEnrolledAll = 0;
        $totalCompletedAll = 0;

        foreach ($courses as $course) {
            $enrollments = Enrollment::query()
                ->where('course_id', $course->id)
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->get();

            $enrolled = $enrollments->count();
            $active = $enrollments->where('status', EnrollmentStatus::Active)->count();
            $completed = $enrollments->where('status', EnrollmentStatus::Completed)->count();
            $dropped = $enrollments->where('status', EnrollmentStatus::Dropped)->count();

            $rate = $enrolled > 0 ? round(($completed / $enrolled) * 100, 2) : 0.0;

            $certificatesIssued = Certificate::query()
                ->where('course_id', $course->id)
                ->where('status', CertificateStatus::Valid)
                ->count();

            $totalEnrolledAll += $enrolled;
            $totalCompletedAll += $completed;

            $courseStats[] = [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'total_enrolled' => $enrolled,
                'active_count' => $active,
                'completed_count' => $completed,
                'dropped_count' => $dropped,
                'completion_rate_pct' => $rate,
                'certificates_issued' => $certificatesIssued,
            ];
        }

        $globalRate = $totalEnrolledAll > 0
            ? round(($totalCompletedAll / $totalEnrolledAll) * 100, 2)
            : 0.0;

        return [
            'overall_completion_rate_pct' => $globalRate,
            'total_students_completed' => $totalCompletedAll,
            'courses' => $courseStats,
        ];
    }

    /**
     * 6. Teacher Workload Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getTeacherWorkload(CarbonInterface $from, CarbonInterface $to, ?int $teacherId = null): array
    {
        $teachersQuery = User::role('Teacher');

        if ($teacherId) {
            $teachersQuery->where('id', $teacherId);
        }

        $teachers = $teachersQuery->get();
        $workload = [];

        foreach ($teachers as $teacher) {
            // Assigned courses/batches
            $courseIds = DB::table('course_teachers')
                ->where('user_id', $teacher->id)
                ->pluck('course_id');
            $batchesCount = $courseIds->count();

            // Scheduled sessions within window
            $sessionIds = DB::table('session_teachers')
                ->where('user_id', $teacher->id)
                ->pluck('session_id');

            $sessions = Session::query()
                ->whereIn('id', $sessionIds)
                ->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()])
                ->get();

            $totalHours = 0.0;
            foreach ($sessions as $session) {
                $totalHours += round($session->starts_at->diffInMinutes($session->ends_at) / 60, 2);
            }

            // Pending submissions to grade for courses taught by this teacher
            $pendingGrading = AssignmentSubmission::query()
                ->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $courseIds))
                ->where('status', 'submitted')
                ->count();

            $workload[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'teacher_email' => $teacher->email,
                'assigned_batches_count' => $batchesCount,
                'sessions_count' => $sessions->count(),
                'total_hours_taught' => round($totalHours, 1),
                'pending_grading_count' => $pendingGrading,
            ];
        }

        return [
            'teachers_count' => $teachers->count(),
            'teachers' => $workload,
        ];
    }

    /**
     * 7. Certificate Issuance Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getCertificateIssuance(CarbonInterface $from, CarbonInterface $to, ?int $courseId = null): array
    {
        $query = Certificate::query()
            ->whereBetween('issue_date', [$from->startOfDay(), $to->endOfDay()]);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $totalIssued = (clone $query)->count();
        $valid = (clone $query)->where('status', CertificateStatus::Valid)->count();
        $revoked = (clone $query)->where('status', CertificateStatus::Revoked)->count();
        $reissued = (clone $query)->where('status', CertificateStatus::Reissued)->count();

        $recentCertificates = (clone $query)
            ->with(['user', 'course'])
            ->latest('issue_date')
            ->take(20)
            ->get()
            ->map(function (Certificate $cert) {
                return [
                    'certificate_number' => $cert->certificate_number,
                    'recipient_name' => $cert->user instanceof User ? $cert->user->full_name : 'Student',
                    'course_name' => $cert->course instanceof Course ? $cert->course->title : 'Course Program',
                    'issue_date' => $cert->issue_date->format('M d, Y'),
                    'status' => $cert->status->value,
                    'public_token' => $cert->public_token,
                ];
            })->toArray();

        return [
            'total_issued' => $totalIssued,
            'valid_count' => $valid,
            'revoked_count' => $revoked,
            'reissued_count' => $reissued,
            'recent' => $recentCertificates,
        ];
    }

    /**
     * 8. Event Registrations Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getEventRegistrations(CarbonInterface $from, CarbonInterface $to, ?int $eventId = null): array
    {
        $eventsQuery = Event::query();

        if ($eventId) {
            $eventsQuery->where('id', $eventId);
        }

        $events = $eventsQuery->get();
        $eventStats = [];

        $grandRegistrations = 0;
        $grandCheckedIn = 0;

        foreach ($events as $event) {
            $regs = EventRegistration::query()
                ->where('event_id', $event->id)
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->get();

            $totalRegs = $regs->count();
            $plusOnes = $regs->where('has_plus_one', true)->count();
            $totalAttendees = $totalRegs + $plusOnes;
            $checkedIn = $regs->where('is_checked_in', true)->count();
            $checkInRate = $totalRegs > 0 ? round(($checkedIn / $totalRegs) * 100, 2) : 0.0;

            $grandRegistrations += $totalRegs;
            $grandCheckedIn += $checkedIn;

            $eventStats[] = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'format' => $event->format ?? 'In-Person',
                'capacity' => $event->capacity,
                'registrations_count' => $totalRegs,
                'plus_ones_count' => $plusOnes,
                'total_expected_attendees' => $totalAttendees,
                'checked_in_count' => $checkedIn,
                'check_in_rate_pct' => $checkInRate,
            ];
        }

        $overallRate = $grandRegistrations > 0
            ? round(($grandCheckedIn / $grandRegistrations) * 100, 2)
            : 0.0;

        return [
            'total_events' => $events->count(),
            'total_registrations' => $grandRegistrations,
            'total_checked_in' => $grandCheckedIn,
            'overall_check_in_rate_pct' => $overallRate,
            'events' => $eventStats,
        ];
    }

    /**
     * Aggregated Weekly Summary for Admin email report.
     *
     * @return array<string, mixed>
     */
    public function getWeeklySummary(CarbonInterface $startOfWeek, CarbonInterface $endOfWeek): array
    {
        $funnel = $this->getEnrollmentFunnel($startOfWeek, $endOfWeek);
        $revenue = $this->getRevenueReport($startOfWeek, $endOfWeek);
        $dues = $this->getOutstandingDues($startOfWeek, $endOfWeek);
        $attendance = $this->getAttendanceByBatch($startOfWeek, $endOfWeek);
        $certificates = $this->getCertificateIssuance($startOfWeek, $endOfWeek);
        $events = $this->getEventRegistrations($startOfWeek, $endOfWeek);

        return [
            'week_range' => $startOfWeek->format('M d, Y').' - '.$endOfWeek->format('M d, Y'),
            'new_enrollments' => $funnel['stages']['active'] + $funnel['stages']['approved'],
            'total_revenue' => $revenue['total_gross'],
            'transactions_count' => $revenue['transactions_count'],
            'outstanding_dues' => $dues['total_outstanding_amount'],
            'overdue_dues' => $dues['total_overdue_amount'],
            'average_attendance_pct' => $attendance['overall_attendance_rate_pct'],
            'certificates_issued' => $certificates['total_issued'],
            'event_registrations' => $events['total_registrations'],
        ];
    }
}
