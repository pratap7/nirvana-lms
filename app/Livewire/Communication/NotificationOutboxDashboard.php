<?php

declare(strict_types=1);

namespace App\Livewire\Communication;

use App\Domain\Communication\Enums\NotificationChannel;
use App\Domain\Communication\Enums\OutboxStatus;
use App\Domain\Communication\Jobs\ProcessNotificationOutboxJob;
use App\Domain\Communication\Models\NotificationOutbox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class NotificationOutboxDashboard extends Component
{
    use WithPagination;

    public string $selectedStatus = 'all';

    public string $selectedChannel = 'all';

    public string $search = '';

    public ?string $feedbackMessage = null;

    public function retry(int $outboxId): void
    {
        /** @var ?NotificationOutbox $outbox */
        $outbox = NotificationOutbox::find($outboxId);
        if ($outbox) {
            $outbox->retry();
            dispatch(new ProcessNotificationOutboxJob($outbox));
            $this->feedbackMessage = "Notification #{$outbox->id} requeued for immediate retry.";
        }
    }

    public function retryAllFailed(): void
    {
        $failedOutboxes = NotificationOutbox::query()
            ->where('status', OutboxStatus::Failed)
            ->get();

        foreach ($failedOutboxes as $outbox) {
            $outbox->retry();
            dispatch(new ProcessNotificationOutboxJob($outbox));
        }

        $this->feedbackMessage = "Requeued {$failedOutboxes->count()} failed notification(s).";
    }

    public function render(): View
    {
        $query = NotificationOutbox::query()
            ->with('user')
            ->latest('id');

        if ($this->selectedStatus !== 'all') {
            $query->where('status', $this->selectedStatus);
        }

        if ($this->selectedChannel !== 'all') {
            $query->where('channel', $this->selectedChannel);
        }

        if ($this->search !== '') {
            $query->where(function ($q): void {
                $q->where('recipient', 'like', "%{$this->search}%")
                    ->orWhere('template_key', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            });
        }

        $metrics = [
            'total' => NotificationOutbox::count(),
            'queued' => NotificationOutbox::where('status', OutboxStatus::Queued)->count(),
            'sent' => NotificationOutbox::whereIn('status', [OutboxStatus::Sent, OutboxStatus::Delivered])->count(),
            'failed' => NotificationOutbox::where('status', OutboxStatus::Failed)->count(),
            'held' => NotificationOutbox::where('status', OutboxStatus::HeldForQuietHours)->count(),
        ];

        return view('livewire.communication.notification-outbox-dashboard', [
            'outboxes' => $query->paginate(15),
            'metrics' => $metrics,
            'channels' => NotificationChannel::cases(),
            'statuses' => OutboxStatus::cases(),
        ]);
    }
}
