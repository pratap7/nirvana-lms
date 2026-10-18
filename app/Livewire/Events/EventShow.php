<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Domain\Certification\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Events\Actions\IssueEventCertificateAction;
use App\Domain\Events\Actions\RegisterForEventAction;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventInvitation;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.student')]
class EventShow extends Component
{
    public Event $event;

    // Optional query parameters
    public ?string $inviteToken = null;

    // Registration Form Inputs
    public bool $hasPlusOne = false;

    public string $plusOneName = '';

    public string $plusOneEmail = '';

    // Guest registration (if not logged in)
    public string $guestFirstName = '';

    public string $guestLastName = '';

    public string $guestEmail = '';

    public string $guestPhone = '';

    // Feedback inputs
    public int $feedbackRating = 5;

    public int $feedbackNps = 10;

    public string $feedbackComments = '';

    public bool $feedbackSubmitted = false;

    // UI state
    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(Event $event): void
    {
        $this->event = $event->load(['occurrences', 'speakers.user', 'venue', 'room']);
        $this->inviteToken = request()->query('token');

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $this->guestFirstName = $user->first_name;
            $this->guestLastName = $user->last_name;
            $this->guestEmail = $user->email;
            $this->guestPhone = $user->phone ?? '';
        }
    }

    public function register(RegisterForEventAction $registerAction): void
    {
        $this->errorMessage = null;
        $this->feedbackMessage = null;

        // 1. Get or create registered user
        /** @var ?User $user */
        $user = Auth::user();
        if (! $user) {
            $this->validate([
                'guestFirstName' => 'required|string|max:50',
                'guestLastName' => 'required|string|max:50',
                'guestEmail' => 'required|email|max:100',
                'guestPhone' => 'nullable|string|max:20',
            ]);

            $user = User::query()->where('email', $this->guestEmail)->first();
            if (! $user) {
                $user = User::create([
                    'first_name' => $this->guestFirstName,
                    'last_name' => $this->guestLastName,
                    'email' => $this->guestEmail,
                    'phone' => $this->guestPhone,
                    'password' => bcrypt(str()->random(16)),
                ]);
                $user->assignRole('Student');
            }
        }

        // 2. Validate Plus-One if selected
        if ($this->hasPlusOne) {
            $this->validate([
                'plusOneName' => 'required|string|max:100',
                'plusOneEmail' => 'nullable|email|max:100',
            ]);
        }

        // 3. Validate Invitation if required
        $invitation = null;
        if ($this->event->isInvitationOnly()) {
            if (empty($this->inviteToken)) {
                $this->errorMessage = 'This event is strictly invitation-only. Please use your personal invitation link.';

                return;
            }

            $invitation = EventInvitation::query()
                ->where('event_id', $this->event->id)
                ->where('token', $this->inviteToken)
                ->first();

            if (! $invitation || ! $invitation->isValid()) {
                $this->errorMessage = 'Invalid, expired, or already used invitation token.';

                return;
            }
        }

        try {
            $result = $registerAction->execute(
                event: $this->event,
                user: $user,
                hasPlusOne: $this->hasPlusOne,
                plusOneName: $this->hasPlusOne ? $this->plusOneName : null,
                plusOneEmail: $this->hasPlusOne ? $this->plusOneEmail : null,
                invitation: $invitation
            );

            if ($result['status'] === EnrollmentStatus::PaymentPending && $result['invoice'] !== null) {
                $this->feedbackMessage = "Registration recorded! Invoice #{$result['invoice']->invoice_number} generated for payment.";
            } elseif ($result['status'] === EnrollmentStatus::Waitlisted) {
                $this->feedbackMessage = 'Capacity is full. You have been added to the priority waitlist.';
            } else {
                $this->feedbackMessage = 'Registration confirmed! Your QR admission ticket has been generated below.';
            }

            // Reload event data
            $this->event->refresh();
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Registration failed.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'An error occurred during registration: '.$e->getMessage();
        }
    }

    public function submitFeedback(): void
    {
        /** @var ?User $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        /** @var ?EventRegistration $registration */
        $registration = EventRegistration::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration) {
            return;
        }

        $this->event->feedbacks()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'registration_id' => $registration->id,
                'rating' => $this->feedbackRating,
                'recommendation_score' => $this->feedbackNps,
                'comments' => $this->feedbackComments,
            ]
        );

        $this->feedbackSubmitted = true;
        $this->feedbackMessage = 'Thank you for your feedback!';
    }

    public function claimCertificate(IssueEventCertificateAction $action): void
    {
        /** @var ?User $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        /** @var ?EventRegistration $registration */
        $registration = EventRegistration::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration) {
            $this->errorMessage = 'You are not registered for this event.';

            return;
        }

        try {
            $cert = $action->execute($registration);
            $this->feedbackMessage = "Certificate #{$cert->certificate_number} issued successfully!";
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Certificate cannot be issued.';
        }
    }

    public function render(): View
    {
        /** @var ?User $user */
        $user = Auth::user();

        /** @var ?EventRegistration $userRegistration */
        $userRegistration = $user
            ? EventRegistration::query()
                ->where('event_id', $this->event->id)
                ->where('user_id', $user->id)
                ->with(['occurrence', 'enrollment'])
                ->first()
            : null;

        /** @var ?Certificate $userCertificate */
        $userCertificate = $user
            ? Certificate::query()
                ->where('event_id', $this->event->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        return view('livewire.events.event-show', [
            'userRegistration' => $userRegistration,
            'userCertificate' => $userCertificate,
            'schemaJson' => $this->event->getSchemaOrgJson(),
        ]);
    }
}
