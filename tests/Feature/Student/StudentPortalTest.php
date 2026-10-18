<?php

declare(strict_types=1);

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Assessment\Models\Quiz;
use App\Domain\Assessment\Models\QuizQuestion;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\InstallmentPlan;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Enums\LessonContentType;
use App\Domain\Catalog\Enums\ResourceType;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\LessonResource;
use App\Domain\Catalog\Models\Module;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDeletionRequest;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\StudentProgressService;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Livewire\Student\AssignmentPortal;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\PaymentHistory;
use App\Livewire\Student\QuizPlayer;
use App\Livewire\Student\StudentProfile;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'User']);
    $this->admin->assignRole('Admin');

    $this->course = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'name' => 'Advanced Hatha Yoga & Pranayama',
        'slug' => 'advanced-hatha-yoga-pranayama',
        'type' => CourseType::ShortTerm,
        'delivery_mode' => DeliveryMode::Online,
        'status' => CourseStatus::Published,
        'fee_amount' => 1500000,
        'fee_currency' => 'INR',
        'duration_weeks' => 12,
    ]);

    $this->batchA = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Batch Alpha',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
        'capacity' => 20,
        'status' => BatchStatus::Open,
    ]);

    $this->batchB = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Batch Beta',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-10-01',
        'end_date' => '2026-12-31',
        'capacity' => 20,
        'status' => BatchStatus::Open,
    ]);

    $this->module = Module::create([
        'course_id' => $this->course->id,
        'name' => 'Module 1: Asana Fundamentals',
        'sort_order' => 1,
    ]);

    $this->lesson = Lesson::create([
        'module_id' => $this->module->id,
        'title' => 'Surya Namaskar Dynamic Alignment',
        'slug' => 'surya-namaskar-dynamic-alignment',
        'content_type' => LessonContentType::Video,
        'duration_minutes' => 45,
        'is_mandatory' => true,
        'is_published' => true,
        'sort_order' => 1,
    ]);

    $this->resource = LessonResource::create([
        'lesson_id' => $this->lesson->id,
        'title' => 'Alignment-Cheatsheet.pdf',
        'type' => ResourceType::Pdf,
        'url_or_path' => 'courses/resources/cheatsheet.pdf',
        'file_size_bytes' => 204800,
        'sort_order' => 1,
    ]);

    $this->student = User::factory()->create([
        'first_name' => 'Pooja',
        'last_name' => 'Sharma',
        'email' => 'pooja@example.com',
    ]);
    $this->student->assignRole('Student');
});

test('ACCEPTANCE: A student cannot open a lesson or stream video from a batch they are not active in', function (): void {
    // 1. Without any enrollment: 403 Forbidden
    $response = $this->actingAs($this->student)
        ->get(route('student.lessons.stream', ['lesson' => $this->lesson->ulid]));
    $response->assertStatus(403);

    // 2. With waitlisted or payment_pending enrollment: 403 Forbidden
    $enrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Waitlisted,
    ]);

    $response = $this->actingAs($this->student)
        ->get(route('student.lessons.stream', ['lesson' => $this->lesson->ulid]));
    $response->assertStatus(403);

    // 3. Active in Batch A, but tries to guess URL or access Batch B context
    $enrollment->update(['status' => EnrollmentStatus::Active]);

    $responseFromBatchB = $this->actingAs($this->student)
        ->get(route('student.lessons.stream', [
            'lesson' => $this->lesson->ulid,
            'batch' => $this->batchB->ulid,
        ]));
    $responseFromBatchB->assertStatus(403);

    // 4. Active student accessing their active batch: 200 OK with streaming headers
    $responseSuccess = $this->actingAs($this->student)
        ->get(route('student.lessons.stream', [
            'lesson' => $this->lesson->ulid,
            'batch' => $this->batchA->ulid,
        ]));
    $responseSuccess->assertStatus(200);
    $responseSuccess->assertHeader('Content-Type', 'video/mp4');
    $responseSuccess->assertHeader('X-Watermark-Student', $this->student->ulid);
});

