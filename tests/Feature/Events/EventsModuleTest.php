<?php

declare(strict_types=1);

use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Enums\InvoicePaymentStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Events\Actions\ImportEventInvitationsAction;
use App\Domain\Events\Actions\IssueEventCertificateAction;
use App\Domain\Events\Actions\RegisterForEventAction;
use App\Domain\Events\Enums\EventFormat;
use App\Domain\Events\Enums\EventPricingType;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\OccurrenceStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventInvitation;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventSpeaker;
use App\Domain\Events\Services\EventRecurrenceService;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\SessionAttendance;
use App\Domain\Scheduling\Models\Venue;
use App\Livewire\Events\EventAttendanceReport;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\StaffEventCheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Roles
    Role::firstOrCreate(['name' => 'Admin']);
    Role::firstOrCreate(['name' => 'Moderator']);
    Role::firstOrCreate(['name' => 'Student']);
    Role::firstOrCreate(['name' => 'Teacher']);

    $this->branch = Branch::factory()->create([
        'name' => 'Rishikesh Main Ashram',
        'code' => 'RSH',
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'first_name' => 'Acharya',
        'last_name' => 'Dev',
        'email' => 'admin@pramana.org',
    ]);
    $this->admin->assignRole('Admin');

    $this->staff = User::factory()->create([
        'first_name' => 'Pooja',
        'last_name' => 'Staff',
        'email' => 'staff@pramana.org',
    ]);
    $this->staff->assignRole('Moderator');

    $this->student = User::factory()->create([
        'first_name' => 'Arjun',
        'last_name' => 'Kapoor',
        'email' => 'arjun@example.com',
    ]);
    $this->student->assignRole('Student');

    $this->venue = Venue::create([
        'branch_id' => $this->branch->id,
        'name' => 'Ganga Dhyana Mandir',
        'slug' => 'ganga-dhyana-mandir',
        'address' => 'Tapovan, Badrinath Road',
        'city' => 'Rishikesh',
        'state' => 'Uttarakhand',
        'country' => 'India',
        'postal_code' => '249192',
    ]);
});

test('ACCEPTANCE: A paid event uses the same webhook-verified payment path', function (): void {
    // 1. Create paid event
    $paidEvent = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'International Yoga & Pranayama Retreat 2026',
        'slug' => 'international-yoga-pranayama-retreat-2026',
        'description' => 'A transformative 3-day immersion into classical pranayama, meditation and sacred kriya.',
        'format' => EventFormat::Hybrid,
        'pricing_type' => EventPricingType::Paid,
        'fee_amount' => 500000, // ₹5,000 in paise
        'fee_currency' => 'INR',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(13),
        'venue_id' => $this->venue->id,
        'capacity' => 50,
        'allow_plus_one' => true,
        'status' => EventStatus::Published,
    ]);

    // 2. Register student with guest (+1)
    $registerAction = app(RegisterForEventAction::class);
    $result = $registerAction->execute(
        event: $paidEvent,
        user: $this->student,
        hasPlusOne: true,
        plusOneName: 'Priya Kapoor',
        plusOneEmail: 'priya@example.com'
    );

    /** @var EventRegistration $registration */
    $registration = $result['registration'];
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];

    expect($registration)->toBeInstanceOf(EventRegistration::class)
        ->and($invoice)->toBeInstanceOf(Invoice::class)
        ->and($result['status'])->toBe(EnrollmentStatus::PaymentPending)
        ->and($invoice->billable_type)->toBe(EventRegistration::class)
        ->and($invoice->billable_id)->toBe($registration->id)
        ->and($invoice->subtotal_amount)->toBe(1000000) // 2 seats x ₹5,000 = ₹10,000 subtotal
        ->and($invoice->total_amount)->toBe(1180000); // with standard 18% GST = ₹11,800 total

    // Enrollment status is PaymentPending initially
    expect($registration->enrollment->status)->toBe(EnrollmentStatus::PaymentPending);

    // 3. Webhook arrives from payment gateway (simulated via recorded Payment and MarkInvoicePaidAction)
    Payment::create([
        'invoice_id' => $invoice->id,
        'student_id' => $this->student->id,
        'amount' => $invoice->total_amount,
        'currency' => $invoice->currency,
        'method' => PaymentMethod::Stripe,
        'gateway' => 'stripe',
        'status' => PaymentStatus::Verified,
        'reference_number' => 'pi_test_stripe_webhook_9921',
        'verified_at' => CarbonImmutable::now(),
    ]);

    // Execute MarkInvoicePaidAction (the exact ledger path triggered by Stripe webhooks)
    MarkInvoicePaidAction::run($invoice);

    // 4. Verification: Enrollment transitions to Active, ticket QR is generated
    $registration->refresh();
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoicePaymentStatus::Paid)
        ->and($registration->enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($registration->qr_code_svg)->not()->toBeNull()
        ->and($registration->qr_code_svg)->toContain('<svg');
});

