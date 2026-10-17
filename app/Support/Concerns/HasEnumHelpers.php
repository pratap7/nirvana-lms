<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Trait providing common utility methods for BackedEnums.
 */
trait HasEnumHelpers
{
    /**
     * Get an array of all enum values.
     *
     * @return array<int|string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get an array of all enum names.
     *
     * @return array<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Get key-value pairs formatted for HTML/Filament select options [value => Label].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Get human-readable label for this enum case.
     */
    public function label(): string
    {
        return Str::headline(str_replace('_', ' ', (string) $this->value));
    }

    /**
     * Try to find an enum case by its human-readable label.
     */
    public static function tryFromLabel(string $label): ?static
    {
        foreach (self::cases() as $case) {
            if (mb_strtolower($case->label()) === mb_strtolower(trim($label))) {
                return $case;
            }
        }

        return null;
    }
}
