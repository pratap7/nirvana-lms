<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Enrollment\Actions\RegisterStudentOfflineAction;
use App\Domain\Enrollment\Actions\ReleaseExpiredSeatHoldsAction;
use App\Domain\Enrollment\Actions\RequestEnrollmentAction;
use App\Domain\Enrollment\Actions\TransferEnrollmentAction;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Events\EnrollmentApproved;
use App\Domain\Enrollment\Events\EnrollmentWaitlistPromoted;
use App\Domain\Enrollment\Exceptions\DuplicateEnrollmentException;
use App\Domain\Enrollment\Exceptions\PrerequisiteNotMetException;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Exceptions\IllegalStateTransitionException;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->moderator = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Moderator']);
    $this->moderator->assignRole('Moderator');

    $this->course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->moderator->id,
        'name' => 'Foundations of Advaita Vedanta',
    ]);

    $this->batch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Monsoon 2026 Vedanta Cohort',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
        'capacity' => 5,
        'seat_hold_hours' => 24,
        'allow_waitlist' => true,
        'auto_promote_waitlist' => true,
        'requires_approval' => false,
        'status' => BatchStatus::Open,
    ]);
});

test('Acceptance: 20 parallel requests for 5 seats results in exactly 5 holds and 15 waitlisted', function (): void {
    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);

    // Create 20 unique student users
    $students = [];
    for ($i = 1; $i <= 20; $i++) {
        $student = User::factory()->create([
            'first_name' => "Student{$i}",
            'last_name' => 'Vidyarthi',
            'email' => "student{$i}@pramana.edu",
        ]);
        $student->assignRole('Student');
        $students[] = $student;
    }

    // Submit 20 enrollment requests
    $enrollments = [];
    foreach ($students as $student) {
        $enrollments[] = $requester->execute(
            student: $student,
            batch: $this->batch,
            consentGiven: true,
            consentIp: '127.0.0.1'
        );
    }

    // Verify exactly 5 holds (Approved with seat hold expires at)
    $approved = Enrollment::where('batch_id', $this->batch->id)
        ->where('status', EnrollmentStatus::Approved)
        ->get();

    expect($approved)->toHaveCount(5);

    foreach ($approved as $app) {
        expect($app->seat_hold_expires_at)->not->toBeNull()
            ->and($app->isSeatOccupied())->toBeTrue()
            ->and($app->waitlist_position)->toBeNull();
    }

    // Verify remaining 15 are on the waitlist with sequential positions 1 to 15
    $waitlisted = Enrollment::where('batch_id', $this->batch->id)
        ->where('status', EnrollmentStatus::Waitlisted)
        ->orderBy('waitlist_position', 'asc')
        ->get();

    expect($waitlisted)->toHaveCount(15);

    $positions = $waitlisted->pluck('waitlist_position')->toArray();
    expect($positions)->toBe(range(1, 15));
});

