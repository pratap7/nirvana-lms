<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyAdminSummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $summaryData
     */
    public function __construct(public array $summaryData) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Pramana LMS] Weekly Executive Operations & Financial Summary ('.$this->summaryData['week_range'].')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reporting.weekly-admin-summary',
        );
    }
}
