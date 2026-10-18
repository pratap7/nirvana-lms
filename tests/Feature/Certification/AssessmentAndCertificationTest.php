<?php

declare(strict_types=1);

use App\Domain\Assessment\Enums\AssessmentType;
use App\Domain\Assessment\Models\Assignment;
use App\Domain\Assessment\Models\AssignmentSubmission;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Module;
use App\Domain\Certification\Actions\IssueCourseCertificateAction;
use App\Domain\Certification\Actions\ReissueCertificateAction;
use App\Domain\Certification\Actions\RevokeCertificateAction;
use App\Domain\Certification\Enums\CertificateStatus;
use App\Domain\Certification\Jobs\GenerateCertificatePdfJob;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Certification\Models\CertificateTemplate;
use App\Domain\Certification\Services\CompletionEvaluator;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Livewire\Certification\CertificateVerification;
use App\Livewire\Certification\StudentCompletionChecklist;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->teacher = User::factory()->create(['first_name' => 'Swami', 'last_name' => 'Anand']);
    $this->teacher->assignRole('Teacher');

    $this->student = User::factory()->create(['first_name' => 'Aarav', 'last_name' => 'Sharma']);
    $this->student->assignRole('Student');

    $this->moderator = User::factory()->create(['first_name' => 'Moderator', 'last_name' => 'Lead']);
    $this->moderator->assignRole('Moderator');

    $this->course = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'name' => 'Hatha Yoga Teacher Training 200h',
        'slug' => 'hatha-yoga-ttc-200h',
        'type' => CourseType::LongTerm,
        'delivery_mode' => DeliveryMode::Hybrid,
        'status' => CourseStatus::Published,
        'fee_amount' => 5000000,
        'fee_currency' => 'INR',
        'duration_weeks' => 16,
    ]);

    $this->batch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Batch 2026-A',
        'delivery_mode' => DeliveryMode::Hybrid,
        'start_date' => '2026-06-01',
        'end_date' => '2026-10-01',
        'capacity' => 25,
        'status' => BatchStatus::Open,
    ]);

    $this->enrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
        'requested_at' => now(),
        'approved_at' => now(),
        'enrolled_at' => now(),
    ]);

    $this->module = Module::create([
        'course_id' => $this->course->id,
        'name' => 'Module 1: Asana Alignment & Pranayama',
        'sort_order' => 1,
    ]);
});

