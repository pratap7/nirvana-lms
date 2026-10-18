<?php

declare(strict_types=1);

namespace App\Domain\Learning\Services;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\URL;

class VideoStreamingService
{
    /**
     * Generate short-lived cryptographically signed streaming URL.
     */
    public function generateSignedUrl(Lesson $lesson, User $student, int $validMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'student.lessons.stream',
            now()->addMinutes($validMinutes),
            [
                'lesson' => $lesson->ulid,
                'user' => $student->ulid,
            ]
        );
    }

    /**
     * Generate dynamic anti-piracy watermark payload with student identity.
     *
     * @return array{text: string, user_id: string, email: string, timestamp: string}
     */
    public function getWatermarkData(User $student): array
    {
        return [
            'text' => sprintf('%s • %s', $student->full_name, $student->email),
            'user_id' => $student->ulid,
            'email' => $student->email,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
