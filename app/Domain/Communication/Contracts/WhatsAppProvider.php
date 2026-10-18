<?php

declare(strict_types=1);

namespace App\Domain\Communication\Contracts;

/**
 * WhatsApp Business API Driver Contract (Section H).
 * Enforces template-based messaging required by Meta WhatsApp Business API.
 */
interface WhatsAppProvider
{
    /**
     * Send an approved template message to a customer/student WhatsApp phone.
     *
     * @param  string  $to  E.164 formatted international phone number (e.g. +919876543210)
     * @param  string  $templateName  Registered Meta template identifier
     * @param  array<string, mixed>  $parameters  Positional or named template parameters
     * @param  string  $language  Template language code (e.g. 'en', 'hi')
     * @return bool True if accepted by provider gateway
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $language = 'en'): bool;
}
