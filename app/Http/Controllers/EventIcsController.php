<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use Illuminate\Http\Response;

class EventIcsController extends Controller
{
    /**
     * Download RFC 5545 iCalendar (.ics) file for an event.
     */
    public function __invoke(Event $event): Response
    {
        $ics = $event->generateIcsContent();
        $filename = $event->slug.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