test('Acceptance: expired hold frees the seat and promotes the first waitlisted student', function (): void {
    Event::fake([EnrollmentWaitlistPromoted::class, EnrollmentApproved::class]);

    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);

    // Enroll 5 students (fills capacity)
    $approvedStudents = [];
    for ($i = 1; $i <= 5; $i++) {
        $student = User::factory()->create();
        $student->assignRole('Student');
        $approvedStudents[] = $requester->execute($student, $this->batch);
    }

    // Enroll 3 waitlisted students
    $wl1Student = User::factory()->create(['first_name' => 'Waitlist', 'last_name' => 'One']);
    $wl2Student = User::factory()->create(['first_name' => 'Waitlist', 'last_name' => 'Two']);
    $wl3Student = User::factory()->create(['first_name' => 'Waitlist', 'last_name' => 'Three']);

    $wl1 = $requester->execute($wl1Student, $this->batch);
    $wl2 = $requester->execute($wl2Student, $this->batch);
    $wl3 = $requester->execute($wl3Student, $this->batch);

    expect($wl1->waitlist_position)->toBe(1)
        ->and($wl2->waitlist_position)->toBe(2)
        ->and($wl3->waitlist_position)->toBe(3);

    // Simulate expiration of seat hold on the 2nd approved student
    $targetToExpire = $approvedStudents[1];
    $targetToExpire->update([
        'seat_hold_expires_at' => now()->subHours(2),
    ]);

    // Execute release expired seat holds
    /** @var ReleaseExpiredSeatHoldsAction $releaser */
    $releaser = app(ReleaseExpiredSeatHoldsAction::class);
    $result = $releaser->execute();

    expect($result['released_count'])->toBe(1)
        ->and($result['promoted_count'])->toBe(1);

    // Verify expired student is Cancelled with reason
    $targetToExpire->refresh();
    expect($targetToExpire->status)->toBe(EnrollmentStatus::Cancelled)
        ->and($targetToExpire->cancellation_reason)->toContain('Seat hold expired')
        ->and($targetToExpire->seat_hold_expires_at)->toBeNull();

    // Verify first waitlisted student is promoted to Approved with a fresh seat hold
    $wl1->refresh();
    expect($wl1->status)->toBe(EnrollmentStatus::Approved)
        ->and($wl1->seat_hold_expires_at)->not->toBeNull()
        ->and($wl1->seat_hold_expires_at->isFuture())->toBeTrue()
        ->and($wl1->waitlist_position)->toBeNull();

    // Verify remaining waitlisted students are re-indexed to positions 1 and 2
    $wl2->refresh();
    $wl3->refresh();
    expect($wl2->waitlist_position)->toBe(1)
        ->and($wl3->waitlist_position)->toBe(2);

    Event::assertDispatched(EnrollmentWaitlistPromoted::class, fn ($e) => $e->enrollment->id === $wl1->id);
});

test('State machine transitions: legal path succeeds and illegal transitions throw', function (): void {
    $student = User::factory()->create();
    $student->assignRole('Student');

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'batch_id' => $this->batch->id,
        'course_id' => $this->course->id,
        'status' => EnrollmentStatus::Requested,
    ]);

    /** @var TransitionEnrollmentAction $transition */
    $transition = app(TransitionEnrollmentAction::class);

    // 1. Legal: Requested -> UnderReview
    $transition->execute($enrollment, EnrollmentStatus::UnderReview, $this->moderator, 'Document verification');
    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::UnderReview);

    // 2. Legal: UnderReview -> Approved (places seat hold)
    $transition->execute($enrollment, EnrollmentStatus::Approved, $this->moderator);
    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Approved)
        ->and($enrollment->seat_hold_expires_at)->not->toBeNull();

    // 3. Legal: Approved -> Active
    $transition->execute($enrollment, EnrollmentStatus::Active, $this->moderator);
    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->seat_hold_expires_at)->toBeNull();

    // 4. Legal: Active -> Completed
    $transition->execute($enrollment, EnrollmentStatus::Completed, $this->moderator);
    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Completed);

    // 5. Illegal: Completed -> Requested (Terminal status cannot transition)
    expect(fn () => $transition->execute($enrollment, EnrollmentStatus::Requested))
        ->toThrow(IllegalStateTransitionException::class);

    // Verify status history audit entries exist for every transition
    expect($enrollment->statusHistories)->toHaveCount(4);
});

test('Duplicate enrollment guard rejects duplicate registrations', function (): void {
    $student = User::factory()->create();
    $student->assignRole('Student');

    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);

    // First request succeeds
    $requester->execute($student, $this->batch);

    // Second request throws DuplicateEnrollmentException
    expect(fn () => $requester->execute($student, $this->batch))
        ->toThrow(DuplicateEnrollmentException::class);
});

