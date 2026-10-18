<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-Language & Multi-Channel Template Translation.
 *
 * @property int $id
 * @property int $template_id
 * @property string $locale
 * @property string $channel
 * @property ?string $subject
 * @property string $body
 * @property ?string $whatsapp_template_name
 * @property int $version
 * @property-read ?NotificationTemplate $template
 */
class NotificationTemplateTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'locale',
        'channel',
        'subject',
        'body',
        'whatsapp_template_name',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
