<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Reporting\Mail\WeeklyAdminSummaryMail;
use App\Domain\Reporting\Services\ReportingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Scheduled command to dispatch weekly executive summary reports to Admins.
 */
class SendWeeklyAdminReportCommand extends Command
{
    protected $signature = 'lms:send-weekly-admin-report {--email= : Optional single email destination}';

    protected $description = 'Generate and send the weekly operational & financial executive summary to Admin';

    public function handle(ReportingService $reportingService): int
    {
        $startOfWeek = now()->subWeek()->startOfWeek();
        $endOfWeek = now()->subWeek()->endOfWeek();

        $this->info("Compiling weekly executive summary for window: {$startOfWeek->toDateString()} to {$endOfWeek->toDateString()}");

        $summaryData = $reportingService->getWeeklySummary($startOfWeek, $endOfWeek);

        $specificEmail = $this->option('email');

        if ($specificEmail) {
            Mail::to($specificEmail)->send(new WeeklyAdminSummaryMail($summaryData));
            $this->info("Dispatched weekly summary to: {$specificEmail}");

            return self::SUCCESS;
        }

        // Fetch all Admins and Super Admins
        $admins = User::role(['Admin', 'Super Admin'])->get();

        if ($admins->isEmpty()) {
            // Fallback to configured institute admin email
            $fallbackEmail = config('mail.from.address', 'admin@pramana-institute.edu');
            Mail::to($fallbackEmail)->send(new WeeklyAdminSummaryMail($summaryData));
            $this->info("No admin users found with role. Sent to fallback address: {$fallbackEmail}");

            return self::SUCCESS;
        }

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new WeeklyAdminSummaryMail($summaryData));
            $this->info("Dispatched to admin: {$admin->email}");
        }

        return self::SUCCESS;
    }
}
