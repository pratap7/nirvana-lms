<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_instantiation_from_minor_and_major_units(): void
    {
        $m1 = Money::fromMinor(150000, 'INR'); // 1,500.00 INR
        $this->assertSame(150000, $m1->getAmount());
        $this->assertSame(150000, $m1->getMinorAmount());
        $this->assertSame('INR', $m1->getCurrency());
        $this->assertSame(1500.0, $m1->toMajor());

        $m2 = Money::fromMajor(1500.50, 'INR');
        $this->assertSame(150050, $m2->getAmount());

        $zero = Money::zero('INR');
        $this->assertTrue($zero->isZero());
        $this->assertSame(0, $zero->getAmount());
    }

    public function test_negative_amounts_are_rejected_with_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(-100, 'INR');
    }

    public function test_unsupported_currencies_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(100, 'XYZ');
    }

    public function test_arithmetic_addition_and_multiplication(): void
    {
        $a = Money::fromMinor(1000, 'INR');
        $b = Money::fromMinor(2500, 'INR');

        $sum = $a->add($b);
        $this->assertSame(3500, $sum->getAmount());

        $product = $a->multiply(2.5);
        $this->assertSame(2500, $product->getAmount());
    }

    public function test_arithmetic_subtraction_and_negative_underflow_prevention(): void
    {
        $a = Money::fromMinor(5000, 'INR');
        $b = Money::fromMinor(2000, 'INR');

        $diff = $a->subtract($b);
        $this->assertSame(3000, $diff->getAmount());

        // Subtracting larger amount throws exception
        $this->expectException(InvalidArgumentException::class);
        $b->subtract($a);
    }

    public function test_cannot_perform_arithmetic_on_different_currencies(): void
    {
        $inr = Money::fromMinor(1000, 'INR');
        $usd = Money::fromMinor(1000, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $inr->add($usd);
    }

    public function test_comparison_methods(): void
    {
        $a = Money::fromMinor(1000, 'INR');
        $b = Money::fromMinor(2000, 'INR');
        $c = Money::fromMinor(1000, 'INR');

        $this->assertTrue($b->isGreaterThan($a));
        $this->assertTrue($a->isLessThan($b));
        $this->assertTrue($a->equals($c));
        $this->assertFalse($a->equals($b));
    }

    public function test_indian_rupee_formatting_with_lakhs_and_crores(): void
    {
        $money = Money::fromMinor(12345678, 'INR'); // 1,23,456.78
        $this->assertSame('₹1,23,456.78', $money->format());
        $this->assertSame('₹1,23,456.78', (string) $money);

        $usd = Money::fromMinor(12345678, 'USD'); // 123,456.78
        $this->assertSame('$123,456.78', $usd->format());
    }

    public function test_json_and_array_serialization(): void
    {
        $money = Money::fromMinor(50000, 'INR');
        $array = $money->toArray();

        $this->assertSame(['amount' => 50000, 'currency' => 'INR'], $array);
        $this->assertSame('{"amount":50000,"currency":"INR"}', json_encode($money));
    }
}
