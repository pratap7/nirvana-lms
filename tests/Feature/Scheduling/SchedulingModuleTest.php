<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\DeliveryMode;
use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\Branch;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Actions\ApproveSubstituteAction;
use App\Domain\Scheduling\Actions\AttachSessionRecordingAction;
use App\Domain\Scheduling\Actions\CancelSessionAction;
use App\Domain\Scheduling\Actions\GenerateSessionsAction;
use App\Domain\Scheduling\Actions\RequestSubstituteAction;
use App\Domain\Scheduling\Actions\RescheduleSessionAction;
use App\Domain\Scheduling\DTOs\MeetingDetails;
use App\Domain\Scheduling\Enums\BatchStatus;
use App\Domain\Scheduling\Enums\MeetingProviderType;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Domain\Scheduling\Events\SessionCancelled;
use App\Domain\Scheduling\Events\SessionRescheduled;
use App\Domain\Scheduling\Events\SubstituteAssigned;
use App\Domain\Scheduling\Exceptions\ScheduleConflictException;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\BatchSchedule;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\Venue;
use App\Domain\Scheduling\Services\CalendarLinkGenerator;
use App\Domain\Scheduling\Services\ConflictDetector;
use App\Domain\Scheduling\Services\Meeting\MeetingProvider;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    $this->teacher = User::factory()->create(['first_name' => 'Prof.', 'last_name' => 'Ramanuja']);
    $this->teacher->assignRole('Teacher');

    $this->substitute = User::factory()->create(['first_name' => 'Prof.', 'last_name' => 'Shankara']);
    $this->substitute->assignRole('Teacher');

    $this->moderator = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Moderator']);
    $this->moderator->assignRole('Moderator');

    $this->course = Course::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->user->id,
    ]);

    $this->venue = Venue::create([
        'branch_id' => $this->branch->id,
        'name' => 'Main Campus',
        'code' => 'MC-01',
        'timezone' => 'America/New_York',
        'address' => '100 University Ave, NY',
    ]);

    $this->room = Room::create([
        'venue_id' => $this->venue->id,
        'name' => 'Hall A',
        'capacity' => 40,
    ]);
});

test('DST-Safe Recurrence: generating a weekly Mon/Wed batch across a DST change yields consistent local times', function (): void {
    // In US in March 2026, DST starts Sunday March 8, 2026 (clocks forward 1hr: EST UTC-5 to EDT UTC-4).
    // A schedule on Mon/Wed at 19:00 NY time across March should always have local time 19:00:00.
    $batch = Batch::create([
        'course_id' => $this->course->id,
        'venue_id' => $this->venue->id,
        'room_id' => $this->room->id,
        'name' => 'Spring 2026 Nyaya Philosophy',
        'delivery_mode' => DeliveryMode::Offline,
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-25',
        'status' => BatchStatus::Open,
    ]);

    // Insert a holiday on Wednesday March 18 to verify holiday awareness
    Holiday::create([
        'branch_id' => $this->branch->id,
        'name' => 'Spring Equinox Festival',
        'holiday_date' => '2026-03-18',
    ]);

    $schedule = BatchSchedule::create([
        'batch_id' => $batch->id,
        'days_of_week' => [1, 3], // Monday (1) and Wednesday (3)
        'start_time' => '19:00:00',
        'end_time' => '20:30:00',
        'timezone' => 'America/New_York',
        'until_date' => '2026-03-25',
        'exclude_holidays' => true,
    ]);

    /** @var GenerateSessionsAction $generator */
    $generator = app(GenerateSessionsAction::class);
    $sessions = $generator->execute($batch, $schedule, $this->teacher);

    // Mon Mar 2, Wed Mar 4 (Before DST: UTC-5)
    // Mon Mar 9, Wed Mar 11 (After DST: UTC-4)
    // Mon Mar 16 (After DST: UTC-4)
    // (Wed Mar 18 is Holiday -> skipped!)
    // Mon Mar 23, Wed Mar 25 (After DST: UTC-4)
    // Total should be 7 sessions (8 minus 1 holiday)
    expect($sessions)->toHaveCount(7);

    // Verify all sessions have identical local start and end wall-clock times in America/New_York
    foreach ($sessions as $session) {
        $localStart = $session->starts_at->setTimezone('America/New_York');
        $localEnd = $session->ends_at->setTimezone('America/New_York');

        expect($localStart->format('H:i'))->toBe('19:00')
            ->and($localEnd->format('H:i'))->toBe('20:30')
            ->and($session->status)->toBe(SessionStatus::Scheduled);
    }

    // Verify UTC times reflect the DST jump
    $mar2Utc = $sessions[0]->starts_at->setTimezone('UTC')->format('H:i'); // EST (UTC-5) -> 00:00 next day
    $mar9Utc = $sessions[2]->starts_at->setTimezone('UTC')->format('H:i'); // EDT (UTC-4) -> 23:00 same day
    expect($mar2Utc)->toBe('00:00')
        ->and($mar9Utc)->toBe('23:00');

    // Verify holiday on March 18 was skipped
    $march18Session = $sessions->first(function ($s) {
        return $s->starts_at->setTimezone('America/New_York')->format('Y-m-d') === '2026-03-18';
    });
    expect($march18Session)->toBeNull();
});