test('assessment types: quiz, assignment, practical with rubric and optional video, final exam, teacher evaluation', function (): void {
    // 1. Practical assessment requiring video and rubric scoring
    $practical = Assignment::create([
        'course_id' => $this->course->id,
        'module_id' => $this->module->id,
        'created_by' => $this->teacher->id,
        'title' => 'Surya Namaskar B Teaching Demonstration (Practical)',
        'description' => 'Record a 5-minute video demonstrating alignment and breath cues for Surya Namaskar B.',
        'assessment_type' => AssessmentType::Practical,
        'submission_types' => ['text', 'file', 'link'],
        'due_at' => now()->addDays(7),
        'requires_video' => true,
        'max_score' => 100,
        'rubric' => [
            'alignment' => ['max' => 40, 'title' => 'Anatomical Alignment'],
            'breath_cueing' => ['max' => 30, 'title' => 'Pranayama Synchronization'],
            'voice_modulation' => ['max' => 30, 'title' => 'Voice Clarity & Presence'],
        ],
    ]);

    expect($practical->assessment_type)->toBe(AssessmentType::Practical)
        ->and($practical->requires_video)->toBeTrue();

    // Student submits practical video link
    $submission = AssignmentSubmission::create([
        'assignment_id' => $practical->id,
        'user_id' => $this->student->id,
        'batch_id' => $this->batch->id,
        'video_url' => 'https://stream.pramana.org/submissions/aarav-surya-namaskar.mp4',
        'submitted_text' => 'Here is my Surya Namaskar B practical demonstration video submission.',
        'submitted_at' => now(),
        'status' => 'submitted',
    ]);

    expect($submission->video_url)->toBe('https://stream.pramana.org/submissions/aarav-surya-namaskar.mp4');

    // Teacher grades against the rubric
    $submission->update([
        'rubric_scores' => [
            'alignment' => 38,
            'breath_cueing' => 28,
            'voice_modulation' => 29,
        ],
        'score' => 95,
        'status' => 'graded',
        'graded_by' => $this->teacher->id,
        'graded_at' => now(),
        'teacher_feedback' => 'Exceptional alignment cues and calm voice.',
    ]);

    $submission->refresh();
    expect($submission->rubric_scores)->toBeArray()
        ->and($submission->rubric_scores['alignment'])->toBe(38)
        ->and($submission->score)->toBe(95);

    // 2. Final Exam assessment type
    $finalExam = Assignment::create([
        'course_id' => $this->course->id,
        'module_id' => $this->module->id,
        'created_by' => $this->teacher->id,
        'title' => 'Comprehensive Yoga Philosophy Final Exam',
        'assessment_type' => AssessmentType::FinalExam,
        'submission_types' => ['text', 'file'],
        'due_at' => now()->addDays(7),
        'max_score' => 100,
    ]);
    expect($finalExam->assessment_type)->toBe(AssessmentType::FinalExam);

    // 3. Teacher Evaluation assessment type
    $teacherEval = Assignment::create([
        'course_id' => $this->course->id,
        'module_id' => $this->module->id,
        'created_by' => $this->teacher->id,
        'title' => 'Lead Teacher Final Practicum Evaluation',
        'assessment_type' => AssessmentType::TeacherEvaluation,
        'submission_types' => ['text'],
        'due_at' => now()->addDays(7),
        'max_score' => 50,
    ]);
    expect($teacherEval->assessment_type)->toBe(AssessmentType::TeacherEvaluation);
});

test('completion evaluator: empty rules are valid and pass automatically (e.g. workshop needing nothing)', function (): void {
    $workshopCourse = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'name' => 'Weekend Sound Bath Workshop',
        'slug' => 'weekend-sound-bath-workshop',
        'type' => CourseType::ShortTerm,
        'delivery_mode' => DeliveryMode::Offline,
        'status' => CourseStatus::Published,
        'fee_amount' => 200000,
        'fee_currency' => 'INR',
        'duration_weeks' => 1,
    ]);

    // No completion rules defined (empty array)
    $evaluator = app(CompletionEvaluator::class);
    $evaluation = $evaluator->evaluate($workshopCourse, $this->student);

    expect($evaluation->is_passed)->toBeTrue()
        ->and($evaluation->checklist_results)->toBeArray()
        ->and($evaluation->checklist_results[0]['requirement'])->toBe('Open Completion')
        ->and($evaluation->checklist_results[0]['passed'])->toBeTrue()
        ->and($evaluation->rules_snapshot)->toBeArray();
});

