<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum LessonContentType: string
{
    case Video = 'video';
    case Text = 'text';
    case Reading = 'reading';
    case Pdf = 'pdf';
    case Audio = 'audio';
    case Quiz = 'quiz';
    case LiveSession = 'live_session';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Text, self::Reading => 'Reading / Article',
            self::Pdf => 'PDF Document',
            self::Audio => 'Audio (Guided Meditation)',
            self::Quiz => 'Quiz',
            self::LiveSession => 'Live Session',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Video => 'heroicon-o-play-circle',
            self::Text, self::Reading => 'heroicon-o-document-text',
            self::Pdf => 'heroicon-o-document',
            self::Audio => 'heroicon-o-speaker-wave',
            self::Quiz => 'heroicon-o-question-mark-circle',
            self::LiveSession => 'heroicon-o-video-camera',
        };
    }
}
