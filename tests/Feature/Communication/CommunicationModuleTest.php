<?php

declare(strict_types=1);

use App\Domain\Assessment\Models\Assignment;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Enums\CourseType;
use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Communication\Actions\FlagBatchMessageAction;
use App\Domain\Communication\Actions\ReviewFlaggedMessageAction;
use App\Domain\Communication\Actions\SendBatchMessageAction;
use App\Domain\Communication\Contracts\WhatsAppProvider;
use App\Domain\Communication\Enums\NotificationChannel;
use App\Domain\Communication\Enums\OutboxStatus;
use App\Domain\Communication\Listeners\SessionRescheduledNotificationListener;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Models\NotificationOutbox;
use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Communication\Models\NotificationTemplate;
use App\Domain\Communication\Services\LogWhatsAppProvider;
use App\Domain\Communication\Services\NotificationService;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Actions\RescheduleSessionAction;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Events\SessionRescheduled;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\Venue;
use App\Livewire\Communication\NotificationOutboxDashboard;
use App\Livewire\Communication\TemplateManager;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['first_name' => 'Swami', 'last_name' => 'Veda']);
    $this->admin->assignRole('Admin');

    $this->teacher = User::factory()->create(['first_name' => 'Acharya', 'last_name' => 'Prashant']);
    $this->teacher->assignRole('Teacher');

    $this->student = User::factory()->create([
        'first_name' => 'Rohan',
        'last_name' => 'Verma',
        'email' => 'rohan@example.com',
        'phone' => '+919876543210',
        'locale' => 'en',
        'timezone' => 'Asia/Kolkata',
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
    ]);
    $this->student->assignRole('Student');

    $this->student2 = User::factory()->create([
        'first_name' => 'Priya',
        'last_name' => 'Nair',
        'email' => 'priya@example.com',
        'phone' => '+919876543211',
        'locale' => 'hi',
        'timezone' => 'Asia/Kolkata',
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
    ]);
    $this->student2->assignRole('Student');

    $this->course = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->teacher->id,
        'name' => 'Kundalini Sadhana Intensive',
        'slug' => 'kundalini-sadhana-intensive',
        'type' => CourseType::ShortTerm,
        'delivery_mode' => DeliveryMode::Hybrid,
        'status' => CourseStatus::Published,
        'fee_amount' => 2500000,
        'fee_currency' => 'INR',
        'duration_weeks' => 4,
    ]);

    $this->batch = Batch::create([
        'course_id' => $this->course->id,
        'branch_id' => $this->branch->id,
        'name' => 'Batch Alpha',
        'delivery_mode' => DeliveryMode::Hybrid,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'capacity' => 20,
        'status' => BatchStatus::Open,
    ]);

    // Active enrollments
    Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    Enrollment::create([
        'student_id' => $this->student2->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->venue = Venue::create([
        'branch_id' => $this->branch->id,
        'name' => 'Main Ashram Hall',
        'address' => 'Tapovan, Rishikesh',
        'capacity' => 50,
    ]);

    $this->session = Session::create([
        'batch_id' => $this->batch->id,
        'course_id' => $this->course->id,
        'venue_id' => $this->venue->id,
        'title' => 'Chakra Alignment Masterclass',
        'starts_at' => CarbonImmutable::parse('2026-11-05 06:30:00', 'Asia/Kolkata'),
        'ends_at' => CarbonImmutable::parse('2026-11-05 08:30:00', 'Asia/Kolkata'),
        'status' => SessionStatus::Scheduled,
    ]);

    // Seed session_rescheduled notification template
    $this->template = NotificationTemplate::create([
        'key' => 'session_rescheduled',
        'name' => 'Session Rescheduled Notice',
        'category' => 'academic',
        'channels' => ['mail', 'database', 'whatsapp'],
        'is_mandatory' => false,
        'version' => 1,
        'is_active' => true,
    ]);

    $this->template->translations()->create([
        'locale' => 'en',
        'channel' => 'mail',
        'subject' => 'Schedule Update: {{ session_title }}',
        'body' => 'Namaste {{ student_name }}, your session {{ session_title }} has been rescheduled from {{ old_starts_at }} to {{ new_starts_at }}. Reason: {{ reason }}.',
    ]);

    $this->template->translations()->create([
        'locale' => 'en',
        'channel' => 'whatsapp',
        'body' => 'Namaste {{ student_name }}, your session {{ session_title }} has been moved to {{ new_starts_at }}.',
        'whatsapp_template_name' => 'pramana_session_rescheduled_en',
    ]);

    $this->template->translations()->create([
        'locale' => 'hi',
        'channel' => 'mail',
        'subject' => 'सत्र समय परिवर्तन: {{ session_title }}',
        'body' => 'नमस्ते {{ student_name }}, आपका सत्र {{ session_title }} अब {{ new_starts_at }} पर आयोजित होगा।',
    ]);
});

