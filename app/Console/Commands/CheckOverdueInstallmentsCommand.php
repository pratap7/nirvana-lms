<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Models\Installment;
use App\Domain\Enrollment\Actions\TransitionEnrollmentAction;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CheckOverdueInstallmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:check-overdue-installments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag overdue installments past grace period, send reminders, and suspend enrollments if configured.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $this->info("Checking overdue installments as of {$now->toIso8601String()}...");

        $pendingInstallments = Installment::query()
            ->with(['plan', 'invoice.enrollment'])
            ->whereNull('paid_at')
            ->where('grace_date', '<', $now)
            ->where('is_overdue', false)
            ->get();

        $flaggedCount = 0;
        $suspendedCount = 0;

        foreach ($pendingInstallments as $installment) {
            $installment->update(['is_overdue' => true]);
            $flaggedCount++;

            // Suspension of enrollment upon overdue installment
            if ($installment->invoice && $installment->invoice->enrollment) {
                $enrollment = $installment->invoice->enrollment;
                if ($enrollment->status === EnrollmentStatus::Active) {
                    try {
                        TransitionEnrollmentAction::run(
                            $enrollment,
                            EnrollmentStatus::Dropped,
                            actor: null,
                            reason: "Enrollment suspended due to overdue installment #{$installment->installment_number} on invoice {$installment->invoice->invoice_number}."
                        );
                        $suspendedCount++;
                    } catch (\Throwable $e) {
                        $this->warn("Failed to suspend enrollment [{$enrollment->id}]: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("Completed. Flagged {$flaggedCount} overdue installment(s); suspended {$suspendedCount} enrollment(s).");

        return self::SUCCESS;
    }
}
