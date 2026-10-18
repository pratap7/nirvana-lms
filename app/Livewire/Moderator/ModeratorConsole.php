<?php

declare(strict_types=1);

namespace App\Livewire\Moderator;

use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Communication\Models\SupportTicket;
use App\Domain\Communication\Models\SupportTicketMessage;
use App\Domain\Enrollment\Actions\RegisterStudentOfflineAction;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.moderator')]
class ModeratorConsole extends Component
{
    public string $activeTab = 'enrollments'; // enrollments, offline_reg, scheduling, attendance, payment, tickets, lookup

    // Enrollment Review
    public ?string $rejectingEnrollmentUlid = null;

    public string $rejectionReason = '';

    // Offline Registration Form
    public string $regFirstName = '';

    public string $regLastName = '';

    public string $regEmail = '';

    public string $regPhone = '';

    public ?string $regBatchId = null;

    // Attendance Correction Form
    public ?string $attSessionUlid = null;

    public ?int $attStudentId = null;

    public string $attNewStatus = 'present';

    public string $attReason = '';

    // Manual Payment Entry Form
    public ?string $payInvoiceUlid = null;

    public int $payAmount = 0;

    public string $payMethod = 'upi';

    public string $payReference = '';

    // Support Ticket Reply Form
    public ?string $selectedTicketUlid = null;

    public string $ticketReplyText = '';

    // Student Lookup Search
    public string $searchStudent = '';

    // Feedback notifications
    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Authorize moderator role or permissions
        if (! $user->hasRole(['Admin', 'Super Admin', 'Moderator'])) {
            abort(403, 'Unauthorized access to Academic Moderator console.');
        }

        $firstBatch = Batch::query()->first();
        if ($firstBatch) {
            $this->regBatchId = (string) $firstBatch->id;
        }