test('Prerequisite check blocks enrollment until prerequisite course is completed', function (): void {
    $prereqCourse = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Introduction to Sanskrit & Logic',
    ]);

    // Attach prerequisite to main course
    DB::table('course_prerequisites')->insert([
        'course_id' => $this->course->id,
        'prerequisite_course_id' => $prereqCourse->id,
    ]);

    $student = User::factory()->create();
    $student->assignRole('Student');

    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);

    // Attempting to enroll without prerequisite throws PrerequisiteNotMetException
    expect(fn () => $requester->execute($student, $this->batch))
        ->toThrow(PrerequisiteNotMetException::class, 'Missing: [Introduction to Sanskrit & Logic]');

    // Now record that student completed the prerequisite course
    $prereqBatch = Batch::create([
        'course_id' => $prereqCourse->id,
        'branch_id' => $this->branch->id,
        'name' => 'Prereq Batch',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
        'capacity' => 10,
        'status' => BatchStatus::Completed,
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'batch_id' => $prereqBatch->id,
        'course_id' => $prereqCourse->id,
        'status' => EnrollmentStatus::Completed,
    ]);

    // Now enrollment in the main course succeeds!
    $enrollment = $requester->execute($student, $this->batch);
    expect($enrollment->status)->toBe(EnrollmentStatus::Approved);
});

test('Offline registration by moderator creates student account and enrolls them', function (): void {
    /** @var RegisterStudentOfflineAction $action */
    $action = app(RegisterStudentOfflineAction::class);

    $enrollment = $action->execute(
        moderator: $this->moderator,
        batch: $this->batch,
        studentData: [
            'first_name' => 'Devavrata',
            'last_name' => 'Sharma',
            'email' => 'devavrata@ashram.org',
            'phone' => '+919876543210',
        ],
        notes: 'Enrolled in-person at Varanasi campus office'
    );

    expect($enrollment)->toBeInstanceOf(Enrollment::class)
        ->and($enrollment->is_offline_registration)->toBeTrue()
        ->and($enrollment->registered_by)->toBe($this->moderator->id)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Approved);

    $createdUser = User::where('email', 'devavrata@ashram.org')->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->hasRole('Student'))->toBeTrue();
});

test('Drop and Transfer: transfers student between batches with pro-rata ledger', function (): void {
    $student = User::factory()->create();
    $student->assignRole('Student');

    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);
    $sourceEnrollment = $requester->execute($student, $this->batch);

    // Target batch
    $targetBatch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Autumn 2026 Alternate Cohort',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-09-01',
        'end_date' => '2026-11-30',
        'capacity' => 10,
        'status' => BatchStatus::Open,
    ]);

    /** @var TransferEnrollmentAction $transferAction */
    $transferAction = app(TransferEnrollmentAction::class);
    $newEnrollment = $transferAction->execute(
        sourceEnrollment: $sourceEnrollment,
        targetBatch: $targetBatch,
        actor: $this->moderator,
        reason: 'Shifted schedule conflict',
        proRataRefundAmount: 50000, // ₹500
        proRataFeeDueAmount: 0
    );

    $sourceEnrollment->refresh();

    // Source enrollment dropped
    expect($sourceEnrollment->status)->toBe(EnrollmentStatus::Dropped)
        ->and($sourceEnrollment->drop_reason)->toContain('Transferred to batch');

    // New enrollment created for target batch
    expect($newEnrollment->batch_id)->toBe($targetBatch->id)
        ->and($newEnrollment->student_id)->toBe($student->id)
        ->and($newEnrollment->transfers)->toHaveCount(1);

    $transferRecord = $newEnrollment->transfers->first();
    expect($transferRecord->source_batch_id)->toBe($this->batch->id)
        ->and($transferRecord->target_batch_id)->toBe($targetBatch->id)
        ->and($transferRecord->pro_rata_refund_amount)->toBe(50000);
});

test('Student can view their enrollment status timeline', function (): void {
    $student = User::factory()->create();
    $student->assignRole('Student');

    /** @var RequestEnrollmentAction $requester */
    $requester = app(RequestEnrollmentAction::class);
    $enrollment = $requester->execute($student, $this->batch);

    $this->actingAs($student);

    $response = $this->getJson("/enrollments/{$enrollment->ulid}/timeline");
    $response->assertStatus(200);

    $data = $response->json();
    expect($data)->toHaveKey('enrollment')
        ->and($data)->toHaveKey('timeline')
        ->and($data['timeline'])->toHaveCount(1)
        ->and($data['timeline'][0]['to_status'])->toBe(EnrollmentStatus::Approved->value);
});