test('ACCEPTANCE: A student cannot download protected lesson resources from an inactive batch', function (): void {
    // 1. No active enrollment -> 403 Forbidden
    $response = $this->actingAs($this->student)
        ->get(route('student.resources.download', ['resource' => $this->resource->ulid]));
    $response->assertStatus(403);

    // 2. Active in Batch A, downloading for Batch A -> 200 OK
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    $responseSuccess = $this->actingAs($this->student)
        ->get(route('student.resources.download', [
            'resource' => $this->resource->ulid,
            'batch' => $this->batchA->ulid,
        ]));
    $responseSuccess->assertStatus(200);
    $responseSuccess->assertHeader('Content-Disposition', 'attachment; filename="Alignment-Cheatsheet.pdf"');

    // 3. Attempting to download via unauthorized Batch B -> 403 Forbidden
    $responseUnauthorized = $this->actingAs($this->student)
        ->get(route('student.resources.download', [
            'resource' => $this->resource->ulid,
            'batch' => $this->batchB->ulid,
        ]));
    $responseUnauthorized->assertStatus(403);
});

test('Video progress ping auto-marks lesson complete when watch percentage >= 90%', function (): void {
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    // 1. Ping with 50% watched
    $this->actingAs($this->student)
        ->postJson(route('student.lessons.progress', ['lesson' => $this->lesson->ulid]), [
            'seconds_watched' => 300,
            'total_duration' => 600,
            'watch_percentage' => 50.0,
        ])
        ->assertStatus(200)
        ->assertJson([
            'is_completed' => false,
            'watch_percentage' => 50.0,
        ]);

    $progress = LessonProgress::query()
        ->where('user_id', $this->student->id)
        ->where('lesson_id', $this->lesson->id)
        ->first();
    expect($progress)->not->toBeNull();
    expect($progress->is_completed)->toBeFalse();

    // 2. Ping with 91% watched -> auto-completes
    $this->actingAs($this->student)
        ->postJson(route('student.lessons.progress', ['lesson' => $this->lesson->ulid]), [
            'seconds_watched' => 550,
            'total_duration' => 600,
            'watch_percentage' => 91.6,
        ])
        ->assertStatus(200)
        ->assertJson([
            'is_completed' => true,
        ]);

    $progress->refresh();
    expect($progress->is_completed)->toBeTrue();
    expect($progress->completed_at)->not->toBeNull();
});

