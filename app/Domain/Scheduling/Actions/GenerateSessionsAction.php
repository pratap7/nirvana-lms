<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\MeetingProviderType;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Enums\SessionTeacherRole;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\BatchSchedule;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionTeacher;
use App\Domain\Scheduling\Services\ConflictDetector;
use App\Domain\Scheduling\Services\Meeting\MeetingProvider;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateSessionsAction extends BaseAction
{
    public function __construct(
        protected ConflictDetector $conflictDetector,
        protected MeetingProvider $meetingProvider,
    ) {}

    /**
     * Expands a batch recurrence schedule into individual Session rows.
     *
     * DST-safe: Wall-clock times in the target timezone are respected across daylight saving boundaries.
     * Holiday calendar aware: Excludes institute holidays if exclude_holidays is enabled.
     *
     * @return Collection<int, Session>
     */
    public function execute(
        Batch $batch,
        BatchSchedule $schedule,
        ?User $leadTeacher = null,
        bool $checkConflicts = true
    ): Collection {
        return DB::transaction(function () use ($batch, $schedule, $leadTeacher, $checkConflicts): Collection {
            $createdSessions = collect();
            $sessionNumber = $batch->sessions()->count() + 1;

            $tz = $schedule->timezone ?: 'Asia/Kolkata';
            $startDate = CarbonImmutable::parse($batch->start_date->format('Y-m-d'), $tz);
            $untilDate = CarbonImmutable::parse($schedule->until_date->format('Y-m-d'), $tz);

            // Fetch holiday dates to exclude
            $holidays = [];
            if ($schedule->exclude_holidays) {
                $holidays = Holiday::query()
                    ->where(function ($q) use ($batch): void {
                        $q->whereNull('branch_id')
                            ->orWhere('branch_id', $batch->branch_id);
                    })
                    ->pluck('holiday_date')
                    ->map(fn ($d) => $d->format('Y-m-d'))
                    ->all();
            }
            $holidayLookup = array_flip($holidays);

            $currentDate = $startDate;
            $selectedDays = array_map('intval', $schedule->days_of_week);

            while ($currentDate->lessThanOrEqualTo($untilDate)) {
                $dayOfWeek = (int) $currentDate->dayOfWeekIso; // 1 = Monday, 7 = Sunday

                if (in_array($dayOfWeek, $selectedDays, true)) {
                    $dateStr = $currentDate->format('Y-m-d');

                    // Check holiday exclusion
                    if (! isset($holidayLookup[$dateStr])) {
                        // DST-safe local wall clock start and end timestamps
                        $localStart = CarbonImmutable::parse("{$dateStr} {$schedule->start_time}", $tz);
                        $localEnd = CarbonImmutable::parse("{$dateStr} {$schedule->end_time}", $tz);

                        $startsAtUtc = $localStart->setTimezone('UTC');
                        $endsAtUtc = $localEnd->setTimezone('UTC');

                        // Detect potential room and teacher double-booking
                        if ($checkConflicts) {
                            $this->conflictDetector->assertNoConflicts(
                                roomId: $batch->room_id,
                                teacherId: $leadTeacher?->id,
                                startsAt: $startsAtUtc,
                                endsAt: $endsAtUtc,
                            );
                        }

                        $isOnline = $batch->delivery_mode->value !== 'offline';
                        $slug = Str::slug("{$batch->slug}-s{$sessionNumber}-{$dateStr}-".Str::random(4));

                        /** @var Session $session */
                        $session = Session::create([
                            'batch_id' => $batch->id,
                            'title' => "{$batch->name} - Session #{$sessionNumber}",
                            'slug' => $slug,
                            'starts_at' => $startsAtUtc,
                            'ends_at' => $endsAtUtc,
                            'timezone' => $tz,
                            'venue_id' => $batch->venue_id,
                            'room_id' => $batch->room_id,
                            'is_online' => $isOnline,
                            'meeting_provider' => $isOnline ? MeetingProviderType::Zoom : MeetingProviderType::None,
                            'status' => SessionStatus::Scheduled,
                        ]);

                        // Generate online meeting link if virtual session
                        if ($isOnline) {
                            $meetingDetails = $this->meetingProvider->createMeeting($session);
                            $session->update([
                                'meeting_link' => $meetingDetails->meetingLink,
                                'meeting_id' => $meetingDetails->meetingId,
                                'meeting_password' => $meetingDetails->password,
                            ]);
                        }

                        // Attach lead teacher
                        if ($leadTeacher !== null) {
                            SessionTeacher::create([
                                'session_id' => $session->id,
                                'user_id' => $leadTeacher->id,
                                'role' => SessionTeacherRole::Lead,
                                'status' => SubstituteRequestStatus::Approved,
                            ]);
                        }

                        $createdSessions->push($session);
                        $sessionNumber++;
                    }
                }

                $currentDate = $currentDate->addDay();
            }

            return $createdSessions;
        });
    }
}
