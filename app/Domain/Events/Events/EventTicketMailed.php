<?php

declare(strict_types=1);

namespace App\Domain\Events\Events;

use App\Domain\Events\Models\EventRegistration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventTicketMailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly EventRegistration $registration
    ) {}
}
