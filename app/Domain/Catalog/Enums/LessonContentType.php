<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum LessonContentType: string
{
    case Video = 'video';
    case Text = 'text';
    case Pdf = 'pdf';
    case Quiz = 'quiz';
    case LiveSession = 'live_session';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Text => 'Text / Article',
            self::Pdf => 'PDF Document',
            self::Quiz => 'Quiz',
            self::LiveSession => 'Live Session',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Video => 'heroicon-o-play-circle',
            self::Text => 'heroicon-o-document-text',
            self::Pdf => 'heroicon-o-document',
            self::Quiz => 'heroicon-o-question-mark-circle',
            self::LiveSession => 'heroicon-o-video-camera',
        };
    }
}
