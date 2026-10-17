<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property ?string $value
 * @property string $type
 * @property string $group
 * @property bool $is_public
 * @property ?string $description
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_public',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get typed value.
     */
    public function getTypedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'int', 'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'bool', 'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
