<?php

declare(strict_types=1);

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\CourseTeacher;
use App\Domain\Communication\Models\BatchMessage;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\StudentProfile;
use App\Domain\Identity\Models\User;
use App\Domain\Learning\Models\CourseProgress;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Domain\Scheduling\Models\SessionTeacher;
use App\Domain\Wellbeing\Models\WellbeingConcern;
use App\Livewire\Moderator\ModeratorConsole;
use App\Livewire\Teacher\AssignmentGrading;
use App\Livewire\Teacher\AttendanceMarker;
use App\Livewire\Teacher\BatchMessenger;
use App\Livewire\Teacher\TeacherDashboard;
use App\Livewire\Teacher\WellbeingEscalation;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();

    $this->admin = User::factory()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
    $this->admin->assignRole('Admin');

    $this->moderator = User::factory()->create(['first_name' => 'Meera', 'last_name' => 'Moderator']);
    $this->moderator->assignRole('Moderator');

    $this->teacher1 = User::factory()->create(['first_name' => 'Swami', 'last_name' => 'Ananda']);
    $this->teacher1->assignRole('Teacher');

    $this->teacher2 = User::factory()->create(['first_name' => 'Acharya', 'last_name' => 'Vidya']);
    $this->teacher2->assignRole('Teacher');

    $this->student = User::factory()->create(['first_name' => 'Rahul', 'last_name' => 'Verma']);
    $this->student->assignRole('Student');

    $this->course = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'name' => 'Yoga Therapy Practitioner Certification',
        'slug' => 'yoga-therapy-practitioner',
        'type' => CourseType::LongTerm,
        'delivery_mode' => DeliveryMode::Hybrid,
        'status' => CourseStatus::Published,
        'fee_amount' => 2500000,
        'fee_currency' => 'INR',
        'duration_weeks' => 24,
    ]);

    // Batch 1 instructed by Teacher 1
    $this->batch1 = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cohort Alpha (Himalayan Center)',
        'delivery_mode' => DeliveryMode::Hybrid,
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
        'capacity' => 25,
        'status' => BatchStatus::Open,
    ]);

    // Batch 2 instructed by Teacher 2
    $this->batch2 = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Cohort Beta (Rishikesh Center)',
        'delivery_mode' => DeliveryMode::Hybrid,
        'start_date' => '2026-08-01',
        'end_date' => '2027-01-31',
        'capacity' => 25,
        'status' => BatchStatus::Open,
    ]);

    // Assign Teacher 1 to Batch 1 via CourseTeacher or SessionTeacher
    $this->session1 = Session::create([
        'batch_id' => $this->batch1->id,
        'title' => 'Asana Kriya & Anatomy Alignment',
        'slug' => 'asana-kriya-anatomy-alignment',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Scheduled,
    ]);
    SessionTeacher::create([
        'session_id' => $this->session1->id,
        'user_id' => $this->teacher1->id,
        'role' => 'lead',
    ]);

    // Assign Teacher 2 to Batch 2
    $this->session2 = Session::create([
        'batch_id' => $this->batch2->id,
        'title' => 'Pranayama Diagnostics',
        'slug' => 'pranayama-diagnostics',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Scheduled,
    ]);
    SessionTeacher::create([
        'session_id' => $this->session2->id,
        'user_id' => $this->teacher2->id,
        'role' => 'lead',
    ]);

    // Enroll student in Batch 1
    $this->enrollment1 = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch1->id,
        'status' => EnrollmentStatus::Active,
    ]);
});

test('ACCEPTANCE: A teacher cannot view another teachers batch roster', function (): void {
    // 1. Teacher 1 viewing Batch 1 (their assigned cohort) -> 200 OK
    $responseAllowed = $this->actingAs($this->teacher1)
        ->get(route('teacher.roster', ['batch' => $this->batch1->ulid]));
    $responseAllowed->assertStatus(200);
    $responseAllowed->assertSee('Cohort Alpha (Himalayan Center)');
    $responseAllowed->assertSee('Rahul Verma');

    // 2. Teacher 1 attempting to view Batch 2 (Teacher 2 cohort) -> 403 Forbidden!
    $responseForbidden = $this->actingAs($this->teacher1)
        ->get(route('teacher.roster', ['batch' => $this->batch2->ulid]));
    $responseForbidden->assertStatus(403);

    // 3. Teacher 2 attempting to view Batch 1 -> 403 Forbidden!
    $responseForbidden2 = $this->actingAs($this->teacher2)
        ->get(route('teacher.roster', ['batch' => $this->batch1->ulid]));
    $responseForbidden2->assertStatus(403);
});