test('acceptance criteria: rescheduled session sends exactly one notification per student per enabled channel with idempotency', function (): void {
    Mail::fake();

    $newStart = CarbonImmutable::parse('2026-11-06 07:00:00', 'Asia/Kolkata');
    $newEnd = CarbonImmutable::parse('2026-11-06 09:00:00', 'Asia/Kolkata');

    // Reschedule action fires SessionRescheduled event
    $rescheduleAction = app(RescheduleSessionAction::class);
    $rescheduleAction->execute(
        session: $this->session,
        newStartsAt: $newStart,
        newEndsAt: $newEnd,
        actor: $this->teacher,
        reason: 'Ashram sunrise aarti extension'
    );

    // Verify exactly one notification was created per enrolled student per enabled channel (mail, database, whatsapp)
    // There are 2 students and 3 channels = 6 outbox notifications total
    $student1Outbox = NotificationOutbox::where('user_id', $this->student->id)->get();
    expect($student1Outbox->count())->toBe(3)
        ->and($student1Outbox->pluck('channel')->map->value->all())->toEqualCanonicalizing(['mail', 'database', 'whatsapp']);

    $student2Outbox = NotificationOutbox::where('user_id', $this->student2->id)->get();
    expect($student2Outbox->count())->toBe(3);

    // Verify Hindi translation was selected for student2 whose preferred locale is 'hi'
    $student2Mail = NotificationOutbox::where('user_id', $this->student2->id)->where('channel', 'mail')->first();
    expect($student2Mail->subject)->toContain('सत्र समय परिवर्तन')
        ->and($student2Mail->content)->toContain('नमस्ते Priya Nair');

    // Test Idempotency: Triggering the reschedule event again for the same timestamp does NOT create duplicate notifications
    $listener = app(SessionRescheduledNotificationListener::class);
    $event = new SessionRescheduled(
        session: $this->session->fresh(),
        oldStartsAt: $newStart,
        oldEndsAt: $newEnd,
        actor: $this->teacher,
        reason: 'Duplicate call simulation'
    );
    $listener->handle($event);

    expect(NotificationOutbox::where('user_id', $this->student->id)->count())->toBe(3);
});

test('acceptance criteria: unsubscribe preferences are honoured except for mandatory notices (payments and safety)', function (): void {
    Mail::fake();

    // 1. Student explicitly unsubscribes / disables 'mail' channel for 'academic' notifications
    NotificationPreference::create([
        'user_id' => $this->student->id,
        'channel' => 'mail',
        'notification_type' => 'academic',
        'is_enabled' => false,
    ]);

    $service = app(NotificationService::class);

    // A. Non-mandatory notification (academic reschedule) -> Mail channel is skipped!
    $outboxNonMandatory = $service->send(
        user: $this->student,
        templateKey: 'session_rescheduled',
        variables: ['student_name' => $this->student->full_name]
    );

    expect($outboxNonMandatory->pluck('channel')->map->value->all())->not->toContain('mail')
        ->and($outboxNonMandatory->pluck('channel')->map->value->all())->toContain('database')
        ->and($outboxNonMandatory->pluck('channel')->map->value->all())->toContain('whatsapp');

    // 2. Student also unsubscribes from 'mail' and 'whatsapp' for 'billing'
    NotificationPreference::create([
        'user_id' => $this->student->id,
        'channel' => 'mail',
        'notification_type' => 'billing',
        'is_enabled' => false,
    ]);
    NotificationPreference::create([
        'user_id' => $this->student->id,
        'channel' => 'whatsapp',
        'notification_type' => 'billing',
        'is_enabled' => false,
    ]);

    // B. Mandatory notification (payment_due) -> BYPASSES unsubscribe! Dispatches to mail and whatsapp!
    $outboxMandatory = $service->send(
        user: $this->student,
        templateKey: 'payment_due',
        variables: [
            'student_name' => $this->student->full_name,
            'due_amount' => 25000,
            'due_date' => 'Tomorrow',
        ]
    );

    $mandatoryChannels = $outboxMandatory->pluck('channel')->map->value->all();
    expect($mandatoryChannels)->toContain('mail')
        ->and($mandatoryChannels)->toContain('whatsapp')
        ->and($outboxMandatory->first()->is_mandatory)->toBeTrue();
});

