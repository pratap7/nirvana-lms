<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background queued job for processing large report exports.
 */
class ProcessReportExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600; // 10 minutes max for massive datasets

    public function __construct(public int $exportId) {}

    public function handle(ReportExportService $service): void
    {
        $export = ReportExport::find($this->exportId);

        if (! $export || $export->status === 'completed') {
            return;
        }

        $service->processExport($export);
    }
}
