<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ResourceType: string
{
    case Pdf = 'pdf';
    case Audio = 'audio';
    case Link = 'link';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF Document',
            self::Audio => 'Audio / Guided Meditation',
            self::Link => 'External Resource Link',
            self::File => 'Downloadable Asset',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pdf => 'heroicon-o-document-text',
            self::Audio => 'heroicon-o-speaker-wave',
            self::Link => 'heroicon-o-link',
            self::File => 'heroicon-o-arrow-down-tray',
        };
    }
}