test('Double-booking conflict detection: room and teacher conflicts are rejected with clear errors', function (): void {
    $batch = Batch::create([
        'course_id' => $this->course->id,
        'venue_id' => $this->venue->id,
        'room_id' => $this->room->id,
        'name' => 'Batch Alpha',
        'delivery_mode' => DeliveryMode::Offline,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
        'status' => BatchStatus::Open,
    ]);

    // Create an existing session from 10:00 to 11:30 UTC
    $existing = Session::create([
        'batch_id' => $batch->id,
        'venue_id' => $this->venue->id,
        'room_id' => $this->room->id,
        'title' => 'Session 1',
        'starts_at' => '2026-04-10 10:00:00',
        'ends_at' => '2026-04-10 11:30:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);

    $existing->sessionTeachers()->create([
        'user_id' => $this->teacher->id,
        'role' => SessionTeacherRole::Lead,
        'status' => SubstituteRequestStatus::Approved,
    ]);

    /** @var ConflictDetector $detector */
    $detector = app(ConflictDetector::class);

    // Overlapping interval: 11:00 to 12:30
    $overlapStart = CarbonImmutable::parse('2026-04-10 11:00:00');
    $overlapEnd = CarbonImmutable::parse('2026-04-10 12:30:00');

    // 1. Room conflict test
    expect(fn () => $detector->assertNoConflicts(
        roomId: $this->room->id,
        teacherId: null,
        startsAt: $overlapStart,
        endsAt: $overlapEnd
    ))->toThrow(ScheduleConflictException::class, 'Room [Hall A] is already booked');

    // 2. Teacher conflict test
    $otherRoom = Room::create([
        'venue_id' => $this->venue->id,
        'name' => 'Hall B',
        'capacity' => 20,
    ]);

    expect(fn () => $detector->assertNoConflicts(
        roomId: $otherRoom->id,
        teacherId: $this->teacher->id,
        startsAt: $overlapStart,
        endsAt: $overlapEnd
    ))->toThrow(ScheduleConflictException::class, 'Teacher [Prof. Ramanuja] is already booked');
});

test('Single-session cancellation leaves all other batch sessions intact', function (): void {
    Event::fake([SessionCancelled::class]);

    $batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Batch Beta',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-30',
        'status' => BatchStatus::Open,
    ]);

    // Create 3 sessions
    $s1 = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Lecture 1',
        'session_number' => 1,
        'starts_at' => '2026-05-04 10:00:00',
        'ends_at' => '2026-05-04 11:00:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);
    $s2 = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Lecture 2',
        'session_number' => 2,
        'starts_at' => '2026-05-06 10:00:00',
        'ends_at' => '2026-05-06 11:00:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);
    $s3 = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Lecture 3',
        'session_number' => 3,
        'starts_at' => '2026-05-08 10:00:00',
        'ends_at' => '2026-05-08 11:00:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);

    /** @var CancelSessionAction $canceller */
    $canceller = app(CancelSessionAction::class);
    $canceller->execute($s2, 'Teacher emergency leave', $this->moderator);

    $s1->refresh();
    $s2->refresh();
    $s3->refresh();

    // S2 is cancelled with reason
    expect($s2->status)->toBe(SessionStatus::Cancelled)
        ->and($s2->cancellation_reason)->toBe('Teacher emergency leave');

    // S1 and S3 remain untouched and Scheduled!
    expect($s1->status)->toBe(SessionStatus::Scheduled)
        ->and($s1->cancellation_reason)->toBeNull()
        ->and($s3->status)->toBe(SessionStatus::Scheduled)
        ->and($s3->cancellation_reason)->toBeNull();

    Event::assertDispatched(SessionCancelled::class, fn ($e) => $e->session->id === $s2->id);
});

