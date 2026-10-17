<?php

declare(strict_types=1);

use App\Support\Money;

it('creates money in smallest currency unit (paise)', function (): void {
    $money = new Money(150000, 'INR');

    expect($money->amount)->toBe(150000)
        ->and($money->currency)->toBe('INR')
        ->and($money->toMajor())->toBe(1500.00);
});

it('creates money from major units', function (): void {
    $money = Money::fromMajor(2499.50, 'INR');

    expect($money->amount)->toBe(249950)
        ->and($money->toMajor())->toBe(2499.50);
});

it('formats money in Indian numbering format', function (): void {
    $fee = Money::fromMajor(125000, 'INR');
    expect($fee->format())->toBe('₹1,25,000.00');

    $small = Money::fromMajor(500, 'INR');
    expect($small->format())->toBe('₹500.00');
});

it('performs arithmetic safely without floats', function (): void {
    $a = new Money(10000, 'INR'); // ₹100
    $b = new Money(5050, 'INR');  // ₹50.50

    $sum = $a->add($b);
    expect($sum->amount)->toBe(15050)
        ->and($sum->format())->toBe('₹150.50');

    $diff = $a->subtract($b);
    expect($diff->amount)->toBe(4950);

    $multiplied = $b->multiply(2);
    expect($multiplied->amount)->toBe(10100);
});

it('throws exception on negative amount', function (): void {
    new Money(-100, 'INR');
})->throws(InvalidArgumentException::class);

it('throws exception when subtracting more than amount', function (): void {
    $small = new Money(100, 'INR');
    $big = new Money(500, 'INR');

    $small->subtract($big);
})->throws(InvalidArgumentException::class);

it('throws exception on cross currency arithmetic', function (): void {
    $inr = new Money(10000, 'INR');
    $usd = new Money(10000, 'USD');

    $inr->add($usd);
})->throws(InvalidArgumentException::class);