test('ACCEPTANCE: A recurring events occurrences can be individually cancelled', function (): void {
    // 1. Create recurring event with RRULE (Weekly for 4 sessions)
    $recurringEvent = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Sunday Morning Vedic Chanting & Meditation',
        'slug' => 'sunday-morning-vedic-chanting',
        'description' => 'Weekly Sunday sadhana cohort for chanting and meditation.',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'is_recurring' => true,
        'rrule' => 'FREQ=WEEKLY;INTERVAL=1;COUNT=4',
        'starts_at' => now()->next('Sunday')->setTime(7, 0),
        'ends_at' => now()->next('Sunday')->setTime(8, 30),
        'venue_id' => $this->venue->id,
        'capacity' => 40,
        'status' => EventStatus::Published,
    ]);

    // 2. Generate occurrences from RRULE
    $service = app(EventRecurrenceService::class);
    $occurrences = $service->generateOccurrences($recurringEvent);

    expect($occurrences)->toHaveCount(4);

    $occ1 = $occurrences[0];
    $occ2 = $occurrences[1];
    $occ3 = $occurrences[2];
    $occ4 = $occurrences[3];

    expect($occ1->status)->toBe(OccurrenceStatus::Scheduled)
        ->and($occ2->status)->toBe(OccurrenceStatus::Scheduled);

    // 3. Individually cancel Occurrence #2 (e.g. Acharya traveling)
    $occ2->cancel('Acharya traveling to Kedarnath ashram. Resumes next week.');

    $occ1->refresh();
    $occ2->refresh();
    $occ3->refresh();
    $occ4->refresh();

    // Occurrence 2 is cancelled with reason
    expect($occ2->status)->toBe(OccurrenceStatus::Cancelled)
        ->and($occ2->cancellation_reason)->toContain('Acharya traveling')
        ->and($occ2->cancelled_at)->not()->toBeNull();

    // Occurrences 1, 3, and 4 remain Scheduled!
    expect($occ1->status)->toBe(OccurrenceStatus::Scheduled)
        ->and($occ3->status)->toBe(OccurrenceStatus::Scheduled)
        ->and($occ4->status)->toBe(OccurrenceStatus::Scheduled);
});

test('Event Module: Invitation-only events with signed single-use links and CSV import', function (): void {
    $inviteOnlyEvent = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Advanced Kundalini Tantra Masterclass',
        'slug' => 'advanced-kundalini-tantra-masterclass',
        'description' => 'Strictly by invitation for senior initiates only.',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::InvitationOnly,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
        'capacity' => 10,
        'status' => EventStatus::Published,
    ]);

    // 1. Attempting registration without invitation token is rejected
    Livewire::actingAs($this->student)
        ->test(EventShow::class, ['event' => $inviteOnlyEvent])
        ->call('register')
        ->assertSee('This event is strictly invitation-only');

    // 2. CSV Import of invitees
    $csvData = "swami.anand@example.com,Swami Anand\narjun@example.com,Arjun Kapoor\nvandana@example.com,Vandana Devi";
    $importAction = app(ImportEventInvitationsAction::class);
    $invitations = $importAction->execute($inviteOnlyEvent, $csvData);

    expect($invitations)->toHaveCount(3);

    /** @var EventInvitation $studentInvite */
    $studentInvite = $invitations->firstWhere('email', 'arjun@example.com');
    expect($studentInvite)->not()->toBeNull()
        ->and($studentInvite->status)->toBe('pending')
        ->and($studentInvite->isValid())->toBeTrue();

    // Verify signed URL generation
    $signedUrl = $studentInvite->getSignedInviteUrl();
    expect($signedUrl)->toContain('token='.$studentInvite->token);

    // 3. Register using the valid invitation token
    Livewire::actingAs($this->student)
        ->test(EventShow::class, ['event' => $inviteOnlyEvent])
        ->set('inviteToken', $studentInvite->token)
        ->call('register')
        ->assertSee('Registration confirmed!');

    // Invitation is now marked used
    $studentInvite->refresh();
    expect($studentInvite->status)->toBe('used')
        ->and($studentInvite->used_at)->not()->toBeNull()
        ->and($studentInvite->isValid())->toBeFalse();

    // 4. Attempting to reuse the same invitation fails
    $otherStudent = User::factory()->create();
    Livewire::actingAs($otherStudent)
        ->test(EventShow::class, ['event' => $inviteOnlyEvent])
        ->set('inviteToken', $studentInvite->token)
        ->call('register')
        ->assertSee('Invalid, expired, or already used invitation token');
});

