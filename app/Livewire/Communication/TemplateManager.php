<?php

declare(strict_types=1);

namespace App\Livewire\Communication;

use App\Domain\Communication\Models\NotificationTemplate;
use App\Domain\Communication\Models\NotificationTemplateTranslation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TemplateManager extends Component
{
    public ?int $selectedTemplateId = null;

    public string $previewLocale = 'en';

    public string $previewChannel = 'mail';

    public ?string $feedbackMessage = null;

    // Form inputs for translation editing
    public string $editSubject = '';

    public string $editBody = '';

    public string $editWhatsappTemplate = '';

    public function selectTemplate(int $id): void
    {
        $this->selectedTemplateId = $id;
        $this->loadTranslation();
    }

    public function updatedPreviewLocale(): void
    {
        $this->loadTranslation();
    }

    public function updatedPreviewChannel(): void
    {
        $this->loadTranslation();
    }

    public function loadTranslation(): void
    {
        if (! $this->selectedTemplateId) {
            return;
        }

        /** @var ?NotificationTemplateTranslation $translation */
        $translation = NotificationTemplateTranslation::query()
            ->where('template_id', $this->selectedTemplateId)
            ->where('locale', $this->previewLocale)
            ->where('channel', $this->previewChannel)
            ->first();

        if ($translation) {
            $this->editSubject = $translation->subject ?? '';
            $this->editBody = $translation->body;
            $this->editWhatsappTemplate = $translation->whatsapp_template_name ?? '';
        } else {
            $this->editSubject = '';
            $this->editBody = '';
            $this->editWhatsappTemplate = '';
        }
    }

    public function saveTranslation(): void
    {
        if (! $this->selectedTemplateId) {
            return;
        }

        /** @var NotificationTemplate $template */
        $template = NotificationTemplate::findOrFail($this->selectedTemplateId);

        /** @var NotificationTemplateTranslation $translation */
        $translation = NotificationTemplateTranslation::updateOrCreate(
            [
                'template_id' => $template->id,
                'locale' => $this->previewLocale,
                'channel' => $this->previewChannel,
            ],
            [
                'subject' => $this->editSubject ?: null,
                'body' => $this->editBody,
                'whatsapp_template_name' => $this->editWhatsappTemplate ?: null,
                'version' => ($template->version ?? 1) + 1,
            ]
        );

        $template->increment('version');

        $this->feedbackMessage = "Updated {$this->previewLocale} ({$this->previewChannel}) translation to version {$template->version}.";
    }

    public function render(): View
    {
        $templates = NotificationTemplate::query()->with('translations')->get();

        $selectedTemplate = $this->selectedTemplateId
            ? NotificationTemplate::with('translations')->find($this->selectedTemplateId)
            : $templates->first();

        if ($selectedTemplate && ! $this->selectedTemplateId) {
            $this->selectedTemplateId = $selectedTemplate->id;
            $this->loadTranslation();
        }

        // Live preview with sample variables
        $sampleVariables = [
            'student_name' => 'Aarav Sharma',
            'session_title' => 'Pranayama & Kriya Sadhana',
            'course_name' => 'Hatha Yoga Teacher Training 200h',
            'old_starts_at' => 'Oct 20, 2026 06:30 AM',
            'new_starts_at' => 'Oct 21, 2026 07:00 AM',
            'starts_at' => '06:30 AM',
            'due_date' => 'Oct 25, 2026',
            'due_amount' => '₹15,000',
            'invoice_number' => 'INV-2026-0042',
            'expires_at' => '11:59 PM',
            'reason' => 'Vedic festival observance',
        ];

        $renderedPreview = $selectedTemplate
            ? $selectedTemplate->render($this->previewChannel, $this->previewLocale, $sampleVariables)
            : ['subject' => '', 'body' => '', 'whatsapp_template_name' => ''];

        return view('livewire.communication.template-manager', [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'renderedPreview' => $renderedPreview,
        ]);
    }
}