test('StudentProgressService computes completion, attendance over held sessions, and quiz average', function (): void {
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    // Create a 2nd required lesson and an optional/unpublished lesson
    $lesson2 = Lesson::create([
        'module_id' => $this->module->id,
        'title' => 'Pranayama Basics',
        'slug' => 'pranayama-basics',
        'content_type' => LessonContentType::Video,
        'is_mandatory' => true,
        'is_published' => true,
        'sort_order' => 2,
    ]);

    Lesson::create([
        'module_id' => $this->module->id,
        'title' => 'Optional Bonus Chant',
        'slug' => 'optional-bonus-chant',
        'content_type' => LessonContentType::Audio,
        'is_mandatory' => false,
        'is_published' => true,
        'sort_order' => 3,
    ]);

    Lesson::create([
        'module_id' => $this->module->id,
        'title' => 'Unpublished Draft Lesson',
        'slug' => 'unpublished-draft-lesson',
        'content_type' => LessonContentType::Video,
        'is_mandatory' => true,
        'is_published' => false,
        'sort_order' => 4,
    ]);

    // Mark 1 out of 2 published mandatory lessons complete (50% lesson progress)
    LessonProgress::create([
        'user_id' => $this->student->id,
        'lesson_id' => $this->lesson->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    // Create 3 sessions: 2 held in past, 1 in future
    $session1 = Session::create([
        'batch_id' => $this->batchA->id,
        'title' => 'Session 1',
        'slug' => 'session-1',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Completed,
    ]);
    $session2 = Session::create([
        'batch_id' => $this->batchA->id,
        'title' => 'Session 2',
        'slug' => 'session-2',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Completed,
    ]);
    Session::create([
        'batch_id' => $this->batchA->id,
        'title' => 'Session 3 (Future)',
        'slug' => 'session-3-future',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Scheduled,
    ]);

    // Student attended session 1, missed session 2 (Attendance = 1/2 = 50% over held sessions)
    SessionAttendance::create([
        'session_id' => $session1->id,
        'user_id' => $this->student->id,
        'status' => 'present',
        'recorded_at' => now()->subDays(2),
    ]);
    SessionAttendance::create([
        'session_id' => $session2->id,
        'user_id' => $this->student->id,
        'status' => 'absent',
        'recorded_at' => now()->subDay(),
    ]);

    /** @var StudentProgressService $progressService */
    $progressService = app(StudentProgressService::class);
    $courseProgress = $progressService->recalculateCourseProgress($this->student, $this->course, $this->batchA);

    expect($courseProgress->lessons_completed)->toBe(1);
    expect($courseProgress->total_required_lessons)->toBe(2); // Only published mandatory
    expect($courseProgress->lesson_completion_percentage)->toBe(50.0);
    expect($courseProgress->attendance_percentage)->toBe(50.0);
});

test('Student Dashboard Livewire component renders courses, progress rings, and upcoming sessions', function (): void {
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    Announcement::create([
        'batch_id' => $this->batchA->id,
        'title' => 'Welcome to Monsoon 2026 Batch',
        'content' => 'Please download the course syllabus and alignment handbook.',
        'author_id' => $this->admin->id,
        'published_at' => now(),
    ]);

    Session::create([
        'batch_id' => $this->batchA->id,
        'title' => 'Orientation & Asana Principles',
        'slug' => 'orientation-asana-principles',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Scheduled,
    ]);

    Livewire::actingAs($this->student)
        ->test(Dashboard::class)
        ->assertSee('Advanced Hatha Yoga')
        ->assertSee('Welcome to Monsoon 2026 Batch')
        ->assertSee('Orientation &amp; Asana Principles', false);
});

test('QuizPlayer Livewire component auto-grades attempts and records results', function (): void {
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    $quiz = Quiz::create([
        'course_id' => $this->course->id,
        'title' => 'Yoga Sutras & Anatomy Midterm',
        'time_limit_minutes' => 30,
        'passing_score' => 70,
        'max_attempts' => 3,
        'is_published' => true,
    ]);

    $q1 = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question_type' => 'true_false',
        'question_text' => 'Pranayama is the 4th limb of Ashtanga Yoga.',
        'points' => 10,
        'sort_order' => 1,
        'options' => [
            ['id' => 'true', 'text' => 'True', 'is_correct' => true],
            ['id' => 'false', 'text' => 'False', 'is_correct' => false],
        ],
        'explanation' => 'Patanjali enumerates: Yama, Niyama, Asana, Pranayama, Pratyahara, Dharana, Dhyana, Samadhi.',
    ]);

    $q2 = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question_type' => 'mcq_single',
        'question_text' => 'Which dosha is associated with fire and water?',
        'points' => 10,
        'sort_order' => 2,
        'options' => [
            ['id' => 'vata', 'text' => 'Vata', 'is_correct' => false],
            ['id' => 'pitta', 'text' => 'Pitta', 'is_correct' => true],
            ['id' => 'kapha', 'text' => 'Kapha', 'is_correct' => false],
        ],
        'explanation' => 'Pitta dosha embodies heat, transformation and metabolism.',
    ]);

    // Test starting and completing the quiz
    Livewire::actingAs($this->student)
        ->test(QuizPlayer::class)
        ->call('startQuiz', $quiz->ulid)
        ->set("answers.{$q1->id}", 'true')
        ->set("answers.{$q2->id}", 'pitta')
        ->call('submitQuiz')
        ->assertSee('Passed')
        ->assertSee('100%');
});

