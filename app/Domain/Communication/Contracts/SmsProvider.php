<?php

declare(strict_types=1);

namespace App\Domain\Communication\Contracts;

/**
 * Optional SMS Driver Contract (Section H).
 */
interface SmsProvider
{
    /**
     * Send an SMS text message to an E.164 formatted telephone number.
     */
    public function sendSms(string $to, string $message): bool;
}
