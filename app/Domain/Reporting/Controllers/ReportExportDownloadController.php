<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Controllers;

use App\Domain\Reporting\Models\ReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for downloading exported reports via temporary signed URLs.
 */
class ReportExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $export): BinaryFileResponse
    {
        // 1. Signature validity checked by signed middleware

        // 2. Fetch export by ULID or ID
        $reportExport = ReportExport::where('ulid', $export)
            ->orWhere('id', is_numeric($export) ? (int) $export : 0)
            ->firstOrFail();

        // 3. Verify expiry
        if ($reportExport->isExpired()) {
            abort(410, 'This report export download link has expired.');
        }

        // 4. Verify completed status and file presence
        if ($reportExport->status !== 'completed' || ! $reportExport->file_path) {
            abort(404, 'Export file is not ready or failed processing.');
        }

        $absolutePath = storage_path('app/'.$reportExport->file_path);

        if (! file_exists($absolutePath)) {
            abort(404, 'Report file was not found on server.');
        }

        // 5. Authorization: Ensure current user is owner or has report.export permission
        $user = $request->user();
        if ($user && $user->id !== $reportExport->user_id && ! $user->can('report.export')) {
            abort(403, 'Unauthorized to download this report.');
        }

        return response()->download($absolutePath, $reportExport->file_name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
