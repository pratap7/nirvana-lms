<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services\Meeting;

use App\Domain\Scheduling\DTOs\MeetingDetails;
use App\Domain\Scheduling\Models\Session;

interface MeetingProvider
{
    /**
     * Create live virtual meeting room for a scheduled session.
     */
    public function createMeeting(Session $session): MeetingDetails;

    /**
     * Update an existing meeting time or topic.
     */
    public function updateMeeting(Session $session): void;

    /**
     * Cancel/delete an existing meeting.
     */
    public function deleteMeeting(Session $session): void;
}