test('timezone-aware quiet hours hold non-urgent notices until morning while mandatory notices bypass quiet hours', function (): void {
    $service = app(NotificationService::class);

    // Set user quiet hours: 22:00 to 07:00 in Asia/Kolkata
    $this->student->update([
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
        'timezone' => 'Asia/Kolkata',
    ]);

    // Simulate current time: 23:30 (inside quiet hours)
    $nightTime = CarbonImmutable::parse('2026-11-10 23:30:00', 'Asia/Kolkata');
    CarbonImmutable::setTestNow($nightTime);

    expect($service->isInQuietHours($this->student))->toBeTrue();

    // A. Non-mandatory message sent at night -> marked as HeldForQuietHours and scheduled for 07:00 AM
    $nonMandatoryOutbox = $service->send(
        user: $this->student,
        templateKey: 'session_rescheduled',
        variables: ['session_title' => 'Gentle Morning Asana']
    );

    $heldItem = $nonMandatoryOutbox->first();
    expect($heldItem->status)->toBe(OutboxStatus::HeldForQuietHours)
        ->and($heldItem->scheduled_at->format('H:i'))->toBe('07:00');

    // B. Mandatory notice (urgent security/payment notice) -> Bypasses quiet hours and is queued/sent immediately!
    $mandatoryOutbox = $service->send(
        user: $this->student,
        templateKey: 'payment_overdue',
        variables: ['invoice_number' => 'INV-999']
    );

    $urgentItem = $mandatoryOutbox->first();
    expect($urgentItem->status)->not->toBe(OutboxStatus::HeldForQuietHours)
        ->and($urgentItem->is_mandatory)->toBeTrue();

    CarbonImmutable::setTestNow();
});

test('WhatsApp Business API provider receives formatted template and parameters', function (): void {
    /** @var LogWhatsAppProvider $whatsAppProvider */
    $whatsAppProvider = app(WhatsAppProvider::class);

    $service = app(NotificationService::class);
    $service->send(
        user: $this->student,
        templateKey: 'session_rescheduled',
        variables: [
            'student_name' => 'Rohan Verma',
            'session_title' => 'Evening Kirtan',
            'new_starts_at' => '07:00 PM',
        ]
    );

    expect(count($whatsAppProvider->sentMessages))->toBeGreaterThan(0);
    $lastMsg = end($whatsAppProvider->sentMessages);
    expect($lastMsg['to'])->toBe('+919876543210')
        ->and($lastMsg['template'])->toBe('session_rescheduled');
});

test('outbox exponential backoff on delivery failure and retry functionality', function (): void {
    $outbox = NotificationOutbox::create([
        'user_id' => $this->student->id,
        'channel' => NotificationChannel::Mail,
        'template_key' => 'session_rescheduled',
        'notification_type' => 'academic',
        'recipient' => 'invalid-email',
        'content' => 'Test body',
        'status' => OutboxStatus::Queued,
        'max_attempts' => 3,
    ]);

    // First failure: attempts becomes 1, backoff scheduled
    $outbox->markFailed('SMTP connection refused');
    expect($outbox->attempts)->toBe(1)
        ->and($outbox->status)->toBe(OutboxStatus::Queued)
        ->and($outbox->scheduled_at)->not->toBeNull();

    // Second failure: attempts becomes 2
    $outbox->markFailed('Timeout');
    expect($outbox->attempts)->toBe(2)
        ->and($outbox->status)->toBe(OutboxStatus::Queued);

    // Third failure: max_attempts reached -> marks Failed
    $outbox->markFailed('Permanent 550 error');
    expect($outbox->attempts)->toBe(3)
        ->and($outbox->status)->toBe(OutboxStatus::Failed);

    // Retry resets status to Queued
    $outbox->retry();
    expect($outbox->status)->toBe(OutboxStatus::Queued);
});

