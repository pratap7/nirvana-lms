<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

/**
 * Formula Sanitizer to prevent CSV / Excel Formula Injection (CWE-1236).
 *
 * Spreadsheet engines (Microsoft Excel, LibreOffice Calc, Apple Numbers, Google Sheets)
 * automatically execute formulas if a cell starts with =, +, -, @, \t, or \r.
 * This sanitizer prepends a single quote (') so spreadsheets strictly parse the cell as text.
 */
class FormulaSanitizer
{
    /**
     * Dangerous formula trigger characters.
     *
     * @var list<string>
     */
    protected const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Sanitize a single cell value.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];

        if (in_array($firstChar, self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Sanitize an entire row (indexed or associative array).
     *
     * @param  array<string|int, mixed>  $row
     * @return array<string|int, mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        $sanitized = [];

        foreach ($row as $key => $value) {
            $sanitized[$key] = self::sanitize($value);
        }

        return $sanitized;
    }
}
