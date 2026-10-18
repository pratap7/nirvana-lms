<?php

declare(strict_types=1);

namespace App\Livewire\Certification;

use App\Domain\Certification\Models\Certificate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.student')]
class CertificateVerification extends Component
{
    public string $publicToken;

    public ?Certificate $certificate = null;

    public bool $isFound = false;

    public function mount(string $publicToken): void
    {
        $this->publicToken = $publicToken;

        /** @var ?Certificate $cert */
        $cert = Certificate::query()
            ->where('public_token', $publicToken)
            ->with(['user', 'course', 'event', 'reissuedCertificate'])
            ->first();

        if ($cert) {
            $this->certificate = $cert;
            $this->isFound = true;
        }
    }

    public function render(): View
    {
        return view('livewire.certification.certificate-verification', [
            'certificate' => $this->certificate,
            'isFound' => $this->isFound,
        ]);
    }
}