test('teacher-student messaging is strictly restricted to shared batches', function (): void {
    $otherCourse = Course::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'name' => 'Independent Course',
        'slug' => 'independent-course',
        'type' => CourseType::ShortTerm,
        'delivery_mode' => DeliveryMode::Online,
        'status' => CourseStatus::Published,
        'fee_amount' => 1000000,
        'fee_currency' => 'INR',
        'duration_weeks' => 2,
    ]);

    $otherBatch = Batch::create([
        'course_id' => $otherCourse->id,
        'branch_id' => $this->branch->id,
        'name' => 'Other Batch Beta',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'capacity' => 10,
        'status' => BatchStatus::Open,
    ]);

    $unrelatedStudent = User::factory()->create(['first_name' => 'Stranger', 'last_name' => 'Student']);
    $unrelatedStudent->assignRole('Student');

    $sendAction = app(SendBatchMessageAction::class);

    // 1. Success: Teacher messages enrolled student in their shared batch
    $msg = $sendAction->execute(
        sender: $this->teacher,
        batch: $this->batch,
        messageText: 'Please review chapter 4 of the Hatha Yoga Pradipika before tomorrow morning.',
        recipient: $this->student
    );

    expect($msg->message)->toContain('chapter 4')
        ->and($msg->batch_id)->toBe($this->batch->id);

    // 2. Failure: Teacher tries to message an unrelated student not in this batch -> Throws ValidationException!
    expect(fn () => $sendAction->execute(
        sender: $this->teacher,
        batch: $this->batch,
        messageText: 'Unauthorized outreach',
        recipient: $unrelatedStudent
    ))->toThrow(ValidationException::class);

    // 3. Failure: Unrelated student tries to message teacher in a batch they are not enrolled in -> Throws ValidationException!
    expect(fn () => $sendAction->execute(
        sender: $unrelatedStudent,
        batch: $this->batch,
        messageText: 'Intruder message',
        recipient: $this->teacher
    ))->toThrow(ValidationException::class);
});

test('flagging message and audited admin review requires message.audit permission', function (): void {
    $sendAction = app(SendBatchMessageAction::class);
    $msg = $sendAction->execute(
        sender: $this->teacher,
        batch: $this->batch,
        messageText: 'Commercial advertisement unrelated to curriculum',
        recipient: $this->student
    );

    // Student flags message
    $flagAction = app(FlagBatchMessageAction::class);
    $flagAction->execute($msg, $this->student, 'Inappropriate commercial spam.');

    $msg->refresh();
    expect($msg->isFlagged())->toBeTrue()
        ->and($msg->flag_reason)->toBe('Inappropriate commercial spam.');

    // Unauthorized user attempts review -> Throws AuthorizationException
    $unauthorizedUser = User::factory()->create();
    $reviewAction = app(ReviewFlaggedMessageAction::class);

    expect(fn () => $reviewAction->execute($msg, $unauthorizedUser, 'Dismissed'))
        ->toThrow(AuthorizationException::class);

    // Authorized Admin reviews flagged message
    Permission::firstOrCreate(['name' => 'message.audit']);
    $this->admin->givePermissionTo('message.audit');

    $reviewAction->execute($msg, $this->admin, 'Message reviewed. Teacher cautioned regarding batch communication policy.');

    $msg->refresh();
    expect($msg->is_reviewed_by_admin)->toBeTrue()
        ->and($msg->admin_review_notes)->toContain('Teacher cautioned');
});

