<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Models\Session;

class CalendarLinkGenerator
{
    /**
     * Generate "Add to Google Calendar" link.
     */
    public function google(Session $session): string
    {
        $dates = $session->starts_at->setTimezone('UTC')->format('Ymd\THis\Z').'/'.$session->ends_at->setTimezone('UTC')->format('Ymd\THis\Z');
        $params = [
            'action' => 'TEMPLATE',
            'text' => $session->title,
            'dates' => $dates,
            'details' => ($session->meeting_link ? "Join Link: {$session->meeting_link}\n\n" : '')."Batch: {$session->batch?->name}",
            'location' => $session->is_online ? 'Online' : "{$session->room?->name}, {$session->venue?->name}",
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    /**
     * Generate "Add to Outlook" link.
     */
    public function outlook(Session $session): string
    {
        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $session->title,
            'startdt' => $session->starts_at->toIso8601String(),
            'enddt' => $session->ends_at->toIso8601String(),
            'body' => ($session->meeting_link ? "Join Link: {$session->meeting_link}\n\n" : '')."Batch: {$session->batch?->name}",
            'location' => $session->is_online ? 'Online' : "{$session->room?->name}, {$session->venue?->name}",
        ];

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query($params);
    }

    /**
     * Generate direct link to download single session .ics file.
     */
    public function ics(Session $session): string
    {
        return route('calendar.session.ics', ['session' => $session->ulid]);
    }

    public static function googleUrl(Session $session): string
    {
        return (new self)->google($session);
    }

    public static function outlookUrl(Session $session): string
    {
        return (new self)->outlook($session);
    }
}
