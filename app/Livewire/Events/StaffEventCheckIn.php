<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.moderator')]
class StaffEventCheckIn extends Component
{
    public ?int $selectedEventId = null;

    public ?int $selectedOccurrenceId = null;

    // Scan / Lookup Input
    public string $scanInput = '';

    // Results feedback
    public ?string $successMessage = null;

    public ?string $duplicateWarning = null;

    public ?string $errorMessage = null;

    /** @var ?array<string, mixed> */
    public ?array $lastCheckedInAttendee = null;

    public function mount(?int $event = null): void
    {
        $firstEvent = $event ? Event::find($event) : Event::query()->where('status', 'published')->latest()->first();
        if ($firstEvent) {
            $this->selectedEventId = $firstEvent->id;
            $firstOccurrence = $firstEvent->occurrences()->first();
            $this->selectedOccurrenceId = $firstOccurrence?->id;
        }
    }

    public function processScan(): void
    {
        $this->successMessage = null;
        $this->duplicateWarning = null;
        $this->errorMessage = null;

        $code = trim($this->scanInput);
        if (empty($code)) {
            $this->errorMessage = 'Please enter or scan a valid ticket code.';

            return;
        }

        // Handle JSON payload from QR code if scanned via scanner
        if (str_starts_with($code, '{') && str_ends_with($code, '}')) {
            $decoded = json_decode($code, true);
            if (isset($decoded['ticket'])) {
                $code = (string) $decoded['ticket'];
            }
        }

        /** @var ?EventRegistration $registration */
        $registration = EventRegistration::query()
            ->where('ticket_code', $code)
            ->with(['event', 'user', 'checkedInBy'])
            ->first();

        if (! $registration) {
            // Also search by student name or email if ticket code wasn't exact
            $registration = EventRegistration::query()
                ->where('event_id', $this->selectedEventId)
                ->whereHas('user', function ($q) use ($code): void {
                    $q->where('email', $code)
                        ->orWhere('phone', $code);
                })
                ->with(['event', 'user', 'checkedInBy'])
                ->first();
        }

        if (! $registration) {
            $this->errorMessage = "Ticket #{$code} not found. Please verify the code or search by email.";

            return;
        }

        /** @var User $staff */
        $staff = Auth::user();

        // Perform check-in with offline duplicate check
        $result = $registration->checkIn($staff);

        if ($result['duplicate']) {
            $this->duplicateWarning = $result['message'];
        } else {
            $this->successMessage = $result['message'];
            $this->lastCheckedInAttendee = [
                'name' => $registration->user->full_name,
                'email' => $registration->user->email,
                'ticket_code' => $registration->ticket_code,
                'has_plus_one' => $registration->has_plus_one,
                'plus_one_name' => $registration->plus_one_name,
                'time' => now()->format('h:i:s A'),
            ];
        }

        $this->scanInput = '';
    }

    public function render(): View
    {
        $events = Event::query()->where('status', 'published')->latest()->get();

        $currentEvent = $this->selectedEventId ? Event::find($this->selectedEventId) : null;
        $occurrences = $currentEvent ? $currentEvent->occurrences : collect();

        $totalRegistered = 0;
        $totalCheckedIn = 0;
        $recentCheckIns = collect();

        if ($currentEvent) {
            $totalRegistered = $currentEvent->registrations()->count();
            $totalCheckedIn = $currentEvent->registrations()->where('is_checked_in', true)->count();

            $recentCheckIns = $currentEvent->registrations()
                ->where('is_checked_in', true)
                ->with(['user', 'checkedInBy'])
                ->latest('checked_in_at')
                ->take(10)
                ->get();
        }

        $attendanceRate = $totalRegistered > 0 ? round(($totalCheckedIn / $totalRegistered) * 100, 1) : 0.0;

        return view('livewire.events.staff-event-check-in', [
            'events' => $events,
            'currentEvent' => $currentEvent,
            'occurrences' => $occurrences,
            'totalRegistered' => $totalRegistered,
            'totalCheckedIn' => $totalCheckedIn,
            'attendanceRate' => $attendanceRate,
            'recentCheckIns' => $recentCheckIns,
        ]);
    }
}
