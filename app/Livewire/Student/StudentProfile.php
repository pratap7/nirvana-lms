<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Identity\Models\StudentProfile as StudentProfileModel;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDeletionRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.student')]
class StudentProfile extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $timezone = 'Asia/Kolkata';

    public string $locale = 'en';

    // Emergency Contact
    public string $emergency_contact_name = '';

    public string $emergency_contact_phone = '';

    public string $emergency_contact_relationship = '';

    public string $medical_conditions = '';

    public string $allergies = '';

    public string $dietary_requirements = '';

    // Channel Notification toggles: channel => [type => bool]
    /** @var array<string, array<string, bool>> */
    public array $notifications = [
        'email' => [
            'class_reminder' => true,
            'announcement' => true,
            'assignment_due' => true,
            'payment_reminder' => true,
        ],
        'whatsapp' => [
            'class_reminder' => true,
            'announcement' => false,
            'assignment_due' => true,
            'payment_reminder' => true,
        ],
        'sms' => [
            'class_reminder' => false,
            'announcement' => false,
            'assignment_due' => false,
            'payment_reminder' => true,
        ],
    ];

    public ?string $exportRequestedAt = null;

    public ?string $feedbackMessage = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->phone = $user->phone ?? '';
        $this->timezone = $user->timezone ?? 'Asia/Kolkata';
        $this->locale = $user->locale ?? 'en';

        /** @var ?StudentProfileModel $profile */
        $profile = $user->studentProfile;
        if ($profile) {
            $this->emergency_contact_name = (string) ($profile->emergency_contact_name ?? '');
            $this->emergency_contact_phone = (string) ($profile->emergency_contact_phone ?? '');
            $this->emergency_contact_relationship = (string) ($profile->emergency_contact_relationship ?? '');
            $this->medical_conditions = (string) ($profile->medical_conditions ?? '');
            $this->allergies = (string) ($profile->allergies ?? '');
            $this->dietary_requirements = (string) ($profile->dietary_requirements ?? '');
        }

        // Load notification preferences
        $prefs = NotificationPreference::query()->where('user_id', $user->id)->get();
        foreach ($prefs as $pref) {
            if (isset($this->notifications[$pref->channel][$pref->notification_type])) {
                $this->notifications[$pref->channel][$pref->notification_type] = $pref->is_enabled;
            }
        }

        // Check if pending export request
        /** @var ?UserDeletionRequest $req */
        $req = UserDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('reason', 'DATA_EXPORT')
            ->latest()
            ->first();

        if ($req) {
            $this->exportRequestedAt = $req->requested_at->format('M d, Y H:i');
        }
    }

    public function saveProfile(): void
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'required|string|timezone',
            'locale' => 'required|string|in:en,hi,sa,ta,te',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:50',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone ?: null,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
        ]);

        StudentProfileModel::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'emergency_contact_name' => $this->emergency_contact_name ?: null,
                'emergency_contact_phone' => $this->emergency_contact_phone ?: null,
                'emergency_contact_relationship' => $this->emergency_contact_relationship ?: null,
                'medical_conditions' => $this->medical_conditions ?: null,
                'allergies' => $this->allergies ?: null,
                'dietary_requirements' => $this->dietary_requirements ?: null,
            ]
        );

        $this->feedbackMessage = 'Profile information updated successfully.';
    }

    public function saveNotificationPreferences(): void
    {
        /** @var User $user */
        $user = Auth::user();

        foreach ($this->notifications as $channel => $types) {
            foreach ($types as $type => $enabled) {
                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'channel' => $channel,
                        'notification_type' => $type,
                    ],
                    [
                        'is_enabled' => (bool) $enabled,
                    ]
                );
            }
        }

        $this->feedbackMessage = 'Notification preferences updated.';
    }

    public function requestDataExport(): void
    {
        /** @var User $user */
        $user = Auth::user();

        UserDeletionRequest::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'reason' => 'DATA_EXPORT',
            'requested_at' => CarbonImmutable::now(),
        ]);

        $this->exportRequestedAt = CarbonImmutable::now()->format('M d, Y H:i');
        $this->feedbackMessage = 'Your data export request has been lodged. A downloadable archive will be prepared and sent to your registered email.';
    }

    public function render(): View
    {
        return view('livewire.student.student-profile', [
            'user' => Auth::user(),
        ]);
    }
}
