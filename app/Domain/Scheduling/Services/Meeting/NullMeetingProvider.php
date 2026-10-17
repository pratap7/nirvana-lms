<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services\Meeting;

use App\Domain\Scheduling\DTOs\MeetingDetails;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Support\Str;

class NullMeetingProvider implements MeetingProvider
{
    public function createMeeting(Session $session): MeetingDetails
    {
        $meetingId = (string) random_int(100000000, 999999999);
        $passcode = Str::upper(Str::random(6));

        return new MeetingDetails(
            provider: 'test_provider',
            meetingLink: "https://meet.pramana-institute.org/room/{$session->slug}?pwd={$passcode}",
            meetingId: $meetingId,
            password: $passcode,
            rawPayload: ['session_id' => $session->id],
        );
    }

    public function updateMeeting(Session $session): void
    {
        // No-op for testing/local
    }

    public function deleteMeeting(Session $session): void
    {
        // No-op for testing/local
    }
}
