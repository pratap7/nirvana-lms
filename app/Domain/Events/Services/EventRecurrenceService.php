<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\OccurrenceStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Expands recurring RRULEs or generates occurrences for one-time events.
 */
class EventRecurrenceService
{
    /**
     * Generate or expand occurrences for an event.
     *
     * @return Collection<int, EventOccurrence>
     */
    public function generateOccurrences(Event $event): Collection
    {
        // 1. If not recurring, create single occurrence matching event dates
        if (! $event->is_recurring || empty($event->rrule)) {
            $occurrence = EventOccurrence::create([
                'event_id' => $event->id,
                'occurrence_number' => 1,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'venue_id' => $event->venue_id,
                'room_id' => $event->room_id,
                'online_meeting_link' => $event->online_meeting_link,
                'status' => OccurrenceStatus::Scheduled,
            ]);

            return collect([$occurrence]);
        }

        // 2. Parse RRULE string
        $params = $this->parseRrule($event->rrule);
        $occurrences = collect();

        $freq = strtoupper($params['FREQ'] ?? 'WEEKLY');
        $count = isset($params['COUNT']) ? (int) $params['COUNT'] : 4;
        $interval = isset($params['INTERVAL']) ? max(1, (int) $params['INTERVAL']) : 1;
        $byDay = isset($params['BYDAY']) ? explode(',', strtoupper($params['BYDAY'])) : [];

        $durationMinutes = $event->ends_at->diffInMinutes($event->starts_at);
        $currentStart = Carbon::parse($event->starts_at);
        $occurrenceNumber = 1;

        if ($freq === 'DAILY') {
            for ($i = 0; $i < $count; $i++) {
                $start = $currentStart->copy()->addDays($i * $interval);
                $end = $start->copy()->addMinutes($durationMinutes);

                $occurrences->push(EventOccurrence::create([
                    'event_id' => $event->id,
                    'occurrence_number' => $occurrenceNumber++,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'venue_id' => $event->venue_id,
                    'room_id' => $event->room_id,
                    'online_meeting_link' => $event->online_meeting_link,
                    'status' => OccurrenceStatus::Scheduled,
                ]));
            }
        } elseif ($freq === 'WEEKLY') {
            if (empty($byDay)) {
                for ($i = 0; $i < $count; $i++) {
                    $start = $currentStart->copy()->addWeeks($i * $interval);
                    $end = $start->copy()->addMinutes($durationMinutes);

                    $occurrences->push(EventOccurrence::create([
                        'event_id' => $event->id,
                        'occurrence_number' => $occurrenceNumber++,
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'venue_id' => $event->venue_id,
                        'room_id' => $event->room_id,
                        'online_meeting_link' => $event->online_meeting_link,
                        'status' => OccurrenceStatus::Scheduled,
                    ]));
                }
            } else {
                // Expand across specific weekdays (e.g. MO,WE,FR or SA,SU)
                $dayMap = [
                    'MO' => CarbonInterface::MONDAY,
                    'TU' => CarbonInterface::TUESDAY,
                    'WE' => CarbonInterface::WEDNESDAY,
                    'TH' => CarbonInterface::THURSDAY,
                    'FR' => CarbonInterface::FRIDAY,
                    'SA' => CarbonInterface::SATURDAY,
                    'SU' => CarbonInterface::SUNDAY,
                ];

                $cursor = $currentStart->copy();
                $generated = 0;

                while ($generated < $count) {
                    foreach ($byDay as $dayCode) {
                        if (! isset($dayMap[$dayCode])) {
                            continue;
                        }

                        $targetDayOfWeek = $dayMap[$dayCode];
                        $start = $cursor->copy()->next($targetDayOfWeek)->setTimeFrom($currentStart);
                        $end = $start->copy()->addMinutes($durationMinutes);

                        $occurrences->push(EventOccurrence::create([
                            'event_id' => $event->id,
                            'occurrence_number' => $occurrenceNumber++,
                            'starts_at' => $start,
                            'ends_at' => $end,
                            'venue_id' => $event->venue_id,
                            'room_id' => $event->room_id,
                            'online_meeting_link' => $event->online_meeting_link,
                            'status' => OccurrenceStatus::Scheduled,
                        ]));

                        $generated++;
                        if ($generated >= $count) {
                            break;
                        }
                    }
                    $cursor->addWeeks($interval);
                }
            }
        } elseif ($freq === 'MONTHLY') {
            for ($i = 0; $i < $count; $i++) {
                $start = $currentStart->copy()->addMonths($i * $interval);
                $end = $start->copy()->addMinutes($durationMinutes);

                $occurrences->push(EventOccurrence::create([
                    'event_id' => $event->id,
                    'occurrence_number' => $occurrenceNumber++,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'venue_id' => $event->venue_id,
                    'room_id' => $event->room_id,
                    'online_meeting_link' => $event->online_meeting_link,
                    'status' => OccurrenceStatus::Scheduled,
                ]));
            }
        }

        return $occurrences;
    }

    /**
     * @return array<string, string>
     */
    private function parseRrule(string $rrule): array
    {
        $parts = explode(';', trim($rrule));
        $params = [];
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $params[strtoupper(trim($k))] = trim($v);
            }
        }

        return $params;
    }
}
