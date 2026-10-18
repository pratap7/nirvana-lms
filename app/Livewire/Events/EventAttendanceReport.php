<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Domain\Events\Actions\ImportEventInvitationsAction;
use App\Domain\Events\Actions\IssueEventCertificateAction;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.moderator')]
class EventAttendanceReport extends Component
{
    public Event $event;

    // Active tab: 'attendance', 'invitations'
    public string $activeTab = 'attendance';

    // CSV invite text input
    public string $csvInviteData = '';

    public int $inviteExpiryDays = 14;

    // Filters
    public string $filterStatus = 'all'; // all, checked_in, pending

    // Feedback
    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(Event $event): void
    {
        $this->event = $event->load(['occurrences', 'invitations']);
    }

    public function importCsvInvitations(ImportEventInvitationsAction $importAction): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        if (trim($this->csvInviteData) === '') {
            $this->errorMessage = 'Please paste CSV data (e.g. email,name per line).';

            return;
        }

        try {
            $imported = $importAction->execute($this->event, $this->csvInviteData, $this->inviteExpiryDays);
            $this->feedbackMessage = "Successfully imported and generated {$imported->count()} signed single-use invitation links!";
            $this->csvInviteData = '';
            $this->event->refresh();
        } catch (\Throwable $e) {
            $this->errorMessage = 'CSV import failed: '.$e->getMessage();
        }
    }

    public function issueCertificateForRegistration(int $registrationId, IssueEventCertificateAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        /** @var ?EventRegistration $reg */
        $reg = EventRegistration::find($registrationId);
        if (! $reg) {
            return;
        }

        try {
            $cert = $action->execute($reg);
            $this->feedbackMessage = "Certificate #{$cert->certificate_number} issued for {$reg->user->full_name}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function bulkIssueCertificates(IssueEventCertificateAction $action): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        $eligibleRegistrations = $this->event->registrations()
            ->where('is_checked_in', true)
            ->get();

        $issuedCount = 0;
        foreach ($eligibleRegistrations as $reg) {
            try {
                $action->execute($reg);
                $issuedCount++;
            } catch (\Throwable) {
                // skip if already issued or error
            }
        }

        $this->feedbackMessage = "Bulk issuance completed: {$issuedCount} attendance certificates generated.";
    }

    public function render(): View
    {
        $query = $this->event->registrations()->with(['user', 'checkedInBy', 'enrollment']);

        if ($this->filterStatus === 'checked_in') {
            $query->where('is_checked_in', true);
        } elseif ($this->filterStatus === 'pending') {
            $query->where('is_checked_in', false);
        }

        /** @var Collection<int, EventRegistration> $registrations */
        $registrations = $query->latest()->get();

        $totalRegistered = $this->event->registrations()->count();
        $totalPlusOnes = $this->event->registrations()->where('has_plus_one', true)->count();
        $totalCheckedIn = $this->event->registrations()->where('is_checked_in', true)->count();
        $turnoutRate = $totalRegistered > 0 ? round(($totalCheckedIn / $totalRegistered) * 100, 1) : 0.0;

        $invitations = $this->event->invitations()->latest()->get();

        return view('livewire.events.event-attendance-report', [
            'registrations' => $registrations,
            'totalRegistered' => $totalRegistered,
            'totalPlusOnes' => $totalPlusOnes,
            'totalCheckedIn' => $totalCheckedIn,
            'turnoutRate' => $turnoutRate,
            'invitations' => $invitations,
        ]);
    }
}