test('Event Module: Mobile-friendly QR check-in terminal with offline duplicate detection', function (): void {
    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Ganga Aarti & Nada Yoga Conclave',
        'slug' => 'ganga-aarti-nada-yoga-conclave',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(5),
        'capacity' => 100,
        'status' => EventStatus::Published,
    ]);

    $occurrence = EventOccurrence::create([
        'event_id' => $event->id,
        'occurrence_number' => 1,
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'status' => OccurrenceStatus::Scheduled,
    ]);

    // Register attendee with guest (+1)
    $registerAction = app(RegisterForEventAction::class);
    $result = $registerAction->execute(
        event: $event,
        user: $this->student,
        hasPlusOne: true,
        plusOneName: 'Kiran Kapoor'
    );
    /** @var EventRegistration $reg */
    $reg = $result['registration'];

    expect($reg->ticket_code)->toStartWith('TKT-')
        ->and($reg->is_checked_in)->toBeFalse();

    // 1. Staff scans ticket code -> Success
    Livewire::actingAs($this->staff)
        ->test(StaffEventCheckIn::class, ['event' => $event->id])
        ->set('scanInput', $reg->ticket_code)
        ->call('processScan')
        ->assertSee('Check-in successful! Welcome Arjun Kapoor and Kiran Kapoor')
        ->assertDontSee('DUPLICATE ENTRY DETECTED');

    $reg->refresh();
    expect($reg->is_checked_in)->toBeTrue()
        ->and($reg->checked_in_by)->toBe($this->staff->id)
        ->and($reg->checked_in_at)->not()->toBeNull();

    // Polymorphic SessionAttendance record is created with status 'present'
    $attendance = SessionAttendance::query()
        ->where('attendable_type', EventOccurrence::class)
        ->where('attendable_id', $occurrence->id)
        ->where('user_id', $this->student->id)
        ->first();

    expect($attendance)->not()->toBeNull()
        ->and($attendance->status)->toBe('present');

    // 2. Offline duplicate attempt: Staff scans the SAME ticket a second time
    Livewire::actingAs($this->staff)
        ->test(StaffEventCheckIn::class, ['event' => $event->id])
        ->set('scanInput', $reg->ticket_code)
        ->call('processScan')
        ->assertSee('DUPLICATE ENTRY DETECTED')
        ->assertSee('was already scanned');
});

test('Event Module: Event certificates issued under attendance-only rule', function (): void {
    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Vipassana Meditation Weekend',
        'slug' => 'vipassana-meditation-weekend',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'starts_at' => now()->subDays(1),
        'ends_at' => now(),
        'capacity' => 50,
        'issue_certificates' => true,
        'certificate_rule' => 'attendance_only',
        'status' => EventStatus::Completed,
    ]);

    $registerAction = app(RegisterForEventAction::class);
    $result = $registerAction->execute(event: $event, user: $this->student);
    /** @var EventRegistration $reg */
    $reg = $result['registration'];

    $certAction = app(IssueEventCertificateAction::class);

    // 1. Attendee has NOT checked in -> certificate issuance is rejected
    expect(fn () => $certAction->execute($reg))
        ->toThrow(ValidationException::class);

    // 2. Mark checked in
    $reg->update([
        'is_checked_in' => true,
        'checked_in_at' => now(),
        'checked_in_by' => $this->staff->id,
    ]);

    // 3. Certificate issuance succeeds under attendance-only rule
    $cert = $certAction->execute($reg);

    expect($cert)->toBeInstanceOf(Certificate::class)
        ->and($cert->certificate_number)->toStartWith('PRM-EVT-')
        ->and($cert->event_id)->toBe($event->id)
        ->and($cert->user_id)->toBe($this->student->id)
        ->and($cert->metadata['rule_applied'])->toBe('attendance_only');
});

