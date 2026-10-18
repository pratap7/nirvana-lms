<?php

declare(strict_types=1);

namespace App\Domain\Certification\Enums;

use App\Support\Concerns\HasEnumHelpers;

enum CertificateStatus: string
{
    use HasEnumHelpers;

    case Valid = 'valid';
    case Revoked = 'revoked';
    case Reissued = 'reissued';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid & Verified',
            self::Revoked => 'Revoked',
            self::Reissued => 'Superseded / Reissued',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Valid => 'bg-emerald-950 text-emerald-400 border border-emerald-800',
            self::Revoked => 'bg-rose-950 text-rose-400 border border-rose-800',
            self::Reissued => 'bg-amber-950 text-amber-400 border border-amber-800',
        };
    }
}
