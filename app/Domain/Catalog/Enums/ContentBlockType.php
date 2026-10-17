<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ContentBlockType: string
{
    case RichText = 'rich_text';
    case Video = 'video';
    case Pdf = 'pdf';
    case ExternalLink = 'external_link';

    public function label(): string
    {
        return match ($this) {
            self::RichText => 'Rich Text',
            self::Video => 'Video',
            self::Pdf => 'PDF',
            self::ExternalLink => 'External Link',
        };
    }
}
