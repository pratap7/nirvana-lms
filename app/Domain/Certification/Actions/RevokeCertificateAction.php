<?php

declare(strict_types=1);

namespace App\Domain\Certification\Actions;

use App\Domain\Certification\Models\Certificate;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;

class RevokeCertificateAction extends BaseAction
{
    /**
     * Revoke a certificate with mandatory reason and audit trail.
     */
    public function execute(Certificate $certificate, string $reason, User $revokedBy): Certificate
    {
        $certificate->revoke($reason, $revokedBy);

        return $certificate->fresh();
    }
}
