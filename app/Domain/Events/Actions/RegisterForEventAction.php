<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Billing\Actions\GenerateInvoiceAction;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Events\Events\EventTicketMailed;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventInvitation;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterForEventAction extends BaseAction
{
    /**
     * Register an attendee for an event (with optional guest/plus-one and payment/invite validation).
     *
     * @return array{registration: EventRegistration, invoice: ?Invoice, status: EnrollmentStatus}
     */
    public function execute(
        Event $event,
        User $user,
        bool $hasPlusOne = false,
        ?string $plusOneName = null,
        ?string $plusOneEmail = null,
        ?EventInvitation $invitation = null,
        ?EventOccurrence $occurrence = null
    ): array {
        return DB::transaction(function () use (
            $event,
            $user,
            $hasPlusOne,
            $plusOneName,
            $plusOneEmail,
            $invitation,
            $occurrence
        ): array {
            $seatsRequested = $hasPlusOne ? 2 : 1;

            // 1. Invitation-only validation
            if ($event->isInvitationOnly()) {
                if ($invitation === null || ! $invitation->isValid() || $invitation->event_id !== $event->id) {
                    throw ValidationException::withMessages([
                        'invitation' => 'A valid, unused single-use invitation is required to register for this event.',
                    ]);
                }
            }

            // 2. Capacity & Waitlist checks
            $isWaitlisted = false;
            if (! $event->hasCapacityAvailable($seatsRequested)) {
                if ($event->allowsWaitlist()) {
                    $isWaitlisted = true;
                } else {
                    throw ValidationException::withMessages([
                        'capacity' => 'This event has reached maximum capacity and the waitlist is closed.',
                    ]);
                }
            }

            // 3. Determine initial enrollment status
            $initialStatus = match (true) {
                $isWaitlisted => EnrollmentStatus::Waitlisted,
                $event->isPaid() => EnrollmentStatus::PaymentPending,
                default => EnrollmentStatus::Active,
            };

            // 4. Create polymorphic Enrollment record for state tracking
            /** @var Enrollment $enrollment */
            $enrollment = Enrollment::create([
                'student_id' => $user->id,
                'event_id' => $event->id,
                'enrollable_type' => Event::class,
                'enrollable_id' => $event->id,
                'status' => $initialStatus,
                'consent_given' => true,
                'consent_given_at' => now(),
                'prerequisites_verified' => true,
                'is_offline_registration' => false,
            ]);

            // 5. Create EventRegistration record
            /** @var EventRegistration $registration */
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence?->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'has_plus_one' => $hasPlusOne,
                'plus_one_name' => $hasPlusOne ? $plusOneName : null,
                'plus_one_email' => $hasPlusOne ? $plusOneEmail : null,
                'invitation_id' => $invitation?->id,
            ]);

            // Mark invitation as used
            if ($invitation !== null) {
                $invitation->markUsed();
            }

            // 6. Handle Billing for Paid Events
            $invoice = null;
            if ($event->isPaid() && ! $isWaitlisted) {
                $unitPrice = $event->fee_amount;
                $customItems = [
                    [
                        'description' => "Event Ticket: {$event->title}".($hasPlusOne ? ' (+1 Guest Pass)' : ''),
                        'quantity' => $seatsRequested,
                        'unit_price' => $unitPrice,
                    ],
                ];

                /** @var GenerateInvoiceAction $invoiceGenerator */
                $invoiceGenerator = app(GenerateInvoiceAction::class);
                $invoice = $invoiceGenerator->execute(
                    student: $user,
                    enrollment: $enrollment,
                    batch: null,
                    course: null,
                    customItems: $customItems,
                    metadata: [
                        'event_id' => $event->id,
                        'registration_id' => $registration->id,
                        'has_plus_one' => $hasPlusOne,
                    ]
                );

                $invoice->update([
                    'event_id' => $event->id,
                    'billable_type' => EventRegistration::class,
                    'billable_id' => $registration->id,
                ]);
            }

            // 7. If Free or Active, immediately generate QR ticket and fire event
            if ($initialStatus === EnrollmentStatus::Active) {
                $registration->generateQrCode();
                EventTicketMailed::dispatch($registration);
            }

            return [
                'registration' => $registration,
                'invoice' => $invoice,
                'status' => $initialStatus,
            ];
        });
    }
}
