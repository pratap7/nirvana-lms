<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Models\User;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Enterprise Reporting Dashboard Livewire Component (Section H).
 * Permission-gated, date-filterable, multi-dimensional analytics.
 */
class ReportingDashboard extends Component
{
    public string $activeTab = 'funnel'; // funnel, revenue, dues, attendance, completion, workload, certification, events

    public string $datePreset = 'this_month'; // today, this_week, this_month, last_30_days, this_quarter, this_year, custom

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedCourseId = null;

    public string $selectedGateway = '';

    public ?string $lastExportDownloadUrl = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->can('report.view')) {
            abort(403, 'Unauthorized access to reporting dashboards.');
        }

        $this->applyPresetDates();
    }

    public function updatedDatePreset(): void
    {
        $this->applyPresetDates();
    }

    protected function applyPresetDates(): void
    {
        $now = now();

        match ($this->datePreset) {
            'today' => [
                $this->dateFrom = $now->toDateString(),
                $this->dateTo = $now->toDateString(),
            ],
            'this_week' => [
                $this->dateFrom = $now->copy()->startOfWeek()->toDateString(),
                $this->dateTo = $now->copy()->endOfWeek()->toDateString(),
            ],
            'this_month' => [
                $this->dateFrom = $now->copy()->startOfMonth()->toDateString(),
                $this->dateTo = $now->copy()->endOfMonth()->toDateString(),
            ],
            'last_30_days' => [
                $this->dateFrom = $now->copy()->subDays(30)->toDateString(),
                $this->dateTo = $now->toDateString(),
            ],
            'this_quarter' => [
                $this->dateFrom = $now->copy()->startOfQuarter()->toDateString(),
                $this->dateTo = $now->copy()->endOfQuarter()->toDateString(),
            ],
            'this_year' => [
                $this->dateFrom = $now->copy()->startOfYear()->toDateString(),
                $this->dateTo = $now->copy()->endOfYear()->toDateString(),
            ],
            default => null,
        };
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->flashMessage = null;
    }

    public function triggerExport(string $format = 'csv'): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->can('report.export')) {
            $this->flashMessage = 'You do not have permission to export reports.';

            return;
        }

        $reportType = match ($this->activeTab) {
            'funnel' => 'enrollment_funnel',
            'revenue' => 'revenue',
            'dues' => 'outstanding_dues',
            'attendance' => 'attendance_batch',
            'completion' => 'course_completion',
            'workload' => 'teacher_workload',
            'certification' => 'certificate_issuance',
            'events' => 'event_registrations',
            default => 'enrollment_funnel',
        };

        $filters = [
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'course_id' => $this->selectedCourseId,
            'gateway' => $this->selectedGateway ?: null,
        ];

        /** @var ReportExportService $exportService */
        $exportService = app(ReportExportService::class);
        $export = $exportService->queueExport($user, $reportType, $format, $filters, true);

        $this->lastExportDownloadUrl = $export->getDownloadUrl();
        $this->flashMessage = "Export generated successfully ({$export->row_count} rows). Ready for secure signed download.";
    }

    public function render(ReportingService $reportingService): View
    {
        $from = Carbon::parse($this->dateFrom ?: now()->subMonth()->toDateString());
        $to = Carbon::parse($this->dateTo ?: now()->toDateString());

        $courses = Course::query()->select('id', 'name')->orderBy('name')->get();

        $data = match ($this->activeTab) {
            'funnel' => $reportingService->getEnrollmentFunnel($from, $to, $this->selectedCourseId),
            'revenue' => $reportingService->getRevenueReport($from, $to, $this->selectedCourseId, $this->selectedGateway ?: null),
            'dues' => $reportingService->getOutstandingDues($from, $to),
            'attendance' => $reportingService->getAttendanceByBatch($from, $to, $this->selectedCourseId),
            'completion' => $reportingService->getCourseCompletion($from, $to, $this->selectedCourseId),
            'workload' => $reportingService->getTeacherWorkload($from, $to),
            'certification' => $reportingService->getCertificateIssuance($from, $to, $this->selectedCourseId),
            'events' => $reportingService->getEventRegistrations($from, $to),
            default => [],
        };

        $myRecentExports = ReportExport::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->take(5)
            ->get();

        return view('livewire.reporting.reporting-dashboard', [
            'data' => $data,
            'courses' => $courses,
            'myRecentExports' => $myRecentExports,
        ])->layout('components.layouts.app');
    }
}