        $firstInvoice = Invoice::query()->where('status', '!=', InvoicePaymentStatus::Paid->value)->first();
        if ($firstInvoice) {
            $this->payInvoiceUlid = $firstInvoice->ulid;
            $this->payAmount = $firstInvoice->getRemainingBalance()->getAmount();
        }
    }

    public function approveEnrollment(string $enrollmentUlid, TransitionEnrollmentAction $transitionAction): void
    {
        /** @var Enrollment $enrollment */
        $enrollment = Enrollment::query()->where('ulid', $enrollmentUlid)->firstOrFail();

        $transitionAction->execute($enrollment, EnrollmentStatus::Approved, Auth::user());
        $this->feedbackMessage = "Enrollment for {$enrollment->student?->full_name} has been approved.";
    }

    public function rejectEnrollment(TransitionEnrollmentAction $transitionAction): void
    {
        if (! $this->rejectingEnrollmentUlid) {
            return;
        }

        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ]);

        /** @var Enrollment $enrollment */
        $enrollment = Enrollment::query()->where('ulid', $this->rejectingEnrollmentUlid)->firstOrFail();

        $transitionAction->execute($enrollment, EnrollmentStatus::Rejected, Auth::user(), [
            'reason' => $this->rejectionReason,
        ]);

        $this->rejectingEnrollmentUlid = null;
        $this->rejectionReason = '';
        $this->feedbackMessage = "Enrollment for {$enrollment->student?->full_name} was rejected with recorded reason.";
    }

    public function registerStudentOffline(RegisterStudentOfflineAction $registerAction): void
    {
        $this->validate([
            'regFirstName' => 'required|string|max:100',
            'regLastName' => 'required|string|max:100',
            'regEmail' => 'required|email|max:255',
            'regPhone' => 'nullable|string|max:20',
            'regBatchId' => 'required|exists:batches,id',
        ]);

        /** @var Batch $batch */
        $batch = Batch::query()->findOrFail((int) $this->regBatchId);

        $enrollment = $registerAction->execute(
            studentData: [
                'first_name' => $this->regFirstName,
                'last_name' => $this->regLastName,
                'email' => $this->regEmail,
                'phone' => $this->regPhone,
            ],
            batch: $batch,
            moderator: Auth::user()
        );

        $this->feedbackMessage = "Student {$enrollment->student?->full_name} successfully registered offline into batch {$batch->name}.";
        $this->regFirstName = '';
        $this->regLastName = '';
        $this->regEmail = '';
        $this->regPhone = '';
    }

    public function submitAttendanceCorrection(): void
    {
        $this->validate([
            'attSessionUlid' => 'required|exists:course_sessions,ulid',
            'attStudentId' => 'required|exists:users,id',
            'attNewStatus' => 'required|in:present,absent,late,excused',
            'attReason' => 'required|string|min:5|max:255',
        ]);

        /** @var User $moderator */
        $moderator = Auth::user();

        /** @var Session $session */
        $session = Session::query()->where('ulid', $this->attSessionUlid)->firstOrFail();

        $attendance = SessionAttendance::query()->updateOrCreate(
            [
                'session_id' => $session->id,
                'user_id' => $this->attStudentId,
            ],
            [
                'status' => $this->attNewStatus,
                'correction_reason' => trim($this->attReason),
                'edited_by' => $moderator->id,
            ]
        );

        activity('attendance')
            ->causedBy($moderator)
            ->performedOn($attendance)
            ->withProperties([
                'session_id' => $session->id,
                'student_id' => $this->attStudentId,
                'new_status' => $this->attNewStatus,
                'reason' => trim($this->attReason),
            ])
            ->log("Attendance corrected by moderator with reason: {$this->attReason}");

        $this->feedbackMessage = "Attendance corrected to {$this->attNewStatus}. Audit entry logged.";
        $this->attReason = '';
    }

    public function recordManualPayment(): void
    {
        $this->validate([
            'payInvoiceUlid' => 'required|exists:invoices,ulid',
            'payAmount' => 'required|integer|min:100',
            'payMethod' => 'required|in:cash,bank_transfer,upi',
            'payReference' => 'required|string|max:100',
        ]);

        /** @var User $moderator */
        $moderator = Auth::user();

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->where('ulid', $this->payInvoiceUlid)->firstOrFail();

        Payment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'amount' => $this->payAmount,
            'currency' => $invoice->currency,
            'method' => PaymentMethod::from($this->payMethod),
            'gateway' => 'manual',
            'status' => PaymentStatus::Verified,
            'reference_number' => $this->payReference,
            'recorded_by' => $moderator->id,
            'verified_by' => $moderator->id,
            'verified_at' => CarbonImmutable::now(),
            'payload' => [
                'type' => 'manual_offline_receipt',
                'entered_by' => $moderator->full_name,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $invoice->recalculateStatus();

        $this->feedbackMessage = "Manual payment of {$this->payAmount} recorded and settled on invoice {$invoice->invoice_number}.";
        $this->payReference = '';
    }

    public function replyToTicket(): void
    {
        $this->validate([
            'ticketReplyText' => 'required|string|min:3|max:2000',
        ]);

        /** @var User $moderator */
        $moderator = Auth::user();

        /** @var SupportTicket $ticket */
        $ticket = SupportTicket::query()->where('ulid', $this->selectedTicketUlid)->firstOrFail();

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $moderator->id,
            'message' => $this->ticketReplyText,
            'is_staff_reply' => true,
        ]);

        $ticket->update([
            'status' => 'in_progress',
            'assigned_to' => $moderator->id,
        ]);

        $this->ticketReplyText = '';
        $this->feedbackMessage = 'Staff response dispatched to student ticket.';
    }

    public function render(): View
    {
        // 1. Pending enrollments queue
        /** @var Collection<int, Enrollment> $pendingEnrollments */
        $pendingEnrollments = Enrollment::query()
            ->whereIn('status', [EnrollmentStatus::Requested->value, EnrollmentStatus::UnderReview->value])
            ->with(['student', 'course', 'batch'])
            ->latest()
            ->take(25)
            ->get();

        // 2. Batches list for dropdowns
        $batches = Batch::query()->with('course')->get();

        // 3. Sessions for attendance corrections
        $sessions = Session::query()->with('batch')->latest('starts_at')->take(30)->get();

        // 4. Invoices pending payment
        $unpaidInvoices = Invoice::query()
            ->where('status', '!=', InvoicePaymentStatus::Paid->value)
            ->with(['student', 'course'])
            ->latest('issued_at')
            ->take(30)
            ->get();

        // 5. Support tickets
        /** @var Collection<int, SupportTicket> $tickets */
        $tickets = SupportTicket::query()->with(['user', 'assignee'])->latest()->take(25)->get();
        $activeTicket = $this->selectedTicketUlid
            ? SupportTicket::query()->with(['user', 'messages.user'])->where('ulid', $this->selectedTicketUlid)->first()
            : null;

        // 6. Student lookup search
        $searchedStudents = collect();
        if (trim($this->searchStudent) !== '') {
            $term = trim($this->searchStudent);
            $tokens = array_values(array_filter(explode(' ', $term)));
            $searchedStudents = User::query()
                ->where(function ($q) use ($tokens, $term): void {
                    $q->where('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");

                    $q->orWhere(function ($nameQuery) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $nameQuery->where(function ($sub) use ($token): void {
                                $sub->where('first_name', 'like', "%{$token}%")
                                    ->orWhere('last_name', 'like', "%{$token}%");
                            });
                        }
                    });
                })
                ->with(['studentProfile', 'enrollments.batch', 'enrollments.course'])
                ->take(10)
                ->get();
        }

        $canViewHealthData = Auth::user()->hasPermissionTo('student.health.view')
            || Auth::user()->hasRole(['Admin', 'Super Admin']);

        return view('livewire.moderator.moderator-console', [
            'pendingEnrollments' => $pendingEnrollments,
            'batches' => $batches,
            'sessions' => $sessions,
            'unpaidInvoices' => $unpaidInvoices,
            'tickets' => $tickets,
            'activeTicket' => $activeTicket,
            'searchedStudents' => $searchedStudents,
            'canViewHealthData' => $canViewHealthData,
        ]);
    }
}
