<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Enums\SubstituteRequestStatus;
use App\Domain\Scheduling\Exceptions\ScheduleConflictException;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Models\Session;
use Carbon\CarbonInterface;

class ConflictDetector
{
    /**
     * Check if a room is already occupied during the requested interval.
     */
    public function detectRoomConflict(
        int $roomId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreSessionId = null
    ): ?string {
        $conflictingSession = Session::query()
            ->where('room_id', $roomId)
            ->where('status', '!=', SessionStatus::Cancelled)
            ->when($ignoreSessionId !== null, fn ($q) => $q->where('id', '!=', $ignoreSessionId))
            ->where(function ($q) use ($startsAt, $endsAt): void {
                $q->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->first();

        if ($conflictingSession === null) {
            return null;
        }

        $room = Room::find($roomId);
        $roomName = $room ? $room->name : "Room #{$roomId}";
        $startStr = $conflictingSession->starts_at->setTimezone($conflictingSession->timezone)->format('Y-m-d H:i');
        $endStr = $conflictingSession->ends_at->setTimezone($conflictingSession->timezone)->format('H:i T');

        return "Room [{$roomName}] is already booked for session '{$conflictingSession->title}' from {$startStr} to {$endStr}.";
    }

    /**
     * Check if a teacher is already scheduled for another active session during the requested interval.
     */
    public function detectTeacherConflict(
        int $teacherId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreSessionId = null
    ): ?string {
        $conflictingSession = Session::query()
            ->where('status', '!=', SessionStatus::Cancelled)
            ->when($ignoreSessionId !== null, fn ($q) => $q->where('id', '!=', $ignoreSessionId))
            ->whereHas('sessionTeachers', function ($q) use ($teacherId): void {
                $q->where('user_id', $teacherId)
                    ->where('status', SubstituteRequestStatus::Approved);
            })
            ->where(function ($q) use ($startsAt, $endsAt): void {
                $q->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->first();

        if ($conflictingSession === null) {
            return null;
        }

        $teacher = User::find($teacherId);
        $teacherName = $teacher ? $teacher->full_name : "Teacher #{$teacherId}";
        $startStr = $conflictingSession->starts_at->setTimezone($conflictingSession->timezone)->format('Y-m-d H:i');
        $endStr = $conflictingSession->ends_at->setTimezone($conflictingSession->timezone)->format('H:i T');

        return "Teacher [{$teacherName}] is already booked for session '{$conflictingSession->title}' from {$startStr} to {$endStr}.";
    }

    /**
     * Assert that neither the room nor the teacher is double-booked. Throws ScheduleConflictException if a conflict exists.
     *
     * @throws ScheduleConflictException
     */
    public function assertNoConflicts(
        ?int $roomId,
        ?int $teacherId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $ignoreSessionId = null
    ): void {
        if ($roomId !== null) {
            $roomConflict = $this->detectRoomConflict($roomId, $startsAt, $endsAt, $ignoreSessionId);
            if ($roomConflict !== null) {
                throw new ScheduleConflictException(
                    message: $roomConflict,
                    conflictType: 'room',
                    conflictingEntityId: $roomId,
                );
            }
        }

        if ($teacherId !== null) {
            $teacherConflict = $this->detectTeacherConflict($teacherId, $startsAt, $endsAt, $ignoreSessionId);
            if ($teacherConflict !== null) {
                throw new ScheduleConflictException(
                    message: $teacherConflict,
                    conflictType: 'teacher',
                    conflictingEntityId: $teacherId,
                );
            }
        }
    }
}
