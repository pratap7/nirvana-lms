<?php

declare(strict_types=1);

namespace App\Domain\Certification\Actions;

use App\Domain\Certification\Jobs\GenerateCertificatePdfJob;
use App\Domain\Certification\Models\Certificate;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;

class ReissueCertificateAction extends BaseAction
{
    /**
     * Supersede and reissue a certificate with old/new bidirectional linkage and audit trail.
     */
    public function execute(Certificate $oldCertificate, string $reason, User $reissuedBy): Certificate
    {
        $newCertificate = $oldCertificate->reissue($reason, $reissuedBy);

        GenerateCertificatePdfJob::dispatch($newCertificate);

        return $newCertificate;
    }
}
