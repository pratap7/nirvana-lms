<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Notification Template Model (Section H).
 * Defines multi-channel templates with variable placeholders, versioning, and mandatory override flags.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property string $name
 * @property string $category
 * @property array<string> $channels
 * @property bool $is_mandatory
 * @property ?array<string, mixed> $default_variables
 * @property int $version
 * @property bool $is_active
 */
class NotificationTemplate extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'key',
        'name',
        'category',
        'channels',
        'is_mandatory',
        'default_variables',
        'version',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_mandatory' => 'boolean',
            'default_variables' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<NotificationTemplateTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(NotificationTemplateTranslation::class, 'template_id');
    }

    /**
     * Render a template for a specific channel, locale, and variables payload.
     *
     * @param  array<string, mixed>  $variables
     * @return array{subject: ?string, body: string, whatsapp_template_name: ?string}
     */
    public function render(string $channel, string $locale = 'en', array $variables = []): array
    {
        /** @var ?NotificationTemplateTranslation $translation */
        $translation = $this->translations()
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->first();

        // Fallback to English if requested locale is missing
        if (! $translation && $locale !== 'en') {
            $translation = $this->translations()
                ->where('channel', $channel)
                ->where('locale', 'en')
                ->first();
        }

        // Generic fallback if translation is not found
        if (! $translation) {
            $name = $variables['student_name'] ?? 'Student';
            $title = $variables['session_title'] ?? $variables['title'] ?? 'LMS Notice';

            return [
                'subject' => "[Pramana] {$this->name}: {$title}",
                'body' => "Namaste {$name}, this is a notification regarding: {$title}.",
                'whatsapp_template_name' => $this->key,
            ];
        }

        $allVars = array_merge($this->default_variables ?? [], $variables);

        $body = $this->interpolateVariables($translation->body, $allVars);
        $subject = $translation->subject ? $this->interpolateVariables($translation->subject, $allVars) : null;

        return [
            'subject' => $subject,
            'body' => $body,
            'whatsapp_template_name' => $translation->whatsapp_template_name ?? $this->key,
        ];
    }

    /**
     * Replace {{ variable_name }} with values.
     *
     * @param  array<string, mixed>  $variables
     */
    private function interpolateVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $content = str_replace(
                    ['{{ '.$key.' }}', '{{'.$key.'}}'],
                    (string) $value,
                    $content
                );
            }
        }

        return $content;
    }
}
