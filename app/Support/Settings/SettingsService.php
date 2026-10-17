<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    private const string CACHE_KEY = 'app_settings_all';

    /**
     * Get a setting value by key, with optional fallback default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    /**
     * Set/update a setting value and invalidate the cache.
     */
    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isPublic = false): void
    {
        $serializedValue = is_array($value) ? json_encode($value) : (string) $value;

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $serializedValue,
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
            ]
        );

        $this->flush();
    }

    /**
     * Check if a setting key exists.
     */
    public function has(string $key): bool
    {
        $all = $this->all();

        return array_key_exists($key, $all);
    }

    /**
     * Invalidate the settings cache.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retrieve all settings mapped as [key => typedValue].
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $settings = Setting::all();
            $mapped = [];

            foreach ($settings as $setting) {
                $mapped[$setting->key] = $setting->getTypedValue();
            }

            return $mapped;
        });
    }
}