test('completion evaluator: returns per-requirement pass/fail with clear reasons and supports manual approval step', function (): void {
    // Set up course completion rules
    $this->course->update([
        'completion_rules' => [
            'min_attendance_percent' => 80,
            'require_practicals' => true,
            'require_manual_approval' => true,
        ],
    ]);

    // Create a held session with attendance
    $session = Session::create([
        'batch_id' => $this->batch->id,
        'course_id' => $this->course->id,
        'title' => 'Session 1: Pranayama Foundations',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subDays(5)->addHours(2),
        'status' => SessionStatus::Completed,
    ]);

    SessionAttendance::create([
        'session_id' => $session->id,
        'user_id' => $this->student->id,
        'status' => 'present',
    ]);

    // Create practical assignment (not submitted yet)
    $practical = Assignment::create([
        'course_id' => $this->course->id,
        'module_id' => $this->module->id,
        'created_by' => $this->teacher->id,
        'title' => 'Asana Demo',
        'assessment_type' => AssessmentType::Practical,
        'submission_types' => ['text', 'file', 'link'],
        'due_at' => now()->addDays(7),
        'max_score' => 100,
    ]);

    $evaluator = app(CompletionEvaluator::class);
    $evaluation = $evaluator->evaluate($this->course, $this->student, $this->batch);

    expect($evaluation->is_passed)->toBeFalse()
        ->and($evaluation->requires_manual_approval)->toBeTrue();

    // Check checklist results
    $results = collect($evaluation->checklist_results)->keyBy('type');

    expect($results->has('attendance'))->toBeTrue()
        ->and($results['attendance']['passed'])->toBeTrue()
        ->and($results['attendance']['reason'])->toContain('100% achieved')
        ->and($results['practicals']['passed'])->toBeFalse()
        ->and($results['practicals']['reason'])->toContain('Pending submission or grading')
        ->and($results['manual_approval']['passed'])->toBeFalse()
        ->and($results['manual_approval']['reason'])->toContain('Pending approval');

    // Submit and grade the practical assignment
    AssignmentSubmission::create([
        'assignment_id' => $practical->id,
        'user_id' => $this->student->id,
        'batch_id' => $this->batch->id,
        'score' => 88,
        'status' => 'graded',
        'graded_by' => $this->teacher->id,
        'graded_at' => now(),
        'submitted_at' => now(),
    ]);

    // Re-evaluate
    $evaluation = $evaluator->evaluate($this->course, $this->student, $this->batch);
    $results = collect($evaluation->checklist_results)->keyBy('type');
    expect($results['practicals']['passed'])->toBeTrue();

    // Manual approval step
    expect($evaluation->is_passed)->toBeFalse(); // Still waiting for moderator approval
    $evaluator->approveManually($evaluation, $this->moderator, 'All competencies demonstrated excellently.');

    $evaluation->refresh();
    expect($evaluation->is_manually_approved)->toBeTrue()
        ->and($evaluation->is_passed)->toBeTrue()
        ->and($evaluation->manual_approved_by)->toBe($this->moderator->id);
});

test('acceptance criteria 1: changing a course rules affects only future evaluations', function (): void {
    // 1. Initial rules: lenient (only min_attendance_percent: 50)
    $this->course->update([
        'completion_rules' => [
            'min_attendance_percent' => 50,
        ],
    ]);

    $session = Session::create([
        'batch_id' => $this->batch->id,
        'course_id' => $this->course->id,
        'title' => 'Intro Session',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
        'status' => SessionStatus::Completed,
    ]);
    SessionAttendance::create([
        'session_id' => $session->id,
        'user_id' => $this->student->id,
        'status' => 'present',
    ]);

    $evaluator = app(CompletionEvaluator::class);
    $evaluation = $evaluator->evaluate($this->course, $this->student, $this->batch);
    expect($evaluation->is_passed)->toBeTrue();

    // Issue certificate under this evaluation
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    expect($certificate->rules_snapshot)->toBeArray()
        ->and($certificate->rules_snapshot['min_attendance_percent'])->toBe(50);

    // 2. Later, admin updates course rules to much stricter requirements (min_attendance 100% and min_quiz 90%)
    $this->course->update([
        'completion_rules' => [
            'min_attendance_percent' => 100,
            'min_quiz_average' => 90,
            'require_practicals' => true,
        ],
    ]);

    // The past evaluation snapshot and certificate rules snapshot MUST REMAIN UNTOUCHED
    $evaluation->refresh();
    $certificate->refresh();

    expect($evaluation->rules_snapshot['min_attendance_percent'])->toBe(50)
        ->and($evaluation->is_passed)->toBeTrue()
        ->and($certificate->rules_snapshot['min_attendance_percent'])->toBe(50)
        ->and(isset($certificate->rules_snapshot['require_practicals']))->toBeFalse();
});

test('certificate issuance: human-readable number, random public token, QR code SVG, queued PDF generation', function (): void {
    Queue::fake();

    // Empty rules -> auto-pass
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    // Format: YOG-YYYY-000123
    $year = now()->year;
    expect($certificate->certificate_number)->toMatch('/^YOG-'.$year.'-\d{6}$/')
        ->and($certificate->public_token)->toStartWith('cert_')
        ->and($certificate->status)->toBe(CertificateStatus::Valid)
        ->and($certificate->qr_code_svg)->toContain('<svg')
        ->and($certificate->issued_at)->not->toBeNull();

    // Dispatched queued PDF job
    Queue::assertPushed(GenerateCertificatePdfJob::class, function ($job) use ($certificate) {
        return $job->certificate->id === $certificate->id;
    });
});