test('Single-session rescheduling modifies only that session and dispatches SessionRescheduled event', function (): void {
    Event::fake([SessionRescheduled::class]);

    $batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Batch Gamma',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-30',
        'status' => BatchStatus::Open,
    ]);

    $session = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Lecture 1',
        'session_number' => 1,
        'starts_at' => '2026-05-04 10:00:00',
        'ends_at' => '2026-05-04 11:00:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);

    /** @var RescheduleSessionAction $rescheduler */
    $rescheduler = app(RescheduleSessionAction::class);
    $rescheduler->execute(
        $session,
        CarbonImmutable::parse('2026-05-05 14:00:00'),
        CarbonImmutable::parse('2026-05-05 15:30:00'),
        null,
        $this->moderator,
        'Shifted per student request'
    );

    $session->refresh();

    expect($session->status)->toBe(SessionStatus::Rescheduled)
        ->and($session->starts_at->format('Y-m-d H:i:s'))->toBe('2026-05-05 14:00:00')
        ->and($session->ends_at->format('Y-m-d H:i:s'))->toBe('2026-05-05 15:30:00');

    Event::assertDispatched(SessionRescheduled::class, fn ($e) => $e->session->id === $session->id);
});

test('Substitute teacher flow: teacher requests, moderator approves, students notified automatically', function (): void {
    Event::fake([SubstituteAssigned::class]);

    $batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Batch Delta',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => BatchStatus::Open,
    ]);

    $session = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Epistemology Workshop',
        'session_number' => 1,
        'starts_at' => '2026-06-05 09:00:00',
        'ends_at' => '2026-06-05 10:30:00',
        'timezone' => 'UTC',
        'status' => SessionStatus::Scheduled,
    ]);

    // Initial lead teacher
    $session->sessionTeachers()->create([
        'user_id' => $this->teacher->id,
        'role' => SessionTeacherRole::Lead,
        'status' => SubstituteRequestStatus::Approved,
    ]);

    expect($session->getActiveTeacher()->id)->toBe($this->teacher->id);

    // 1. Teacher requests substitute
    /** @var RequestSubstituteAction $requester */
    $requester = app(RequestSubstituteAction::class);
    $subRecord = $requester->execute(
        $session,
        $this->substitute,
        $this->teacher,
        'Attending international academic conference'
    );

    expect($subRecord->status)->toBe(SubstituteRequestStatus::Requested)
        ->and($subRecord->user_id)->toBe($this->substitute->id);

    // Before approval, active teacher is still original lead teacher
    expect($session->getActiveTeacher()->id)->toBe($this->teacher->id);

    // 2. Moderator approves
    /** @var ApproveSubstituteAction $approver */
    $approver = app(ApproveSubstituteAction::class);
    $approver->execute($subRecord, $this->moderator);

    $subRecord->refresh();
    expect($subRecord->status)->toBe(SubstituteRequestStatus::Approved)
        ->and($subRecord->approved_by)->toBe($this->moderator->id);

    // Now active teacher is the approved substitute!
    $session->refresh();
    expect($session->getActiveTeacher()->id)->toBe($this->substitute->id);

    // SubstituteAssigned event fired with correct payload
    Event::assertDispatched(SubstituteAssigned::class, function ($event) use ($session) {
        return $event->session->id === $session->id
            && $event->substituteTeacher->id === $this->substitute->id
            && $event->approvedBy->id === $this->moderator->id;
    });
});