test('Event Module: Post-event attendee feedback submission', function (): void {
    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Chakra Harmonization & Singing Bowls',
        'slug' => 'chakra-harmonization-singing-bowls',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHours(2),
        'capacity' => 30,
        'status' => EventStatus::Completed,
    ]);

    $registerAction = app(RegisterForEventAction::class);
    $result = $registerAction->execute(event: $event, user: $this->student);
    $result['registration']->update([
        'is_checked_in' => true,
        'checked_in_at' => now()->subHours(5),
    ]);

    Livewire::actingAs($this->student)
        ->test(EventShow::class, ['event' => $event])
        ->set('feedbackRating', 5)
        ->set('feedbackNps', 10)
        ->set('feedbackComments', 'The singing bowl vibrations created deep meditative stillness. Hari Om!')
        ->call('submitFeedback')
        ->assertSee('Thank you for your feedback!');

    expect($event->feedbacks()->count())->toBe(1);
    $fb = $event->feedbacks()->first();
    expect($fb->rating)->toBe(5)
        ->and($fb->recommendation_score)->toBe(10)
        ->and($fb->comments)->toContain('deep meditative stillness');
});

test('Event Module: Public event page with schema.org JSON-LD and ICS calendar download', function (): void {
    $speaker = User::factory()->create(['first_name' => 'Swami', 'last_name' => 'Niranjan']);

    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Vedanta Sutras Colloquium',
        'slug' => 'vedanta-sutras-colloquium',
        'description' => 'A discourse on Brahma Sutras with traditional commentary.',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'starts_at' => now()->addDays(7)->setTime(9, 0),
        'ends_at' => now()->addDays(7)->setTime(17, 0),
        'venue_id' => $this->venue->id,
        'capacity' => 75,
        'status' => EventStatus::Published,
    ]);

    EventSpeaker::create([
        'event_id' => $event->id,
        'user_id' => $speaker->id,
        'name' => 'Swami Niranjan',
        'title' => 'Senior Acharya of Advaita Vedanta',
        'is_lead' => true,
    ]);

    // 1. Public Event Detail page renders with Schema.org JSON-LD
    $response = $this->get(route('events.show', ['event' => $event->slug]));
    $response->assertStatus(200);
    $response->assertSee('schema.org', false);
    $response->assertSee('"@type": "Event"', false);
    $response->assertSee('Vedanta Sutras Colloquium');
    $response->assertSee('Swami Niranjan');
    $response->assertSee('Download .ICS Calendar File');

    // 2. ICS Calendar download endpoint
    $icsResponse = $this->get(route('events.ics', ['event' => $event->slug]));
    $icsResponse->assertStatus(200);
    $icsResponse->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $icsContent = $icsResponse->getContent();
    expect($icsContent)->toContain('BEGIN:VCALENDAR')
        ->and($icsContent)->toContain('BEGIN:VEVENT')
        ->and($icsContent)->toContain('SUMMARY:Vedanta Sutras Colloquium')
        ->and($icsContent)->toContain('LOCATION:Ganga Dhyana Mandir')
        ->and($icsContent)->toContain('STATUS:CONFIRMED')
        ->and($icsContent)->toContain('END:VCALENDAR');
});

test('Event Module: Moderator Attendance Report view and bulk certificate issuance', function (): void {
    $event = Event::create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'title' => 'Ashtanga Vinyasa Weekend Immersion',
        'slug' => 'ashtanga-vinyasa-weekend-immersion',
        'format' => EventFormat::InPerson,
        'pricing_type' => EventPricingType::Free,
        'starts_at' => now()->subDay(),
        'ends_at' => now(),
        'capacity' => 20,
        'status' => EventStatus::Completed,
    ]);

    $registerAction = app(RegisterForEventAction::class);
    $r1 = $registerAction->execute(event: $event, user: $this->student);
    $r1['registration']->update([
        'is_checked_in' => true,
        'checked_in_at' => now()->subHours(4),
        'checked_in_by' => $this->staff->id,
    ]);

    Livewire::actingAs($this->staff)
        ->test(EventAttendanceReport::class, ['event' => $event])
        ->assertSee('Arjun Kapoor')
        ->assertSee('Checked In')
        ->call('bulkIssueCertificates')
        ->assertSee('Bulk issuance completed');

    expect(Certificate::query()->where('event_id', $event->id)->count())->toBe(1);
});