test('acceptance criteria 2: revoked certificate shows as revoked on public page immediately', function (): void {
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    // Check public page initially shows VALID
    Livewire::test(CertificateVerification::class, ['publicToken' => $certificate->public_token])
        ->assertStatus(200)
        ->assertSee('Aarav Sharma')
        ->assertSee('Hatha Yoga Teacher Training 200h')
        ->assertSee('VALID');

    // Revoke certificate immediately with reason
    $revokeAction = app(RevokeCertificateAction::class);
    $revokeAction->execute($certificate, 'Academic integrity violation discovered during audit.', $this->moderator);

    $certificate->refresh();
    expect($certificate->status)->toBe(CertificateStatus::Revoked)
        ->and($certificate->revocation_reason)->toBe('Academic integrity violation discovered during audit.')
        ->and($certificate->revoked_by)->toBe($this->moderator->id);

    // Public verification page immediately reflects REVOKED status and shows revocation notice
    Livewire::test(CertificateVerification::class, ['publicToken' => $certificate->public_token])
        ->assertStatus(200)
        ->assertSee('REVOKED')
        ->assertSee('This certificate was revoked on')
        ->assertSee('Academic integrity violation discovered during audit.');
});

test('reissue certificate: creates new certificate, bidirectionally links old and new, marks old reissued', function (): void {
    $issueAction = app(IssueCourseCertificateAction::class);
    $oldCertificate = $issueAction->execute($this->course, $this->student, $this->batch);

    $reissueAction = app(ReissueCertificateAction::class);
    $newCertificate = $reissueAction->execute($oldCertificate, 'Correction of student legal surname spelling.', $this->moderator);

    $oldCertificate->refresh();
    $newCertificate->refresh();

    // Old certificate is marked Reissued and points to new
    expect($oldCertificate->status)->toBe(CertificateStatus::Reissued)
        ->and($oldCertificate->reissued_certificate_id)->toBe($newCertificate->id)
        ->and($oldCertificate->revocation_reason)->toContain('Correction of student legal surname');

    // New certificate is Valid and points back to original
    expect($newCertificate->status)->toBe(CertificateStatus::Valid)
        ->and($newCertificate->original_certificate_id)->toBe($oldCertificate->id)
        ->and($newCertificate->certificate_number)->not->toBe($oldCertificate->certificate_number)
        ->and($newCertificate->public_token)->not->toBe($oldCertificate->public_token);

    // Public verification page of old certificate shows REISSUED and links to replacement
    Livewire::test(CertificateVerification::class, ['publicToken' => $oldCertificate->public_token])
        ->assertStatus(200)
        ->assertSee('REISSUED')
        ->assertSee('Certificate Superseded')
        ->assertSee($newCertificate->public_token);

    // Public verification page of new certificate shows VALID
    Livewire::test(CertificateVerification::class, ['publicToken' => $newCertificate->public_token])
        ->assertStatus(200)
        ->assertSee('VALID')
        ->assertSee('Reissued Certificate');
});

test('public verification page: rate-limited and shows ONLY name, course, issue date, and status', function (): void {
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    $response = $this->get(route('certificates.verify', ['publicToken' => $certificate->public_token]));
    $response->assertStatus(200)
        ->assertSee('Aarav Sharma')
        ->assertSee('Hatha Yoga Teacher Training 200h')
        ->assertSee($certificate->issued_at->format('F d, Y'))
        ->assertSee('VALID');

    // Ensure private student data like student email, phone, user_id are NOT exposed on public verification
    $response->assertDontSee($this->student->email);
});

test('public verification rate limiting throttles excessive requests', function (): void {
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    // Check rate limiting middleware throttle:60,1
    $url = route('certificates.verify', ['publicToken' => $certificate->public_token]);
    $response = $this->get($url);
    $response->assertStatus(200);
});

