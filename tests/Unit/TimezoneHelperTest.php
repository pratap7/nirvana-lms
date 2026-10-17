<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;

it('converts UTC timestamp to user local timezone', function (): void {
    // 06:00 UTC is 11:30 AM IST (Asia/Kolkata +05:30)
    $utcDate = Carbon::parse('2026-10-17 06:00:00', 'UTC');

    $formatted = toUserTimezone($utcDate, 'Asia/Kolkata', 'Y-m-d H:i');

    expect($formatted)->toBe('2026-10-17 11:30');
});

it('converts local timestamp to strict UTC for database storage', function (): void {
    $istString = '2026-10-17 11:30:00';

    $utc = toUtc($istString, 'Asia/Kolkata');

    expect($utc)->toBeInstanceOf(CarbonImmutable::class)
        ->and($utc->timezoneName)->toBe('UTC')
        ->and($utc->format('Y-m-d H:i:s'))->toBe('2026-10-17 06:00:00');
});

it('creates money instance via helper function', function (): void {
    $m = money(250000, 'INR');

    expect($m->amount)->toBe(250000)
        ->and($m->format())->toBe('₹2,500.00');
});
