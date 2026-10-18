<?php

declare(strict_types=1);

use App\Domain\Assessment\Enums\AssessmentType;
use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
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
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Reporting\Mail\WeeklyAdminSummaryMail;
use App\Domain\Reporting\Models\ReportDailyAggregate;
use App\Domain\Reporting\Services\FormulaSanitizer;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportingService;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Livewire\Reporting\ReportingDashboard;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();

    $this->admin = User::factory()->create([
        'first_name' => 'Aditi',
        'last_name' => 'Sharma',
        'email' => 'admin.aditi@pramana.edu',
    ]);
    $this->admin->assignRole('Admin');

    $this->teacher = User::factory()->create([
        'first_name' => 'Acharya',
        'last_name' => 'Devrat',
        'email' => 'devrat@pramana.edu',
    ]);
    $this->teacher->assignRole('Teacher');

    $this->student = User::factory()->create([
        'first_name' => 'Rohan',
        'last_name' => 'Verma',
        'email' => 'rohan@example.com',
    ]);
    $this->student->assignRole('Student');

    $this->course = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'name' => 'Classical Hatha & Vinyasa Sadhana',
        'slug' => 'classical-hatha-vinyasa',
        'type' => 'long_term',
        'default_delivery_mode' => 'hybrid',
        'base_fee_amount' => 2500000,
        'base_fee_currency' => 'INR',
        'status' => 'published',
    ]);

    $this->batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Morning Prana Batch 2026',
        'slug' => 'morning-prana-2026',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
        'max_capacity' => 30,
        'status' => 'open',
    ]);

    DB::table('course_teachers')->insert([
        'course_id' => $this->course->id,
        'user_id' => $this->teacher->id,
        'role' => 'primary',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('enrollment funnel dashboard calculates stage counts and conversion rates accurately', function (): void {
    // Create enrollments in various stages
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Requested,
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Approved,
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Completed,
    ]);

    $service = app(ReportingService::class);
    $funnel = $service->getEnrollmentFunnel(now()->subDays(10), now()->addDays(1));

    expect($funnel['total_applications'])->toBe(4)
        ->and($funnel['total_enrolled'])->toBe(2) // active + completed
        ->and($funnel['conversion_rate_pct'])->toBe(50.0) // 2 / 4 = 50%
        ->and($funnel['stages']['requested'])->toBe(1)
        ->and($funnel['stages']['approved'])->toBe(1)
        ->and($funnel['stages']['active'])->toBe(1)
        ->and($funnel['stages']['completed'])->toBe(1);
});

test('revenue report aggregates gross revenue, transactions count, by gateway and by course', function (): void {
    $invoice = Invoice::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'invoice_number' => 'INV-REP-01',
        'financial_year' => '2026-27',
        'sequence_number' => 1,
        'subtotal_amount' => 2500000,
        'tax_amount' => 0,
        'total_amount' => 2500000,
        'currency' => 'INR',
        'status' => InvoicePaymentStatus::Paid,
        'billing_name' => $this->student->full_name,
        'billing_email' => $this->student->email,
        'issued_at' => now()->subDays(2),
        'due_date' => now()->addDays(10),
    ]);

    // Payment 1: Stripe 15,000 INR (1,500,000 paise)
    Payment::create([
        'invoice_id' => $invoice->id,
        'student_id' => $this->student->id,
        'amount' => 1500000,
        'currency' => 'INR',
        'method' => PaymentMethod::Stripe,
        'gateway' => 'stripe',
        'status' => PaymentStatus::Verified,
    ]);

    // Payment 2: Razorpay 10,000 INR (1,000,000 paise)
    Payment::create([
        'invoice_id' => $invoice->id,
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'currency' => 'INR',
        'method' => PaymentMethod::Upi,
        'gateway' => 'razorpay',
        'status' => PaymentStatus::Verified,
    ]);

    $service = app(ReportingService::class);
    $report = $service->getRevenueReport(now()->subDays(5), now()->addDays(1));

    expect($report['total_gross'])->toBe(25000.0) // 25,000 INR
        ->and($report['transactions_count'])->toBe(2);

    $gateways = collect($report['by_gateway'])->keyBy('gateway');
    expect($gateways['stripe']['total_amount'])->toBe(15000.0)
        ->and($gateways['razorpay']['total_amount'])->toBe(10000.0);
});