test('open badges v2 JSON endpoint and LinkedIn add-to-profile URL generation', function (): void {
    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    // Open Badges JSON
    $badgeResponse = $this->get(route('certificates.badge', ['publicToken' => $certificate->public_token]));
    $badgeResponse->assertStatus(200)
        ->assertJsonStructure([
            '@context',
            'id',
            'type',
            'recipient' => ['type', 'hashed', 'identity'],
            'badge' => ['name', 'description', 'image', 'criteria', 'issuer'],
            'issuedOn',
            'verification' => ['type', 'url'],
        ]);

    $badgeData = $badgeResponse->json();
    expect($badgeData['type'])->toBe('Assertion')
        ->and($badgeData['badge']['name'])->toBe($this->course->name)
        ->and($badgeData['verification']['url'])->toBe($certificate->getVerificationUrl());

    // LinkedIn URL
    $linkedInUrl = $certificate->getLinkedInShareUrl();
    expect($linkedInUrl)->toContain('linkedin.com/profile/add')
        ->and($linkedInUrl)->toContain(urlencode($this->course->name))
        ->and($linkedInUrl)->toContain(urlencode($certificate->certificate_number))
        ->and($linkedInUrl)->toContain(urlencode($certificate->getVerificationUrl()));
});

test('certificate template: supports institute branding and drag-and-drop field placement', function (): void {
    $template = CertificateTemplate::create([
        'name' => 'Gold Border Traditional Vedic Template',
        'slug' => 'gold-border-traditional-vedic',
        'course_id' => $this->course->id,
        'created_by' => $this->teacher->id,
        'is_default' => true,
        'branding' => [
            'institute_name' => 'Pramana Institute of Yogic Sciences',
            'logo_url' => 'https://pramana.org/images/logo.png',
            'signatory_name' => 'Swami Niranjananda Saraswati',
            'signatory_title' => 'Head of Academic Board',
        ],
        'fields' => [
            ['id' => 'recipient_name', 'label' => 'Student Name', 'x' => 50, 'y' => 45, 'font_size' => 28],
            ['id' => 'course_name', 'label' => 'Course Title', 'x' => 50, 'y' => 55, 'font_size' => 22],
            ['id' => 'certificate_number', 'label' => 'Cert ID', 'x' => 20, 'y' => 85, 'font_size' => 12],
            ['id' => 'qr_code', 'label' => 'Verification QR', 'x' => 80, 'y' => 85, 'width' => 90],
        ],
    ]);

    expect($template->branding['institute_name'])->toBe('Pramana Institute of Yogic Sciences')
        ->and($template->fields)->toBeArray()
        ->and(count($template->fields))->toBe(4);
});

test('PDF generation queued job produces stored PDF file', function (): void {
    Storage::fake('public');

    $issueAction = app(IssueCourseCertificateAction::class);
    $certificate = $issueAction->execute($this->course, $this->student, $this->batch);

    // Run the job synchronously
    $job = new GenerateCertificatePdfJob($certificate);
    $job->handle();

    $certificate->refresh();
    expect($certificate->pdf_path)->not->toBeNull();
    Storage::disk('public')->assertExists($certificate->pdf_path);
});

test('student checklist livewire component displays requirements and allows claiming certificate', function (): void {
    $this->actingAs($this->student);

    // Empty rules -> student sees passed checklist and can claim certificate
    Livewire::test(StudentCompletionChecklist::class, [
        'courseSlug' => $this->course->slug,
        'batchId' => $this->batch->id,
    ])
        ->assertStatus(200)
        ->assertSee('Hatha Yoga Teacher Training 200h')
        ->assertSee('Completion Criteria Satisfied')
        ->call('claimCertificate')
        ->assertDispatched('certificate-claimed');

    $certificate = Certificate::where('user_id', $this->student->id)
        ->where('course_id', $this->course->id)
        ->first();

    expect($certificate)->not->toBeNull()
        ->and($certificate->status)->toBe(CertificateStatus::Valid);
});
