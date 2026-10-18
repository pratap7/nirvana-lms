<?php

declare(strict_types=1);

namespace App\Domain\Certification\Jobs;

use App\Domain\Certification\Models\Certificate;
use App\Domain\Certification\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Certificate $certificate
    ) {}

    public function handle(): void
    {
        /** @var ?CertificateTemplate $template */
        $template = $this->certificate->template;
        if (! $template) {
            $template = CertificateTemplate::first() ?? new CertificateTemplate([
                'name' => 'Default Classical Template',
                'branding' => ['institute_name' => 'Pramana Institute of Yoga & Vedanta'],
            ]);
        }

        // Generate QR code SVG if not present
        if (empty($this->certificate->qr_code_svg)) {
            $this->certificate->generateQrCode();
        }

        $pdf = Pdf::loadView('certificates.pdf_template', [
            'certificate' => $this->certificate,
            'template' => $template,
        ])->setPaper('a4', 'landscape');

        $directory = 'certificates';
        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $filename = "{$directory}/{$this->certificate->ulid}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        $this->certificate->update([
            'pdf_path' => $filename,
        ]);
    }
}
