<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Commands;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Reporting\Models\ReportDailyAggregate;
use App\Domain\Scheduling\Models\SessionAttendance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled job to refresh read-optimized summary tables (Section H).
 */
class RefreshReportingSummariesCommand extends Command
{
    protected $signature = 'lms:refresh-reporting-summaries {--date= : The date to summarize (YYYY-MM-DD), defaults to yesterday}';

    protected $description = 'Aggregate daily operational and financial metrics into read-optimized summary tables';

    public function handle(): int
    {
        $targetDateStr = $this->option('date') ?: now()->subDay()->toDateString();
        $date = CarbonImmutable::parse($targetDateStr);

        $this->info("Refreshing reporting summaries for: {$date->toDateString()}");

        $this->refreshEnrollments($date);
        $this->refreshRevenue($date);
        $this->refreshAttendance($date);
        $this->refreshEventRegistrations($date);

        $this->info('Reporting summaries refreshed successfully.');

        return self::SUCCESS;
    }

    protected function refreshEnrollments(CarbonInterface $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $counts = Enrollment::query()
            ->whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = $counts->sum();

        ReportDailyAggregate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_group' => 'enrollment',
                'dimension_key' => 'all',
            ],
            [
                'numeric_value' => (float) $total,
                'secondary_value' => (float) ($counts[EnrollmentStatus::Active->value] ?? 0),
                'metadata' => $counts->toArray(),
            ]
        );
    }

    protected function refreshRevenue(CarbonInterface $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalPaise = $payments->sum('amount');
        $totalAmount = round($totalPaise / 100, 2);

        $byGateway = $payments->groupBy('gateway')->map(fn ($g) => round($g->sum('amount') / 100, 2));

        ReportDailyAggregate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_group' => 'revenue',
                'dimension_key' => 'all',
            ],
            [
                'numeric_value' => (float) $totalAmount,
                'secondary_value' => (float) $payments->count(),
                'metadata' => [
                    'by_gateway' => $byGateway->toArray(),
                ],
            ]
        );
    }

    protected function refreshAttendance(CarbonInterface $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $attendances = SessionAttendance::query()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $total = $attendances->count();
        $present = $attendances->whereIn('status', ['present', 'late'])->count();
        $rate = $total > 0 ? round(($present / $total) * 100, 2) : 0.0;

        ReportDailyAggregate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_group' => 'attendance',
                'dimension_key' => 'all',
            ],
            [
                'numeric_value' => (float) $rate,
                'secondary_value' => (float) $total,
                'metadata' => [
                    'present_count' => $present,
                    'absent_count' => $attendances->where('status', 'absent')->count(),
                ],
            ]
        );
    }

    protected function refreshEventRegistrations(CarbonInterface $date): void
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $regs = EventRegistration::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        ReportDailyAggregate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'metric_group' => 'event',
                'dimension_key' => 'all',
            ],
            [
                'numeric_value' => (float) $regs,
                'secondary_value' => 0.0,
                'metadata' => ['registrations' => $regs],
            ]
        );
    }
}
