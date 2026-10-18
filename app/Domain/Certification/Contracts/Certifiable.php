<?php

declare(strict_types=1);

namespace App\Domain\Certification\Contracts;

interface Certifiable
{
    public function getCertificateTitle(): string;

    public function getCertificateRule(): string;
}