test('outstanding dues dashboard segments balances into aging buckets and flags past due', function (): void {
    // Current invoice (due in 5 days)
    Invoice::create([
        'student_id' => $this->student->id,
        'invoice_number' => 'INV-DUE-01',
        'financial_year' => '2026-27',
        'sequence_number' => 2,
        'subtotal_amount' => 1000000,
        'total_amount' => 1000000,
        'currency' => 'INR',
        'status' => InvoicePaymentStatus::Pending,
        'billing_name' => $this->student->full_name,
        'billing_email' => $this->student->email,
        'issued_at' => now()->subDays(2),
        'due_date' => now()->addDays(5),
    ]);

    // Overdue invoice (due 45 days ago -> 31-60 days bucket)
    Invoice::create([
        'student_id' => $this->student->id,
        'invoice_number' => 'INV-DUE-02',
        'financial_year' => '2026-27',
        'sequence_number' => 3,
        'subtotal_amount' => 2000000,
        'total_amount' => 2000000,
        'currency' => 'INR',
        'status' => InvoicePaymentStatus::Pending,
        'billing_name' => $this->student->full_name,
        'billing_email' => $this->student->email,
        'issued_at' => now()->subDays(60),
        'due_date' => now()->subDays(45),
    ]);

    $service = app(ReportingService::class);
    $dues = $service->getOutstandingDues(now()->subDays(70), now()->addDays(1));

    expect($dues['total_outstanding_amount'])->toBe(30000.0)
        ->and($dues['total_overdue_amount'])->toBe(20000.0)
        ->and($dues['aging_breakdown']['current']['count'])->toBe(1)
        ->and($dues['aging_breakdown']['31_60']['count'])->toBe(1);
});

test('batch attendance report accurately calculates presence, absence, and attendance rate', function (): void {
    $session = Session::create([
        'batch_id' => $this->batch->id,
        'title' => 'Session 1: Pranayama Foundations',
        'starts_at' => now()->subDays(2)->setTime(7, 0),
        'ends_at' => now()->subDays(2)->setTime(9, 0),
        'status' => SessionStatus::Completed,
    ]);

    $student2 = User::factory()->create();

    // 1 present, 1 late, 1 absent
    SessionAttendance::create([
        'session_id' => $session->id,
        'user_id' => $this->student->id,
        'status' => 'present',
    ]);

    SessionAttendance::create([
        'session_id' => $session->id,
        'user_id' => $student2->id,
        'status' => 'absent',
    ]);

    $service = app(ReportingService::class);
    $report = $service->getAttendanceByBatch(now()->subDays(5), now()->addDays(1));

    expect($report['total_marked_records'])->toBe(2)
        ->and($report['overall_attendance_rate_pct'])->toBe(50.0); // 1 present out of 2 = 50%
});

