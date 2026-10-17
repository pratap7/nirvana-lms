<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Session;
use App\Domain\Scheduling\Models\SessionRecording;
use App\Support\Actions\BaseAction;

class AttachSessionRecordingAction extends BaseAction
{
    /**
     * Attach a recording URL / archive to a completed session.
     */
    public function execute(
        Session $session,
        string $videoUrl,
        string $title = 'Session Recording',
        string $provider = 'bunny',
        ?int $durationSeconds = null,
        ?User $actor = null
    ): SessionRecording {
        $recording = SessionRecording::create([
            'session_id' => $session->id,
            'title' => $title,
            'provider' => $provider,
            'video_id_or_url' => $videoUrl,
            'duration_seconds' => $durationSeconds,
            'is_processed' => true,
        ]);

        $session->update([
            'recording_url' => $videoUrl,
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($session)
            ->withProperties([
                'recording_id' => $recording->id,
                'video_url' => $videoUrl,
            ])
            ->log('session_recording_attached');

        return $recording;
    }
}
