<?php

declare(strict_types=1);

use App\Support\Settings\SettingsService;
use Database\Seeders\SettingSeeder;

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
});

it('retrieves seeded settings using the global helper', function (): void {
    expect(setting('institute_name'))->toBe('Pramana Institute of Yoga & Indic Sciences')
        ->and(setting('default_currency'))->toBe('INR')
        ->and(setting('default_timezone'))->toBe('Asia/Kolkata')
        ->and(setting('gst_number'))->toBe('05AAAAA0000A1Z5');
});

it('returns default fallback when setting key does not exist', function (): void {
    expect(setting('non_existent_key', 'fallback_value'))->toBe('fallback_value');
});

it('sets and updates settings with automatic cache invalidation', function (): void {
    /** @var SettingsService $service */
    $service = app(SettingsService::class);

    $service->set('max_batch_size', 25, 'int', 'academic');
    expect(setting('max_batch_size'))->toBe(25);

    $service->set('is_registration_open', false, 'bool', 'academic');
    expect(setting('is_registration_open'))->toBeFalse();

    $service->set('social_links', ['youtube' => 'https://youtube.com/@pramana'], 'json', 'social');
    expect(setting('social_links'))->toBe(['youtube' => 'https://youtube.com/@pramana']);
});
