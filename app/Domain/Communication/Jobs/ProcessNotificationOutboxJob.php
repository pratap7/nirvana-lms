<?php

declare(strict_types=1);

namespace App\Domain\Communication\Jobs;

use App\Domain\Communication\Contracts\SmsProvider;
use App\Domain\Communication\Contracts\WhatsAppProvider;
use App\Domain\Communication\Enums\NotificationChannel;
use App\Domain\Communication\Models\NotificationOutbox;
use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessNotificationOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly NotificationOutbox $outbox
    ) {}

    public function handle(WhatsAppProvider $whatsAppProvider, SmsProvider $smsProvider): void
    {
        if ($this->outbox->status->value === 'sent' || $this->outbox->status->value === 'delivered') {
            return;
        }

        try {
            match ($this->outbox->channel) {
                NotificationChannel::Mail => $this->sendEmail(),
                NotificationChannel::Database => $this->sendDatabaseNotification(),
                NotificationChannel::WhatsApp => $this->sendWhatsApp($whatsAppProvider),
                NotificationChannel::Sms => $this->sendSms($smsProvider),
            };

            $this->outbox->markSent();
            $this->outbox->markDelivered();
        } catch (\Throwable $e) {
            $this->outbox->markFailed($e->getMessage());
            throw $e;
        }
    }

    protected function sendEmail(): void
    {
        $recipient = $this->outbox->recipient;
        $subject = $this->outbox->subject ?? 'Pramana Institute Notice';
        $body = $this->outbox->content;

        Mail::raw($body, function ($message) use ($recipient, $subject): void {
            $message->to($recipient)->subject($subject);
        });
    }

    protected function sendDatabaseNotification(): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\DomainNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->outbox->user_id,
            'data' => [
                'template_key' => $this->outbox->template_key,
                'notification_type' => $this->outbox->notification_type,
                'subject' => $this->outbox->subject,
                'message' => $this->outbox->content,
                'payload' => $this->outbox->payload,
                'outbox_ulid' => $this->outbox->ulid,
            ],
            'read_at' => null,
        ]);
    }

    protected function sendWhatsApp(WhatsAppProvider $provider): void
    {
        $phone = $this->outbox->recipient;
        $template = $this->outbox->template_key;
        $params = $this->outbox->payload ?? [];
        $lang = $this->outbox->user->locale ?? 'en';

        $provider->sendTemplate($phone, $template, $params, $lang);
    }

    protected function sendSms(SmsProvider $provider): void
    {
        $phone = $this->outbox->recipient;
        $message = $this->outbox->content;

        $provider->sendSms($phone, $message);
    }
}
