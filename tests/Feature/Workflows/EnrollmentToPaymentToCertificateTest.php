<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\DifficultyLevel;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Module;
use App\Domain\Certification\Enums\CertificateStatus;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Certification\Services\CompletionEvaluator;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentToPaymentToCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $acharya;

    protected User $student;

    protected Course $course;

    protected Batch $batch;

    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Tapovan Rishikesh Ashram',
            'code' => 'TAP',
            'city' => 'Rishikesh',
            'state' => 'Uttarakhand',
            'country' => 'IN',
            'postal_code' => '249192',
        ]);

        $this->acharya = User::create([
            'first_name' => 'Swami',
            'last_name' => 'Brahmananda',
            'email' => 'brahmananda@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $this->student = User::create([
            'first_name' => 'Arjun',
            'last_name' => 'Kapoor',
            'email' => 'arjun.student@pramana.edu',
            'password' => Hash::make('Secret123!'),
            'status' => UserStatus::Active,
        ]);

        $category = Category::create([
            'name' => 'Traditional Hatha',
            'slug' => 'traditional-hatha',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'category_id' => $category->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->acharya->id,
            'name' => '200-Hour Classical Hatha Yoga Sadhana',
            'slug' => '200h-classical-hatha-yoga-sadhana',
            'default_delivery_mode' => DeliveryMode::Offline,
            'type' => CourseType::LongTerm,
            'difficulty_level' => DifficultyLevel::Beginner,
            'status' => CourseStatus::Published,
            'base_fee_amount' => 12500000, // 1,25,000 INR
            'base_fee_currency' => 'INR',
        ]);
        $this->course->teachers()->attach($this->acharya->id);

        $module = Module::create([
            'course_id' => $this->course->id,
            'name' => 'Asana Foundations & Alignment',
            'sort_order' => 1,
        ]);

        $this->lesson = Lesson::create([
            'module_id' => $module->id,
            'title' => 'Surya Namaskar Dynamic Alignment',
            'slug' => 'surya-namaskar-dynamic-alignment',
            'sort_order' => 1,
            'duration_minutes' => 90,
            'is_free_preview' => false,
        ]);

        $this->batch = Batch::create([
            'course_id' => $this->course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Spring 2027 Intensive',
            'delivery_mode' => DeliveryMode::Offline,
            'status' => BatchStatus::InProgress,
            'capacity' => 20,
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addMonth()->endOfDay(),
        ]);
    }

    /**
     * Complete Happy Path Test:
     * 1. Enrollment Creation ->
     * 2. Invoice Generation & Payment ->
     * 3. Batch Admission & Learning/Attendance Tracking ->
     * 4. Assessment Submission & Grading ->
     * 5. Completion Evaluation ->
     * 6. Certificate Issuance ->
     * 7. Public Verification & Badge Verification
     */
    public function test_full_enrollment_to_payment_to_certification_happy_path(): void
    {
        // -------------------------------------------------------------------------
        // Step 1: Student Enrolls in Course Batch
        // -------------------------------------------------------------------------
        $enrollment = Enrollment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => EnrollmentStatus::Approved,
            'seat_hold_expires_at' => now()->addHours(48),
        ]);

        $this->assertSame(EnrollmentStatus::Approved, $enrollment->status);

        // -------------------------------------------------------------------------
        // Step 2: Invoice Generated & Payment Processed
        // -------------------------------------------------------------------------
        $invoice = Invoice::create([
            'student_id' => $this->student->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'invoice_number' => 'INV-2027-0099',
            'financial_year' => '2026-2027',
            'sequence_number' => 99,
            'subtotal_amount' => 12500000,
            'discount_amount' => 0,
            'tax_amount' => 2250000, // 18% GST
            'total_amount' => 14750000,
            'currency' => 'INR',
            'status' => InvoicePaymentStatus::Pending,
            'due_date' => now()->addDays(2),
            'issued_at' => now(),
            'billing_name' => 'Arjun Kapoor',
            'billing_email' => 'arjun.student@pramana.edu',
        ]);

        $enrollment->transitionTo(EnrollmentStatus::PaymentPending);
        $this->assertSame(EnrollmentStatus::PaymentPending, $enrollment->status);

        // Process verified payment
        Payment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $this->student->id,
            'amount' => 14750000,
            'currency' => 'INR',
            'method' => PaymentMethod::Stripe,
            'gateway' => 'stripe',
            'gateway_transaction_id' => 'ch_test_happy_path_123',
            'status' => PaymentStatus::Verified,
            'verified_at' => now(),
        ]);

        MarkInvoicePaidAction::run($invoice);
        $invoice->refresh();
        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->status);

        // Verify Enrollment automatically transitioned to Active via MarkInvoicePaidAction
        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        // -------------------------------------------------------------------------
        // Step 3: Attendance & Class Sessions
        // -------------------------------------------------------------------------
        $session = Session::create([
            'batch_id' => $this->batch->id,
            'title' => 'Morning Pranayama & Asana Sadhana',
            'starts_at' => now()->subDays(2)->setTime(6, 0),
            'ends_at' => now()->subDays(2)->setTime(8, 0),
            'status' => SessionStatus::Completed,
        ]);

        SessionAttendance::create([
            'session_id' => $session->id,
            'user_id' => $this->student->id,
            'status' => 'present',
            'marked_by' => $this->acharya->id,
        ]);

        // Student learning progress recorded
        CourseProgress::create([
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'attendance_percentage' => 100.0,
            'lessons_completed' => 1,
            'total_required_lessons' => 1,
            'overall_percentage' => 100.0,
            'completed_at' => now(),
        ]);

        // -------------------------------------------------------------------------
        // Step 4: Assessment Submission & Grading
        // -------------------------------------------------------------------------
        $assignment = Assignment::create([
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'title' => 'Classical Alignment Practicum',
            'submission_types' => ['file', 'text'],
            'max_score' => 100,
            'due_at' => now()->addDays(5),
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'status' => 'graded',
            'score' => 95,
            'feedback' => 'Exemplary execution of alignment principles and breath control.',
            'graded_by' => $this->acharya->id,
            'graded_at' => now(),
            'submitted_at' => now()->subDay(),
        ]);
        $this->assertSame(95, $submission->score);

        // -------------------------------------------------------------------------
        // Step 5: Completion Evaluation
        // -------------------------------------------------------------------------
        $evaluator = app(CompletionEvaluator::class);
        $evaluation = $evaluator->evaluate($this->course, $this->student, $this->batch, [
            'min_attendance_percent' => 80,
        ]);

        $this->assertTrue($evaluation->is_passed, 'Student must pass all completion criteria.');

        // -------------------------------------------------------------------------
        // Step 6: Certificate Issuance
        // -------------------------------------------------------------------------
        $certificate = Certificate::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'certificate_number' => 'YOG-2027-000456',
            'public_token' => Str::random(32),
            'status' => CertificateStatus::Valid,
            'issue_date' => now()->toDateString(),
            'template_name' => 'Classical Himalayan Gurukula Gold',
            'rules_snapshot' => [
                'min_attendance_percent' => 80,
            ],
            'evaluation_snapshot' => [
                'name' => 'Arjun Kapoor',
                'course' => $this->course->name,
                'attendance_achieved' => 100,
            ],
        ]);

        $this->assertNotEmpty($certificate->public_token, 'Certificate must generate a secure random public verification token.');
        $this->assertSame(CertificateStatus::Valid, $certificate->status);

        // Enrollment reaches Completed status
        $enrollment->transitionTo(EnrollmentStatus::Completed);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);

        // -------------------------------------------------------------------------
        // Step 7: Public Verification Page Verification
        // -------------------------------------------------------------------------
        $verifyResponse = $this->get(route('certificates.verify', $certificate->public_token));
        $verifyResponse->assertStatus(200);
        $verifyResponse->assertSee('Arjun Kapoor', false);
        $verifyResponse->assertSee($this->course->name, false);
        $verifyResponse->assertSee('YOG-2027-000456', false);
        $verifyResponse->assertSee('VALID', false);

        // Open Badges standard JSON metadata
        $badgeResponse = $this->get(route('certificates.badge', $certificate->public_token));
        $badgeResponse->assertStatus(200);
        $badgeResponse->assertJsonPath('badge.name', $this->course->name);
        $badgeResponse->assertJsonPath('status', 'valid');
    }
}
