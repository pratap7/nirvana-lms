<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Money value object.
 *
 * Stores monetary values in the smallest currency unit (e.g. paise for INR).
 * Never uses floats for arithmetic — all operations are integer-based.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /** @var array<string, array{symbol: string, decimals: int, name: string}> */
    private const array CURRENCIES = [
        'INR' => ['symbol' => '₹', 'decimals' => 2, 'name' => 'Indian Rupee'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'name' => 'US Dollar'],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'name' => 'Euro'],
        'GBP' => ['symbol' => '£', 'decimals' => 2, 'name' => 'British Pound'],
    ];

    public function __construct(
        public int $amount,
        public string $currency = 'INR',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative. Use a separate sign/direction if needed.');
        }

        if (! isset(self::CURRENCIES[$currency])) {
            throw new InvalidArgumentException("Unsupported currency: {$currency}");
        }
    }

    /**
     * Create from a major-unit value (e.g. rupees → paise).
     */
    public static function fromMajor(float|int|string $majorUnits, string $currency = 'INR'): self
    {
        $decimals = self::CURRENCIES[$currency]['decimals'] ?? 2;
        $amount = (int) round((float) $majorUnits * (10 ** $decimals));

        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'INR'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new InvalidArgumentException('Money subtraction would result in a negative amount.');
        }

        return new self($result, $this->currency);
    }

    public function multiply(int|float $factor): self
    {
        return new self(
            (int) round($this->amount * $factor),
            $this->currency,
        );
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * Get the major-unit representation (e.g. 150000 paise → 1500.00).
     */
    public function toMajor(): float
    {
        $decimals = self::CURRENCIES[$this->currency]['decimals'];

        return $this->amount / (10 ** $decimals);
    }

    /**
     * Format for display (e.g. "₹1,500.00").
     */
    public function format(): string
    {
        $config = self::CURRENCIES[$this->currency];
        $major = $this->toMajor();

        if ($this->currency === 'INR') {
            return $config['symbol'].$this->formatIndian($major);
        }

        return $config['symbol'].number_format($major, $config['decimals']);
    }

    /**
     * Indian number formatting (1,23,456.00).
     */
    private function formatIndian(float $amount): string
    {
        $decimals = self::CURRENCIES[$this->currency]['decimals'];
        $formatted = number_format($amount, $decimals);

        $parts = explode('.', $formatted);
        $integer = $parts[0];
        $decimal = $parts[1] ?? str_repeat('0', $decimals);

        $integer = str_replace(',', '', $integer);
        $lastThree = substr($integer, -3);
        $rest = substr($integer, 0, -3);

        if ($rest !== '') {
            $lastThree = ','.$lastThree;
            $rest = preg_replace('/(\d)(?=(\d{2})+$)/', '$1,', $rest);
        }

        return $rest.$lastThree.'.'.$decimal;
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot perform arithmetic on different currencies: {$this->currency} vs {$other->currency}",
            );
        }
    }
}
