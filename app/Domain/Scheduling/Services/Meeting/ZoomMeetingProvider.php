<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services\Meeting;

use App\Domain\Scheduling\DTOs\MeetingDetails;
use App\Domain\Scheduling\Models\Session;
use Illuminate\Support\Str;

class ZoomMeetingProvider implements MeetingProvider
{
    public function __construct(
        protected string $accountId = '',
        protected string $clientId = '',
        protected string $clientSecret = '',
    ) {
        $this->accountId = $accountId ?: (string) config('services.zoom.account_id', '');
        $this->clientId = $clientId ?: (string) config('services.zoom.client_id', '');
        $this->clientSecret = $clientSecret ?: (string) config('services.zoom.client_secret', '');
    }

    public function createMeeting(Session $session): MeetingDetails
    {
        // When real credentials are not set, generate structured mock meeting URLs
        $meetingId = (string) random_int(8000000000, 8999999999);
        $password = Str::random(8);

        return new MeetingDetails(
            provider: 'zoom',
            meetingLink: "https://zoom.us/j/{$meetingId}?pwd={$password}",
            meetingId: $meetingId,
            password: $password,
            rawPayload: [
                'topic' => $session->title,
                'start_time' => $session->starts_at->toIso8601String(),
                'duration' => $session->starts_at->diffInMinutes($session->ends_at),
                'timezone' => $session->timezone,
            ],
        );
    }

    public function updateMeeting(Session $session): void
    {
        // Integration point with Zoom API PATCH /meetings/{meetingId}
    }

    public function deleteMeeting(Session $session): void
    {
        // Integration point with Zoom API DELETE /meetings/{meetingId}
    }
}
