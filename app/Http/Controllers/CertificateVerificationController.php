<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Certification\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateVerificationController extends Controller
{
    /**
     * Serve standard Open Badges 2.0 / 3.0 verifiable credential assertion JSON.
     */
    public function openBadgeJson(string $publicToken): JsonResponse
    {
        /** @var ?Certificate $cert */
        $cert = Certificate::query()->where('public_token', $publicToken)->firstOrFail();

        return response()->json($cert->getOpenBadgesJson(), 200, [
            'Content-Type' => 'application/ld+json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Download generated Certificate PDF.
     */
    public function downloadPdf(string $publicToken): Response|BinaryFileResponse
    {
        /** @var ?Certificate $cert */
        $cert = Certificate::query()->where('public_token', $publicToken)->firstOrFail();

        if ($cert->pdf_path && Storage::disk('public')->exists($cert->pdf_path)) {
            $path = Storage::disk('public')->path($cert->pdf_path);

            return response()->download($path, "{$cert->certificate_number}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Generate on the fly if not yet queued
        $pdf = Pdf::loadView('certificates.pdf_template', [
            'certificate' => $cert,
            'template' => $cert->template,
        ])->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$cert->certificate_number}.pdf\"",
        ]);
    }
}
