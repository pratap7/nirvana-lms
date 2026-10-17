<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum UserStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