test('ACCEPTANCE: Attendance edits after lock require a reason and are audited', function (): void {
    // Create an old session held 10 days ago (past the default 3-day edit window)
    $pastSession = Session::create([
        'batch_id' => $this->batch1->id,
        'title' => 'Ancient Hatha Session (Historical)',
        'slug' => 'ancient-hatha-session-historical',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10)->addHours(2),
        'timezone' => 'Asia/Kolkata',
        'status' => SessionStatus::Completed,
    ]);

    $attendance = SessionAttendance::create([
        'session_id' => $pastSession->id,
        'user_id' => $this->student->id,
        'status' => 'absent',
        'marked_by' => $this->teacher1->id,
    ]);

    // Verify it is locked
    expect($attendance->isLocked())->toBeTrue();

    // 1. Regular teacher attempting to edit locked attendance is denied
    Livewire::actingAs($this->teacher1)
        ->test(AttendanceMarker::class, ['session' => $pastSession->ulid])
        ->call('markStatus', $this->student->id, 'present')
        ->assertSee('Attendance is locked');

    // 2. Moderator attempting to edit without reason is prompted
    Livewire::actingAs($this->moderator)
        ->test(AttendanceMarker::class, ['session' => $pastSession->ulid])
        ->call('markStatus', $this->student->id, 'present')
        ->assertSee('Please specify a mandatory correction reason');

    // 3. Moderator submitting override with mandatory reason succeeds and logs audit
    Livewire::actingAs($this->moderator)
        ->test(AttendanceMarker::class, ['session' => $pastSession->ulid])
        ->set('correctingUserId', $this->student->id)
        ->set('pendingCorrectionStatus', 'present')
        ->set('correctionReason', 'Medical leave certificate verified by academic director.')
        ->call('submitLockedCorrection')
        ->assertSee('Attendance updated to Present.');

    $attendance->refresh();
    expect($attendance->status)->toBe('present');
    expect($attendance->correction_reason)->toBe('Medical leave certificate verified by academic director.');
    expect($attendance->edited_by)->toBe($this->moderator->id);

    // Verify spatie activity log was written with reason and performer
    $loggedActivity = Activity::query()
        ->where('log_name', 'attendance')
        ->where('causer_id', $this->moderator->id)
        ->latest()
        ->first();

    expect($loggedActivity)->not->toBeNull();
    expect($loggedActivity->description)->toContain('Locked attendance overridden with reason:');
    expect($loggedActivity->properties['reason'])->toBe('Medical leave certificate verified by academic director.');
});

test('ACCEPTANCE: Concern reports are invisible to teachers other than the reporters own submission receipt', function (): void {
    // Teacher 1 reports a sensitive wellbeing concern about a student
    $concern = WellbeingConcern::create([
        'reporter_id' => $this->teacher1->id,
        'student_id' => $this->student->id,
        'batch_id' => $this->batch1->id,
        'category' => 'mental_health',
        'urgency' => 'high',
        'description' => 'Student appeared deeply distressed and disclosed severe sleep deprivation and despair.',
        'status' => 'received',
    ]);

    // 1. Teacher 1 visiting concerns portal sees their receipt
    Livewire::actingAs($this->teacher1)
        ->test(WellbeingEscalation::class)
        ->assertSee('My Submitted Concern Receipts')
        ->assertSee('CONCERN-'.substr($concern->ulid, -8))
        ->assertSee('Mental health');

    // 2. Teacher 2 visiting concerns portal CANNOT see Teacher 1 concern report or receipt
    Livewire::actingAs($this->teacher2)
        ->test(WellbeingEscalation::class)
        ->assertDontSee('CONCERN-'.substr($concern->ulid, -8))
        ->assertDontSee('Student appeared deeply distressed');

    // 3. Moderator with concern.review CAN see all escalated concerns and review decrypted narrative
    Livewire::actingAs($this->moderator)
        ->test(WellbeingEscalation::class)
        ->assertSee('Wellbeing Review Queue')
        ->assertSee('CONCERN-'.substr($concern->ulid, -8))
        ->call('selectConcernForReview', $concern->ulid)
        ->assertSee('Student appeared deeply distressed and disclosed severe sleep deprivation and despair.')
        ->set('reviewStatus', 'in_progress')
        ->set('resolutionNotes', 'Assigned to senior Ayurveda & counseling mentor.')
        ->call('updateConcernReview')
        ->assertSee('Concern status and resolution notes updated.');

    $concern->refresh();
    expect($concern->status)->toBe('in_progress');
    expect($concern->assigned_to)->toBe($this->moderator->id);
    expect($concern->resolution_notes)->toBe('Assigned to senior Ayurveda & counseling mentor.');
});