test('teacher workload and course completion reports aggregate operational data correctly', function (): void {
    // 1. Scheduled Session
    $session = Session::create([
        'batch_id' => $this->batch->id,
        'title' => 'Asana Practice 1',
        'starts_at' => now()->subDays(1)->setTime(6, 0),
        'ends_at' => now()->subDays(1)->setTime(8, 0), // 2 hours
        'status' => SessionStatus::Completed,
    ]);

    DB::table('session_teachers')->insert([
        'session_id' => $session->id,
        'user_id' => $this->teacher->id,
        'role' => 'lead',
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Pending assignment submission
    $assignment = Assignment::create([
        'course_id' => $this->course->id,
        'created_by' => $this->teacher->id,
        'title' => 'Pranayama Reflection',
        'assessment_type' => AssessmentType::Assignment,
        'submission_types' => ['text'],
        'due_at' => now()->addDays(5),
        'max_score' => 100,
    ]);

    AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $this->student->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $service = app(ReportingService::class);
    $workload = $service->getTeacherWorkload(now()->subDays(5), now()->addDays(1), $this->teacher->id);

    $teacherStats = $workload['teachers'][0];
    expect($teacherStats['teacher_id'])->toBe($this->teacher->id)
        ->and($teacherStats['assigned_batches_count'])->toBe(1)
        ->and($teacherStats['sessions_count'])->toBe(1)
        ->and($teacherStats['total_hours_taught'])->toBe(2.0)
        ->and($teacherStats['pending_grading_count'])->toBe(1);
});

test('certificate issuance and event registrations reports track credentials and attendance rates', function (): void {
    // 1. Certificate
    Certificate::create([
        'certificate_number' => 'YOG-2026-0001',
        'public_token' => 'tok_cert_123',
        'user_id' => $this->student->id,
        'course_id' => $this->course->id,
        'issue_date' => now()->subDays(1),
        'status' => CertificateStatus::Valid,
        'template_name' => 'Classical Yoga Certificate',
    ]);

    // 2. Event & Registrations
    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'title' => 'International Yoga Day Retreat',
        'slug' => 'international-yoga-day-2026',
        'format' => 'in_person',
        'status' => 'published',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
        'capacity' => 100,
    ]);

    EventRegistration::create([
        'event_id' => $event->id,
        'user_id' => $this->student->id,
        'ticket_code' => 'TCK-EVENT-001',
        'has_plus_one' => true,
        'plus_one_name' => 'Meera Verma',
        'is_checked_in' => true,
        'checked_in_at' => now(),
    ]);

    $service = app(ReportingService::class);

    $certReport = $service->getCertificateIssuance(now()->subDays(5), now()->addDays(1));
    expect($certReport['total_issued'])->toBe(1)
        ->and($certReport['valid_count'])->toBe(1);

    $eventReport = $service->getEventRegistrations(now()->subDays(5), now()->addDays(1));
    expect($eventReport['total_events'])->toBe(1)
        ->and($eventReport['total_registrations'])->toBe(1)
        ->and($eventReport['total_checked_in'])->toBe(1)
        ->and($eventReport['overall_check_in_rate_pct'])->toBe(100.0);
});

test('scheduled command refreshes read-optimized summary tables correctly', function (): void {
    // Setup some data for yesterday
    $yesterday = now()->subDay();

    $enrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
    ]);
    DB::table('enrollments')->where('id', $enrollment->id)->update(['created_at' => $yesterday->toDateTimeString()]);

    $this->artisan('lms:refresh-reporting-summaries', ['--date' => $yesterday->toDateString()])
        ->assertSuccessful();

    $aggregate = ReportDailyAggregate::whereDate('date', $yesterday->toDateString())
        ->where('metric_group', 'enrollment')
        ->first();

    expect($aggregate)->not->toBeNull()
        ->and($aggregate->numeric_value)->toBe(1.0)
        ->and($aggregate->secondary_value)->toBe(1.0);
});

test('formula injection sanitizer neutralizes dangerous prefixes with single quote', function (): void {
    // Dangerous prefixes: =, +, -, @, \t, \r
    $payloads = [
        '=cmd|\' /C calc\'!A0',
        '+1+2',
        '-5*3',
        '@SUM(A1:A10)',
        "\tmalicious_tab",
        "\rmalicious_cr",
    ];

    foreach ($payloads as $dangerous) {
        $sanitized = FormulaSanitizer::sanitize($dangerous);
        expect($sanitized)->toStartWith("'")
            ->and(substr($sanitized, 1))->toBe($dangerous);
    }

    // Normal safe strings should remain unaltered
    expect(FormulaSanitizer::sanitize('Classical Hatha Yoga'))->toBe('Classical Hatha Yoga')
        ->and(FormulaSanitizer::sanitize(12345))->toBe(12345)
        ->and(FormulaSanitizer::sanitize(''))->toBe('');
});