test('scheduled reminders command dispatches class, payment, assignment, hold, and inactivity notices', function (): void {
    Mail::fake();

    // 1. Session 24h away
    $session24h = Session::create([
        'batch_id' => $this->batch->id,
        'venue_id' => $this->venue->id,
        'title' => 'Pranayama 24h Ahead Session',
        'starts_at' => now()->addHours(24),
        'ends_at' => now()->addHours(26),
        'status' => SessionStatus::Scheduled,
    ]);

    // 2. Unpaid invoice
    $invoice = Invoice::create([
        'student_id' => $this->student->id,
        'invoice_number' => 'INV-REMIND-01',
        'financial_year' => '2026-27',
        'sequence_number' => 1,
        'subtotal_amount' => 1000000,
        'tax_amount' => 0,
        'total_amount' => 1000000,
        'currency' => 'INR',
        'status' => InvoicePaymentStatus::Pending,
        'billing_name' => $this->student->full_name,
        'billing_email' => $this->student->email,
        'issued_at' => now()->subDays(5),
        'due_date' => now()->addDays(2),
    ]);

    // 3. Assignment due in 24h
    $assignment = Assignment::create([
        'course_id' => $this->course->id,
        'title' => 'Asana Journal Reflection',
        'submission_types' => ['text'],
        'due_at' => now()->addHours(24),
        'max_score' => 50,
    ]);

    // 4. Enrollment with seat hold expiring in 1 hour
    $holdEnrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Approved,
        'seat_hold_expires_at' => now()->addHour(),
    ]);

    // 5. Inactive student (last login 10 days ago)
    $inactiveStudent = User::factory()->create([
        'last_login_at' => now()->subDays(10),
    ]);
    Enrollment::create([
        'student_id' => $inactiveStudent->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'status' => EnrollmentStatus::Active,
    ]);

    // Run scheduled reminders command
    $this->artisan('lms:send-scheduled-reminders')
        ->assertSuccessful();

    // Verify outboxes were populated
    expect(NotificationOutbox::where('template_key', 'session_reminder_24h')->exists())->toBeTrue()
        ->and(NotificationOutbox::where('template_key', 'payment_due')->exists())->toBeTrue()
        ->and(NotificationOutbox::where('template_key', 'assignment_due')->exists())->toBeTrue()
        ->and(NotificationOutbox::where('template_key', 'seat_hold_expiring')->exists())->toBeTrue()
        ->and(NotificationOutbox::where('template_key', 'inactivity_nudge')->exists())->toBeTrue();
});

test('announcements scoped to course/batch/institute track read receipts', function (): void {
    $announcement = Announcement::create([
        'branch_id' => $this->branch->id,
        'course_id' => $this->course->id,
        'batch_id' => $this->batch->id,
        'author_id' => $this->teacher->id,
        'title' => 'Special Satsang with Visiting Guru',
        'content' => 'Join us this Saturday evening for a rare discourse on Vedanta.',
        'priority' => 'high',
        'published_at' => now(),
    ]);

    expect($announcement->isReadBy($this->student))->toBeFalse()
        ->and($announcement->read_receipts_count)->toBe(0);

    // Student reads announcement
    $announcement->markAsRead($this->student);

    $announcement->refresh();
    expect($announcement->isReadBy($this->student))->toBeTrue()
        ->and($announcement->read_receipts_count)->toBe(1);

    // Second student reads
    $announcement->markAsRead($this->student2);
    expect($announcement->read_receipts_count)->toBe(2);
});

test('communication outbox dashboard and template manager livewire components render cleanly', function (): void {
    $this->actingAs($this->admin);

    // Outbox Dashboard
    Livewire::test(NotificationOutboxDashboard::class)
        ->assertStatus(200)
        ->assertSee('Multi-Channel Outbox');

    // Template Manager
    Livewire::test(TemplateManager::class)
        ->assertStatus(200)
        ->assertSee('Multi-Channel Notification Template Manager')
        ->assertSee('session_rescheduled')
        ->set('previewLocale', 'hi')
        ->assertSee('सत्र समय परिवर्तन')
        ->call('saveTranslation')
        ->assertSee('Updated hi');
});