test('Online session meeting provider integration and recording attachments', function (): void {
    // Mock the MeetingProvider interface
    $mockProvider = Mockery::mock(MeetingProvider::class);
    $mockProvider->shouldReceive('createMeeting')
        ->once()
        ->andReturn(new MeetingDetails(
            provider: 'zoom',
            meetingLink: 'https://zoom.us/j/999888777',
            meetingId: '999888777',
            password: 'Secret123'
        ));
    app()->instance(MeetingProvider::class, $mockProvider);

    $batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Online Sankhya Class',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-07',
        'status' => BatchStatus::Open,
    ]);

    $schedule = BatchSchedule::create([
        'batch_id' => $batch->id,
        'days_of_week' => [1],
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'timezone' => 'UTC',
        'until_date' => '2026-07-07',
        'exclude_holidays' => false,
    ]);

    /** @var GenerateSessionsAction $generator */
    $generator = app(GenerateSessionsAction::class);
    $sessions = $generator->execute($batch, $schedule);

    expect($sessions)->toHaveCount(1);
    $session = $sessions->first();

    expect($session->meeting_url)->toBe('https://zoom.us/j/999888777')
        ->and($session->meeting_id)->toBe('999888777')
        ->and($session->meeting_passcode)->toBe('Secret123')
        ->and($session->meeting_provider)->toBe(MeetingProviderType::Zoom);

    // Attach recording after session
    /** @var AttachSessionRecordingAction $recorder */
    $recorder = app(AttachSessionRecordingAction::class);
    $recording = $recorder->execute(
        session: $session,
        videoUrl: 'https://stream.pramana.edu/recordings/rec_999888.mp4',
        title: 'Class 1 Video Archive',
        provider: 'bunny',
        durationSeconds: 3600
    );

    expect($recording->video_id_or_url)->toBe('https://stream.pramana.edu/recordings/rec_999888.mp4')
        ->and($recording->duration_seconds)->toBe(3600)
        ->and($session->recordings)->toHaveCount(1);
});

test('Calendar feeds: ICS feed per user with secret token and Add-to-Calendar links', function (): void {
    $batch = Batch::create([
        'course_id' => $this->course->id,
        'name' => 'Hermeneutics Seminar',
        'delivery_mode' => DeliveryMode::Online,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'status' => BatchStatus::Open,
    ]);

    $session = Session::create([
        'batch_id' => $batch->id,
        'title' => 'Purva Mimamsa Exegesis',
        'session_number' => 1,
        'starts_at' => now()->addDays(2)->format('Y-m-d 14:00:00'),
        'ends_at' => now()->addDays(2)->format('Y-m-d 16:00:00'),
        'timezone' => 'UTC',
        'meeting_link' => 'https://meet.google.com/xyz-abcd-efg',
        'status' => SessionStatus::Scheduled,
    ]);

    $session->sessionTeachers()->create([
        'user_id' => $this->teacher->id,
        'role' => SessionTeacherRole::Lead,
        'status' => SubstituteRequestStatus::Approved,
    ]);

    // 1. Test ICS token feed for the teacher
    $token = $this->teacher->getCalendarToken();
    expect($token)->toBeString()->toHaveLength(64);

    $response = $this->get("/calendar/feed/{$token}.ics");
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
        ->and($content)->toContain('SUMMARY:Purva Mimamsa Exegesis')
        ->and($content)->toContain('https://meet.google.com/xyz-abcd-efg')
        ->and($content)->toContain('END:VCALENDAR');

    // 2. Test Single Session ICS download
    $singleResponse = $this->get("/sessions/{$session->ulid}/ics");
    $singleResponse->assertStatus(200);
    $singleResponse->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($singleResponse->getContent())->toContain('SUMMARY:Purva Mimamsa Exegesis');

    // 3. Test FullCalendar events JSON endpoint
    $this->actingAs($this->teacher);
    $eventsResponse = $this->getJson('/calendar/events');
    $eventsResponse->assertStatus(200);
    $eventsData = $eventsResponse->json();

    expect($eventsData)->toBeArray()->toHaveCount(1)
        ->and($eventsData[0]['title'])->toContain('Purva Mimamsa Exegesis')
        ->and($eventsData[0]['url'])->toBe('https://meet.google.com/xyz-abcd-efg');

    // 4. Test CalendarLinkGenerator
    $googleUrl = CalendarLinkGenerator::googleUrl($session);
    $outlookUrl = CalendarLinkGenerator::outlookUrl($session);

    expect($googleUrl)->toContain('https://calendar.google.com/calendar/render?action=TEMPLATE')
        ->and($googleUrl)->toContain(urlencode('Purva Mimamsa Exegesis'))
        ->and($outlookUrl)->toContain('https://outlook.live.com/calendar/0/deeplink/compose');
});
