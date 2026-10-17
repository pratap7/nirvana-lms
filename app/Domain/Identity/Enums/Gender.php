<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case NonBinary = 'non_binary';
    case PreferNotToSay = 'prefer_not_to_say';
}
