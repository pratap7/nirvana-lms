<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Certification\Models\Certificate;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Support\Actions\BaseAction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueEventCertificateAction extends BaseAction
{
    /**
     * Issue an event completion certificate based on the attendance-only rule.
     */
    public function execute(EventRegistration $registration): Certificate
    {
        $event = $registration->event;
        if (! $event) {
            throw ValidationException::withMessages(['event' => 'Invalid event for registration.']);
        }

        // Attendance-only rule check
        if ($event->certificate_rule === 'attendance_only' && ! $registration->is_checked_in) {
            throw ValidationException::withMessages([
                'attendance' => 'Certificate cannot be issued: Attendee has not checked in to this event.',
            ]);
        }

        // Prevent duplicate certificate issuance
        $existing = Certificate::query()
            ->where('event_id', $event->id)
            ->where('user_id', $registration->user_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $year = now()->format('Y');
        $randomSeq = strtoupper(Str::random(6));
        $certNumber = "PRM-EVT-{$year}-{$randomSeq}";

        return Certificate::create([
            'certificate_number' => $certNumber,
            'user_id' => $registration->user_id,
            'event_id' => $event->id,
            'certifiable_type' => Event::class,
            'certifiable_id' => $event->id,
            'issue_date' => now()->toDateString(),
            'template_name' => 'event_attendance_mastery',
            'metadata' => [
                'event_title' => $event->title,
                'attendee_name' => $registration->user->full_name,
                'rule_applied' => 'attendance_only',
                'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
                'has_plus_one' => $registration->has_plus_one,
            ],
        ]);
    }
}