test('Teacher Portal: Mobile-friendly one-tap attendance marking and bulk mark all present', function (): void {
    $student2 = User::factory()->create(['first_name' => 'Ananya', 'last_name' => 'Iyer']);
    Enrollment::create([
        'student_id' => $student2->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch1->id,
        'status' => EnrollmentStatus::Active,
    ]);

    // One-tap mark student 1 as Late
    Livewire::actingAs($this->teacher1)
        ->test(AttendanceMarker::class, ['session' => $this->session1->ulid])
        ->call('markStatus', $this->student->id, 'late')
        ->assertSee('Attendance updated to Late.');

    $att = SessionAttendance::query()->where('session_id', $this->session1->id)->where('user_id', $this->student->id)->first();
    expect($att)->not->toBeNull();
    expect($att->status)->toBe('late');

    // Bulk mark all present
    Livewire::actingAs($this->teacher1)
        ->test(AttendanceMarker::class, ['session' => $this->session1->ulid])
        ->call('markAllPresent')
        ->assertSee('All active students in this cohort marked as Present.');

    expect(SessionAttendance::query()->where('session_id', $this->session1->id)->where('user_id', $this->student->id)->first()->status)->toBe('present');
    expect(SessionAttendance::query()->where('session_id', $this->session1->id)->where('user_id', $student2->id)->first()->status)->toBe('present');
});

test('Teacher Portal: Review and grade assignment submissions with feedback', function (): void {
    $assignment = Assignment::create([
        'course_id' => $this->course->id,
        'batch_id' => $this->batch1->id,
        'title' => 'Clinical Case Study: Vata Imbalance',
        'description' => 'Document dietary and asana interventions for patient.',
        'submission_types' => ['text'],
        'max_score' => 50,
        'due_at' => now()->addDays(5),
        'is_published' => true,
    ]);

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $this->student->id,
        'submission_number' => 1,
        'text_content' => 'Prescribed daily warm sesame oil Abhyanga and Ashwagandha with milk.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    Livewire::actingAs($this->teacher1)
        ->test(AssignmentGrading::class)
        ->assertSee('Clinical Case Study')
        ->call('selectSubmission', $submission->ulid)
        ->assertSee('Prescribed daily warm sesame oil Abhyanga')
        ->set('score', 48)
        ->set('feedback', 'Excellent classical understanding of Vata pacification.')
        ->call('gradeSubmission')
        ->assertSee('Submission graded successfully (48 / 50 pts).');

    $submission->refresh();
    expect($submission->score)->toBe(48);
    expect($submission->status)->toBe('graded');
    expect($submission->graded_by)->toBe($this->teacher1->id);
});

