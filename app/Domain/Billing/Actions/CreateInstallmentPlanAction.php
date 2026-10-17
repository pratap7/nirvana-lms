<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\InstallmentPlan;
use App\Domain\Billing\Models\Invoice;
use App\Support\Actions\BaseAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateInstallmentPlanAction extends BaseAction
{
    /**
     * Create an installment plan splitting an invoice into scheduled milestones.
     */
    public function execute(
        Invoice $invoice,
        int $numberOfInstallments = 3,
        int $intervalDays = 30,
        int $gracePeriodDays = 5,
        bool $autoSuspendOnOverdue = false
    ): InstallmentPlan {
        return DB::transaction(function () use (
            $invoice,
            $numberOfInstallments,
            $intervalDays,
            $gracePeriodDays
        ): InstallmentPlan {
            $totalAmount = $invoice->total_amount;
            $baseAmount = intdiv($totalAmount, $numberOfInstallments);
            $remainder = $totalAmount % $numberOfInstallments;

            $plan = InstallmentPlan::create([
                'invoice_id' => $invoice->id,
                'total_installments' => $numberOfInstallments,
                'frequency_days' => $intervalDays,
                'grace_days' => $gracePeriodDays,
                'status' => 'active',
            ]);

            $now = CarbonImmutable::now();

            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $installmentAmount = $baseAmount + ($i === 1 ? $remainder : 0);
                $dueDate = $i === 1 ? $now->addDays(3)->endOfDay() : $now->addDays($intervalDays * ($i - 1))->endOfDay();
                $graceDate = $dueDate->addDays($gracePeriodDays)->endOfDay();

                Installment::create([
                    'installment_plan_id' => $plan->id,
                    'invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'amount' => $installmentAmount,
                    'currency' => $invoice->currency,
                    'due_date' => $dueDate,
                    'grace_date' => $graceDate,
                    'is_overdue' => false,
                ]);
            }

            return $plan->fresh(['installments', 'invoice']);
        });
    }
}
