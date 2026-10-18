<?php

declare(strict_types=1);

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProvider
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $sentMessages = [];

    public function sendSms(string $to, string $message): bool
    {
        $payload = [
            'to' => $to,
            'message' => $message,
            'sent_at' => now()->toIso8601String(),
        ];

        $this->sentMessages[] = $payload;

        Log::channel('single')->info("SMS Sent to [{$to}]: {$message}", $payload);

        return true;
    }
}
