<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services\Meeting;

use App\Domain\Scheduling\DTOs\MeetingDetails;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Support\Str;

class GoogleMeetProvider implements MeetingProvider
{
    public function createMeeting(Session $session): MeetingDetails
    {
        $code = strtolower(Str::random(3).'-'.Str::random(4).'-'.Str::random(3));

        return new MeetingDetails(
            provider: 'google_meet',
            meetingLink: "https://meet.google.com/{$code}",
            meetingId: $code,
            rawPayload: [
                'summary' => $session->title,
                'conferenceData' => [
                    'entryPoints' => [
                        [
                            'entryPointType' => 'video',
                            'uri' => "https://meet.google.com/{$code}",
                        ],
                    ],
                ],
            ],
        );
    }

    public function updateMeeting(Session $session): void
    {
        // Google Calendar API Event Patch
    }

    public function deleteMeeting(Session $session): void
    {
        // Google Calendar API Event Delete
    }
}
