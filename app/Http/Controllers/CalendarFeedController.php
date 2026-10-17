<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Live ICS calendar feed for Google Calendar, Apple Calendar, and Outlook subscriptions.
     */
    public function feed(string $token): Response
    {
        /** @var User|null $user */
        $user = User::where('calendar_token', $token)->first();

        if ($user === null) {
            abort(404, 'Invalid calendar feed subscription token.');
        }

        // Query sessions for this user (lead teacher, substitute, or all active if admin/moderator)
        $query = Session::query()
            ->where('status', '!=', SessionStatus::Cancelled)
            ->where('starts_at', '>=', now()->subMonths(6))
            ->where('starts_at', '<=', now()->addMonths(12))
            ->with(['batch', 'venue', 'room']);

        if (! $user->hasAnyRole(['Super Admin', 'Admin', 'Moderator', 'super_admin'])) {
            $query->where(function ($q) use ($user): void {
                $q->whereHas('sessionTeachers', function ($st) use ($user): void {
                    $st->where('user_id', $user->id);
                });
            });
        }

        $sessions = $query->orderBy('starts_at')->get();

        $vevents = [];
        foreach ($sessions as $session) {
            $vevents[] = $session->toIcsEvent();
        }

        $calendarName = 'Pramana Institute - '.addcslashes($user->full_name, ',;\\');

        $icsContent = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pramana Institute//LMS Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$calendarName}",
            'X-WR-TIMEZONE:Asia/Kolkata',
            implode("\r\n", $vevents),
            'END:VCALENDAR',
        ]);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="pramana-schedule.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Download single session .ics file for Add-to-Calendar.
     */
    public function downloadSessionIcs(Session $session): Response
    {
        $icsContent = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pramana Institute//LMS Session//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            $session->toIcsEvent(),
            'END:VCALENDAR',
        ]);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"session-{$session->ulid}.ics\"",
        ]);
    }

    /**
     * FullCalendar JSON event feed for student, teacher, and moderator panels.
     */
    public function eventsJson(Request $request): JsonResponse
    {
        $start = $request->query('start');
        $end = $request->query('end');

        $query = Session::query()
            ->with(['batch', 'venue', 'room', 'teachers']);

        if ($start) {
            $query->where('starts_at', '>=', $start);
        }

        if ($end) {
            $query->where('ends_at', '<=', $end);
        }

        $sessions = $query->get();

        $events = $sessions->map(fn (Session $s): array => [
            'id' => $s->id,
            'title' => $s->title,
            'start' => $s->starts_at->toIso8601String(),
            'end' => $s->ends_at->toIso8601String(),
            'url' => $s->meeting_link,
            'backgroundColor' => match ($s->status) {
                SessionStatus::Scheduled => '#0284c7', // Sky-600
                SessionStatus::InProgress => '#d97706', // Amber-600
                SessionStatus::Completed => '#16a34a', // Green-600
                SessionStatus::Cancelled => '#dc2626', // Red-600
                SessionStatus::Rescheduled => '#6b7280', // Gray-500
            },
            'borderColor' => '#00000000',
            'extendedProps' => [
                'batch_name' => $s->batch?->name,
                'is_online' => $s->is_online,
                'meeting_link' => $s->meeting_link,
                'room' => $s->room?->name,
                'status' => $s->status->value,
            ],
        ]);

        return response()->json($events);
    }
}
