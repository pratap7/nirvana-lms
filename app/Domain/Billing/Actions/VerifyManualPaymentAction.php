<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\MakerCheckerViolationException;
use App\Domain\Billing\Models\Payment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionResolver;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;

class VerifyManualPaymentAction extends BaseAction
{
    public function __construct(
        protected PermissionResolver $permissionResolver
    ) {}

    /**
     * Enforce maker-checker segregation of duties and verify manual payment.
     *
     * @throws MakerCheckerViolationException
     */
    public function execute(
        Payment $payment,
        User $checker,
        bool $approved = true,
        ?string $notes = null
    ): Payment {
        // 1. Strict Maker-Checker check: the same user who recorded the payment CANNOT verify it
        if ($payment->recorded_by !== null && $payment->recorded_by === $checker->id) {
            throw MakerCheckerViolationException::sameUser($checker->id);
        }

        // 2. Permission check: the checker must possess 'payment.verify'
        $hasPermission = $this->permissionResolver->can($checker, 'payment.verify') || $checker->can('payment.verify');
        if (! $hasPermission) {
            throw MakerCheckerViolationException::missingPermission($checker->id);
        }

        return DB::transaction(function () use ($payment, $checker, $approved, $notes): Payment {
            if ($approved) {
                $payment->update([
                    'status' => PaymentStatus::Verified,
                    'verified_by' => $checker->id,
                    'verified_at' => now(),
                    'verification_notes' => $notes ?? $payment->verification_notes,
                ]);

                // Recalculate invoice status and trigger enrollment activation if fully settled
                MarkInvoicePaidAction::run($payment->invoice);
            } else {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'verified_by' => $checker->id,
                    'verified_at' => now(),
                    'verification_notes' => $notes,
                ]);

                $payment->invoice->recalculateStatus();
            }

            return $payment->fresh(['invoice', 'recorder', 'verifier']);
        });
    }
}
