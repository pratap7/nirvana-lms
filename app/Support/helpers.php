<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Support\Money;
use App\Support\Settings\SettingsService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

if (! function_exists('toUserTimezone')) {
    /**
     * Convert any timestamp to user or default institute timezone for display.
     */
    function toUserTimezone(
        null|string|DateTimeInterface $date,
        ?string $timezone = null,
        ?string $format = 'd M Y, h:i A'
    ): ?string {
        if ($date === null) {
            return null;
        }

        if ($timezone !== null) {
            $tz = $timezone;
        } else {
            /** @var User|null $user */
            $user = (function_exists('app') && app()->bound('auth')) ? auth()->user() : null;
            $tz = ($user !== null ? $user->timezone : null)
                ?? ((function_exists('app') && app()->bound(SettingsService::class))
                    ? setting('default_timezone', config('app.timezone', 'Asia/Kolkata'))
                    : config('app.timezone', 'Asia/Kolkata'));
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $converted = $carbon->setTimezone($tz);

        return $format ? $converted->format($format) : $converted->toIso8601String();
    }
}

if (! function_exists('toUtc')) {
    /**
     * Parse any local timestamp and convert it strictly to UTC for database storage.
     */
    function toUtc(string|DateTimeInterface $date, ?string $sourceTimezone = null): CarbonImmutable
    {
        if ($sourceTimezone !== null) {
            $tz = $sourceTimezone;
        } else {
            /** @var User|null $user */
            $user = (function_exists('app') && app()->bound('auth')) ? auth()->user() : null;
            $tz = ($user !== null ? $user->timezone : null)
                ?? ((function_exists('app') && app()->bound(SettingsService::class))
                    ? setting('default_timezone', config('app.timezone', 'Asia/Kolkata'))
                    : config('app.timezone', 'Asia/Kolkata'));
        }

        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date)->setTimezone('UTC');
        }

        return CarbonImmutable::parse($date, $tz)->setTimezone('UTC');
    }
}

if (! function_exists('setting')) {
    /**
     * Get or set settings via the cached SettingsService.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        /** @var SettingsService $service */
        $service = app(SettingsService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('money')) {
    /**
     * Helper to create an immutable Money instance in paise.
     */
    function money(int $amount, string $currency = 'INR'): Money
    {
        return new Money($amount, $currency);
    }
}
