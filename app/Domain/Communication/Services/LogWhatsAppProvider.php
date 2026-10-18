<?php

declare(strict_types=1);

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Log;

class LogWhatsAppProvider implements WhatsAppProvider
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $sentMessages = [];

    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $language = 'en'): bool
    {
        $payload = [
            'to' => $to,
            'template' => $templateName,
            'parameters' => $parameters,
            'language' => $language,
            'sent_at' => now()->toIso8601String(),
        ];

        $this->sentMessages[] = $payload;

        Log::channel('single')->info("WhatsApp Template Sent to [{$to}]: template={$templateName}, lang={$language}", $payload);

        return true;
    }
}
