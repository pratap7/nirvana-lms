<?php

declare(strict_types=1);

namespace App\Domain\Communication\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum NotificationChannel: string
{
    use HasEnumHelpers;

    case Mail = 'mail';
    case Database = 'database';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email (Direct Mail)',
            self::Database => 'In-App Bell (Database Notification)',
            self::WhatsApp => 'WhatsApp Business API',
            self::Sms => 'SMS Gateway',
        };
    }
}
