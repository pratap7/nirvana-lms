<?php

declare(strict_types=1);

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Enums\NotificationChannel;
use App\Domain\Communication\Enums\OutboxStatus;
use App\Domain\Communication\Jobs\ProcessNotificationOutboxJob;
use App\Domain\Communication\Models\NotificationOutbox;
use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Communication\Models\NotificationTemplate;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Event-Driven Multi-Channel Notification Service (Section H).
 * Enforces per-user channel preferences, timezone-aware quiet hours,
 * mandatory notice overrides (payments & safety), and outbox tracking.
 */
class NotificationService
{
    /**
     * Dispatch multi-channel notifications for a user based on template rules and preferences.
     *
     * @param  array<string, mixed>  $variables  Variables for template interpolation
     * @param  string|null  $idempotencyPrefix  Prefix to guarantee exactly-once delivery per channel
     * @return Collection<int, NotificationOutbox> Outbox records created or queued
     */
    public function send(
        User $user,
        string $templateKey,
        array $variables = [],
        ?string $idempotencyPrefix = null,
        bool $sync = true
    ): Collection {
        /** @var ?NotificationTemplate $template */
        $template = NotificationTemplate::query()->where('key', $templateKey)->first();

        // If template doesn't exist yet in DB, create standard fallback definition
        if (! $template) {
            $template = $this->createFallbackTemplate($templateKey);
        }

        $channels = $template->channels ?? ['mail', 'database', 'whatsapp'];
        $createdOutboxes = collect();

        foreach ($channels as $channelStr) {
            $channelEnum = NotificationChannel::tryFrom($channelStr);
            if (! $channelEnum) {
                continue;
            }

            // 1. Check user channel preference (unless mandatory notice like payments/safety)
            if (! $template->is_mandatory && ! $this->isChannelEnabledForUser($user, $channelEnum, $template->category)) {
                continue; // User unsubscribed / disabled this channel
            }

            // 2. Resolve recipient address
            $recipient = $this->resolveRecipient($user, $channelEnum);
            if (! $recipient) {
                continue;
            }

            // 3. Idempotency Key check (prevents duplicate dispatch)
            $idempotencyKey = $idempotencyPrefix
                ? "{$idempotencyPrefix}_{$channelEnum->value}"
                : null;

            if ($idempotencyKey) {
                $existing = NotificationOutbox::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    $createdOutboxes->push($existing);

                    continue; // Already queued or delivered!
                }
            }

            // 4. Render content for this channel and user's preferred locale
            $locale = $user->locale ?? 'en';
            $rendered = $template->render($channelEnum->value, $locale, array_merge([
                'student_name' => $user->full_name,
                'email' => $user->email,
            ], $variables));

            // 5. Quiet hours check (unless mandatory notice)
            $isQuiet = ! $template->is_mandatory && $this->isInQuietHours($user);
            $status = $isQuiet ? OutboxStatus::HeldForQuietHours : OutboxStatus::Queued;
            $scheduledAt = $isQuiet ? $this->getQuietHoursEndDateTime($user) : now();

            /** @var NotificationOutbox $outbox */
            $outbox = NotificationOutbox::create([
                'user_id' => $user->id,
                'channel' => $channelEnum,
                'template_key' => $template->key,
                'notification_type' => $template->category,
                'recipient' => $recipient,
                'subject' => $rendered['subject'],
                'content' => $rendered['body'],
                'payload' => array_merge($variables, [
                    'whatsapp_template_name' => $rendered['whatsapp_template_name'],
                ]),
                'status' => $status,
                'is_mandatory' => $template->is_mandatory,
                'idempotency_key' => $idempotencyKey,
                'scheduled_at' => $scheduledAt,
            ]);

            $createdOutboxes->push($outbox);

            // 6. Process outbox delivery immediately if not held for quiet hours
            if (! $isQuiet) {
                if ($sync) {
                    dispatch_sync(new ProcessNotificationOutboxJob($outbox));
                } else {
                    dispatch(new ProcessNotificationOutboxJob($outbox));
                }
            }
        }

        return $createdOutboxes;
    }

    /**
     * Check if a channel is enabled in user's notification preferences.
     */
    public function isChannelEnabledForUser(User $user, NotificationChannel $channel, string $category): bool
    {
        /** @var ?NotificationPreference $preference */
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel->value)
            ->where('notification_type', $category)
            ->first();

        // If no explicit preference record, default to enabled
        return $preference ? $preference->is_enabled : true;
    }

    /**
     * Check if user is currently inside their configured quiet hours (timezone-aware).
     */
    public function isInQuietHours(User $user, ?CarbonInterface $at = null): bool
    {
        if (! $user->quiet_hours_start || ! $user->quiet_hours_end) {
            return false;
        }

        $tz = $user->timezone ?? config('app.timezone', 'Asia/Kolkata');
        $now = ($at ?? now())->setTimezone($tz);

        $startParts = explode(':', (string) $user->quiet_hours_start);
        $endParts = explode(':', (string) $user->quiet_hours_end);

        $start = CarbonImmutable::create(
            $now->year,
            $now->month,
            $now->day,
            (int) $startParts[0],
            (int) ($startParts[1] ?? 0),
            0,
            $tz
        );

        $end = CarbonImmutable::create(
            $now->year,
            $now->month,
            $now->day,
            (int) $endParts[0],
            (int) ($endParts[1] ?? 0),
            0,
            $tz
        );

        if ($start->greaterThan($end)) {
            // Overnight window (e.g. 22:00 to 07:00 next day)
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
    }

    /**
     * Calculate next quiet hours release timestamp.
     */
    public function getQuietHoursEndDateTime(User $user): CarbonInterface
    {
        $tz = $user->timezone ?? config('app.timezone', 'Asia/Kolkata');
        $now = now()->setTimezone($tz);
        $endParts = explode(':', (string) ($user->quiet_hours_end ?? '07:00:00'));

        $release = CarbonImmutable::create(
            $now->year,
            $now->month,
            $now->day,
            (int) $endParts[0],
            (int) ($endParts[1] ?? 0),
            0,
            $tz
        );

        if ($now->greaterThanOrEqualTo($release)) {
            $release = $release->addDay();
        }

        return $release;
    }

    protected function resolveRecipient(User $user, NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::Mail => $user->email,
            NotificationChannel::Database => (string) $user->id,
            NotificationChannel::WhatsApp, NotificationChannel::Sms => $user->phone ?? '+919999999999',
        };
    }

    /**
     * Create fallback template definition in database if not pre-seeded.
     */
    protected function createFallbackTemplate(string $key): NotificationTemplate
    {
        $isMandatory = in_array($key, [
            'payment_due',
            'payment_overdue',
            'seat_hold_expiring',
            'wellbeing_concern',
            'urgent_security_alert',
        ], true);

        $category = match (true) {
            str_contains($key, 'payment') || str_contains($key, 'seat_hold') => 'billing',
            str_contains($key, 'session') => 'academic',
            str_contains($key, 'concern') || str_contains($key, 'safety') => 'safety',
            default => 'reminders',
        };

        /** @var NotificationTemplate $template */
        $template = NotificationTemplate::create([
            'key' => $key,
            'name' => ucwords(str_replace('_', ' ', $key)),
            'category' => $category,
            'channels' => ['mail', 'database', 'whatsapp'],
            'is_mandatory' => $isMandatory,
            'default_variables' => [
                'institute_name' => 'Pramana Institute of Yogic Sciences',
            ],
            'version' => 1,
            'is_active' => true,
        ]);

        return $template;
    }
}
