<?php

namespace App\Support;

use InvalidArgumentException;

class Quantity
{
    public static function isWeightUnit(?string $unit): bool
    {
        return strtolower(trim((string) $unit)) === 'kg';
    }

    /**
     * @return array<int, string>
     */
    public static function validationRules(?string $unit, bool $allowZero = false): array
    {
        if (self::isWeightUnit($unit)) {
            return [
                'required',
                'numeric',
                $allowZero ? 'min:0' : 'min:0.001',
                'max:9999999999.999',
                'decimal:0,3',
            ];
        }

        return ['required', 'integer', $allowZero ? 'min:0' : 'min:1', 'max:2147483647'];
    }

    public static function milliUnits(string|int|float $quantity): int
    {
        if (is_float($quantity)) {
            $quantity = (string) $quantity;
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', (string) $quantity, $matches)) {
            throw new InvalidArgumentException('Quantity must be a non-negative number with at most three decimal places.');
        }

        $wholeDigits = ltrim($matches[1], '0') ?: '0';
        if (strlen($wholeDigits) > 10 || (strlen($wholeDigits) === 10 && strcmp($wholeDigits, '9999999999') > 0)) {
            throw new InvalidArgumentException('Quantity exceeds the supported stock capacity.');
        }

        $whole = (int) $wholeDigits;
        $fraction = (int) str_pad($matches[2] ?? '', 3, '0');

        return ($whole * 1000) + $fraction;
    }

    public static function normalize(string|int|float $quantity): string
    {
        $milliUnits = self::milliUnits($quantity);

        return sprintf('%d.%03d', intdiv($milliUnits, 1000), $milliUnits % 1000);
    }

    public static function format(string|int|float $quantity): string
    {
        return self::formatMilliUnits(self::milliUnits($quantity));
    }

    public static function formatMilliUnits(int $milliUnits): string
    {
        if ($milliUnits < 0) {
            throw new InvalidArgumentException('Quantity must be non-negative.');
        }

        $whole = intdiv($milliUnits, 1000);
        $fraction = rtrim(sprintf('%03d', $milliUnits % 1000), '0');

        return $fraction === '' ? (string) $whole : "{$whole}.{$fraction}";
    }

    public static function add(string|int|float $first, string|int|float $second): string
    {
        $totalMilliUnits = self::milliUnits($first) + self::milliUnits($second);

        return sprintf('%d.%03d', intdiv($totalMilliUnits, 1000), $totalMilliUnits % 1000);
    }

    public static function isValid(string|int|float $quantity): bool
    {
        try {
            self::milliUnits($quantity);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
