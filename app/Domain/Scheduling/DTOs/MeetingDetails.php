<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

final readonly class MeetingDetails
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $meetingLink,
        public ?string $meetingId = null,
        public ?string $password = null,
        public array $rawPayload = [],
    ) {}
}