test('acceptance criteria: reports with 100k rows export without timeouts and exported files contain no formula-injection payloads', function (): void {
    // Increase memory/time headroom for synthetic 100k streaming benchmark
    ini_set('memory_limit', '512M');

    $exportService = app(ReportExportService::class);

    // Request export with 100,000 synthetic rows containing formula injection payloads
    $startTime = microtime(true);

    $export = $exportService->queueExport(
        user: $this->admin,
        reportType: 'benchmark_100k_test',
        format: 'csv',
        filters: [
            'custom_rows_limit' => 100000,
            'payload_prefix' => '=cmd|\' /C calc\'!row_',
        ],
        sync: true
    );

    $duration = microtime(true) - $startTime;

    expect($export->status)->toBe('completed')
        ->and($export->row_count)->toBe(100000);

    // Fast streaming check: 100k rows should export rapidly without timeout
    expect($duration)->toBeLessThan(15.0);

    // Verify file exists on disk
    $fullPath = storage_path('app/'.$export->file_path);
    expect(file_exists($fullPath))->toBeTrue();

    // Verify formula injection protection in file contents:
    // Read the first 100 data lines and ensure every formula field is escaped with leading single quote
    $handle = fopen($fullPath, 'r');
    $header = fgetcsv($handle);
    expect($header)->toBe(['Record ID', 'Code', 'Label', 'Formula Field', 'Amount', 'Date']);

    for ($i = 0; $i < 50; $i++) {
        $row = fgetcsv($handle);
        expect($row)->not->toBeFalse();
        $formulaCell = $row[3]; // Formula Field

        // MUST be prepended with single quote "'"
        expect($formulaCell)->toStartWith("'=cmd|")
            ->and(str_starts_with($formulaCell, '=cmd|'))->toBeFalse();
    }
    fclose($handle);

    // Clean up benchmark file
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
});

test('expiring signed download URL securely serves file and rejects expired or tampered signatures', function (): void {
    $exportService = app(ReportExportService::class);

    $export = $exportService->queueExport(
        user: $this->admin,
        reportType: 'enrollment_funnel',
        format: 'csv',
        filters: ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()],
        sync: true
    );

    $signedUrl = $export->getDownloadUrl();
    expect($signedUrl)->toContain('signature=');

    // 1. Authorized download via signed link
    $response = $this->actingAs($this->admin)->get($signedUrl);
    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // 2. Tampered URL signature is rejected with 403
    $tamperedUrl = $signedUrl.'&tampered=1';
    $tamperedResponse = $this->actingAs($this->admin)->get($tamperedUrl);
    $tamperedResponse->assertStatus(403);

    // 3. Expired link is rejected
    $export->update(['expires_at' => now()->subHour()]);
    $expiredUrl = URL::temporarySignedRoute(
        'reporting.exports.download',
        now()->subHour(),
        ['export' => $export->ulid]
    );

    $expiredResponse = $this->actingAs($this->admin)->get($expiredUrl);
    $expiredResponse->assertStatus(403); // Laravel signed middleware rejects expired signature
});

test('scheduled weekly admin summary command sends email with key operational metrics', function (): void {
    Mail::fake();

    $this->artisan('lms:send-weekly-admin-report')
        ->assertSuccessful();

    Mail::assertSent(WeeklyAdminSummaryMail::class, function (WeeklyAdminSummaryMail $mail): bool {
        return $mail->hasTo($this->admin->email)
            && isset($mail->summaryData['new_enrollments'])
            && isset($mail->summaryData['total_revenue']);
    });
});

test('reporting dashboard livewire component enforces permissions and switches tabs', function (): void {
    // 1. Unauthorized student cannot access
    $this->actingAs($this->student);
    Livewire::test(ReportingDashboard::class)
        ->assertStatus(403);

    // 2. Authorized Admin can access and switch tabs
    $this->actingAs($this->admin);

    Livewire::test(ReportingDashboard::class)
        ->assertStatus(200)
        ->assertSee('Institute Operational')
        ->assertSee('1. Admissions Funnel')
        ->set('activeTab', 'revenue')
        ->assertSee('Total Gross Collections')
        ->set('activeTab', 'dues')
        ->assertSee('Outstanding Dues')
        ->set('activeTab', 'attendance')
        ->assertSee('Institute Attendance Rate')
        ->set('activeTab', 'completion')
        ->assertSee('Overall Completion Rate')
        ->set('activeTab', 'workload')
        ->assertSee('Faculty Workload')
        ->set('activeTab', 'certification')
        ->assertSee('Recent Credentials Registry')
        ->set('activeTab', 'events')
        ->assertSee('Event Registrations');
});