test('AssignmentPortal Livewire component allows submission and displays deadline', function (): void {
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'status' => EnrollmentStatus::Active,
    ]);

    $assignment = Assignment::create([
        'course_id' => $this->course->id,
        'title' => 'Daily Sadhana Journal Submission',
        'description' => 'Write reflections on 10 rounds of Nadi Shodhana.',
        'submission_types' => ['text', 'link'],
        'max_score' => 100,
        'due_at' => now()->addDays(3),
        'is_published' => true,
    ]);

    Livewire::actingAs($this->student)
        ->test(AssignmentPortal::class)
        ->assertSee('Daily Sadhana Journal Submission')
        ->call('selectAssignment', $assignment->ulid)
        ->set('textContent', 'Reflections on breath retention and calmness.')
        ->call('submitAssignment')
        ->assertSee('Assignment submitted successfully.');

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->first();
    expect($submission)->not->toBeNull();
    expect($submission->text_content)->toBe('Reflections on breath retention and calmness.');
});

test('PaymentHistory Livewire component allows student to pay pending installment', function (): void {
    $invoice = Invoice::create([
        'invoice_number' => 'INV-2026-0001',
        'financial_year' => '2026-2027',
        'sequence_number' => 1,
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batchA->id,
        'subtotal_amount' => 1000000,
        'discount_amount' => 0,
        'tax_amount' => 180000,
        'total_amount' => 1180000,
        'currency' => 'INR',
        'status' => InvoicePaymentStatus::Pending,
        'due_date' => now()->addDays(15),
        'issued_at' => now(),
        'billing_name' => $this->student->full_name,
        'billing_email' => $this->student->email,
    ]);

    $plan = InstallmentPlan::create([
        'invoice_id' => $invoice->id,
        'total_installments' => 2,
        'total_amount' => 1180000,
        'currency' => 'INR',
    ]);

    $inst1 = Installment::create([
        'installment_plan_id' => $plan->id,
        'invoice_id' => $invoice->id,
        'installment_number' => 1,
        'amount' => 590000,
        'currency' => 'INR',
        'due_date' => now()->addDays(5),
        'grace_date' => now()->addDays(10),
    ]);

    Installment::create([
        'installment_plan_id' => $plan->id,
        'invoice_id' => $invoice->id,
        'installment_number' => 2,
        'amount' => 590000,
        'currency' => 'INR',
        'due_date' => now()->addDays(35),
        'grace_date' => now()->addDays(40),
    ]);

    Livewire::actingAs($this->student)
        ->test(PaymentHistory::class)
        ->assertSee('INV-2026-0001')
        ->call('selectInstallmentToPay', $inst1->ulid)
        ->set('paymentMethod', 'stripe')
        ->call('processInstallmentPayment')
        ->assertSee('processed successfully');

    $inst1->refresh();
    expect($inst1->isPaid())->toBeTrue();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoicePaymentStatus::PartiallyPaid);
});

test('StudentProfile Livewire component updates emergency contact and requests data export', function (): void {
    Livewire::actingAs($this->student)
        ->test(StudentProfile::class)
        ->set('emergency_contact_name', 'Ramesh Sharma')
        ->set('emergency_contact_phone', '+91 9876543210')
        ->set('emergency_contact_relationship', 'Father')
        ->call('saveProfile')
        ->assertSee('Profile information updated successfully.');

    $this->student->refresh();
    expect($this->student->studentProfile->emergency_contact_name)->toBe('Ramesh Sharma');

    // Lodging data export request
    Livewire::actingAs($this->student)
        ->test(StudentProfile::class)
        ->call('requestDataExport')
        ->assertSee('Your data export request has been lodged');

    $exportReq = UserDeletionRequest::query()
        ->where('user_id', $this->student->id)
        ->where('reason', 'DATA_EXPORT')
        ->first();
    expect($exportReq)->not->toBeNull();
});