test('Teacher Portal: Direct messaging restricted strictly to shared batches', function (): void {
    $unrelatedStudent = User::factory()->create(['first_name' => 'Stranger', 'last_name' => 'Student']);

    // 1. Messaging student in shared Batch 1 succeeds
    Livewire::actingAs($this->teacher1)
        ->test(BatchMessenger::class, ['batch' => $this->batch1->ulid, 'student' => $this->student->ulid])
        ->set('messageText', 'Please bring your yoga strap to tomorrows class.')
        ->call('sendMessage')
        ->assertSee('Message sent to student.');

    $msg = BatchMessage::query()->where('sender_id', $this->teacher1->id)->where('recipient_id', $this->student->id)->first();
    expect($msg)->not->toBeNull();
    expect($msg->message)->toBe('Please bring your yoga strap to tomorrows class.');

    // 2. Attempting to message an unrelated student not in this batch is rejected
    Livewire::actingAs($this->teacher1)
        ->test(BatchMessenger::class, ['batch' => $this->batch1->ulid, 'student' => $unrelatedStudent->ulid])
        ->set('messageText', 'Unauthorized message')
        ->call('sendMessage')
        ->assertSee('Security policy violation: You can only message students in shared batches.');
});

test('Teacher Portal: Participation and at-risk indicators detect low attendance', function (): void {
    // Set student progress to 60% attendance (< 75% threshold)
    CourseProgress::create([
        'user_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch1->id,
        'attendance_percentage' => 60.0,
        'lessons_completed' => 1,
        'total_required_lessons' => 4,
        'overall_percentage' => 40.0,
    ]);

    Livewire::actingAs($this->teacher1)
        ->test(TeacherDashboard::class)
        ->assertSee('Participation &amp; At-Risk Alert', false)
        ->assertSee('Rahul Verma')
        ->assertSee('Low Attendance (60%)');
});

test('Moderator Console: Offline registration and enrollment approval queue', function (): void {
    // 1. Offline student registration
    Livewire::actingAs($this->moderator)
        ->test(ModeratorConsole::class)
        ->set('activeTab', 'offline_reg')
        ->set('regFirstName', 'Devendra')
        ->set('regLastName', 'Shastri')
        ->set('regEmail', 'devendra@example.com')
        ->set('regBatchId', (string) $this->batch1->id)
        ->call('registerStudentOffline')
        ->assertSee('successfully registered offline');

    $registeredStudent = User::query()->where('email', 'devendra@example.com')->first();
    expect($registeredStudent)->not->toBeNull();

    // 2. Reviewing and approving pending online enrollment
    $newStudent = User::factory()->create(['first_name' => 'Kavita', 'last_name' => 'Patel']);
    $pendingEnrollment = Enrollment::create([
        'student_id' => $newStudent->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch1->id,
        'status' => EnrollmentStatus::Requested,
    ]);

    Livewire::actingAs($this->moderator)
        ->test(ModeratorConsole::class)
        ->assertSee('Kavita Patel')
        ->call('approveEnrollment', $pendingEnrollment->ulid)
        ->assertSee('has been approved');

    $pendingEnrollment->refresh();
    expect($pendingEnrollment->status)->toBe(EnrollmentStatus::Approved);
});

test('Moderator Console: Student lookup redacts confidential health data unless permitted', function (): void {
    StudentProfile::create([
        'user_id' => $this->student->id,
        'emergency_contact_name' => 'Sunita Verma',
        'emergency_contact_phone' => '+91 9988776655',
        'medical_conditions' => 'Chronic lower back herniation L4-L5',
        'allergies' => 'Sesame seed allergy',
    ]);

    // 1. Moderator without 'student.health.view' sees redacted notice
    $moderatorWithoutHealthView = User::factory()->create();
    $moderatorWithoutHealthView->assignRole('Moderator'); // does not have student.health.view

    Livewire::actingAs($moderatorWithoutHealthView)
        ->test(ModeratorConsole::class)
        ->set('activeTab', 'lookup')
        ->set('searchStudent', 'Rahul Verma')
        ->assertSee('Rahul Verma')
        ->assertSee('[REDACTED]')
        ->assertDontSee('Sunita Verma')
        ->assertDontSee('Chronic lower back herniation');

    // 2. Admin with 'student.health.view' sees decrypted health declarations
    Livewire::actingAs($this->admin)
        ->test(ModeratorConsole::class)
        ->set('activeTab', 'lookup')
        ->set('searchStudent', 'Rahul Verma')
        ->assertSee('Sunita Verma')
        ->assertSee('Chronic lower back herniation L4-L5')
        ->assertSee('Sesame seed allergy');
});
