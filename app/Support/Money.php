<?php

namespace App\Support;

use DomainException;
use InvalidArgumentException;

class Money
{
    private const MAX_CENTS = 9_999_999_999;

    public static function cents(string|int|float $amount): int
    {
        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', (string) $amount, $matches)) {
            throw new InvalidArgumentException('Money amounts must have at most two decimal places.');
        }

        $wholeDigits = ltrim($matches[1], '0') ?: '0';
        if (strlen($wholeDigits) > 8 || (strlen($wholeDigits) === 8 && strcmp($wholeDigits, '99999999') > 0)) {
            throw new DomainException('The calculated amount exceeds the supported order total.');
        }

        $whole = (int) $wholeDigits;
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');

        if ($whole > intdiv(self::MAX_CENTS - $fraction, 100)) {
            throw new DomainException('The calculated amount exceeds the supported order total.');
        }

        return ($whole * 100) + $fraction;
    }

    public static function lineSubtotalCents(string|int|float $unitPrice, int $quantityMilliUnits): int
    {
        return self::subtotalCentsFromUnitCents(self::cents($unitPrice), $quantityMilliUnits);
    }

    public static function subtotalCentsFromUnitCents(int $unitPriceCents, int $quantityMilliUnits): int
    {

        if ($unitPriceCents < 0 || $quantityMilliUnits < 0) {
            throw new InvalidArgumentException('Price and quantity cannot be negative.');
        }

        if ($unitPriceCents === 0 || $quantityMilliUnits === 0) {
            return 0;
        }

        if ($quantityMilliUnits > intdiv(self::MAX_CENTS * 1000, $unitPriceCents)) {
            throw new DomainException('The calculated item subtotal exceeds the supported order total.');
        }

        $priceTimesQuantity = $unitPriceCents * $quantityMilliUnits;

        return intdiv($priceTimesQuantity + 500, 1000);
    }

    public static function formatCents(int $amountCents): string
    {
        $absoluteCents = abs($amountCents);
        $formatted = sprintf('%d.%02d', intdiv($absoluteCents, 100), $absoluteCents % 100);

        return $amountCents < 0 ? '-'.$formatted : $formatted;
    }

    public static function formatCentsForDisplay(int $amountCents): string
    {
        $absoluteCents = abs($amountCents);
        $formatted = number_format(intdiv($absoluteCents, 100), 0, '.', ',')
            .'.'.sprintf('%02d', $absoluteCents % 100);

        return $amountCents < 0 ? '-'.$formatted : $formatted;
    }

    public static function ensureOrderTotalFits(int $amountCents): void
    {
        if ($amountCents < 0 || $amountCents > self::MAX_CENTS) {
            throw new DomainException('The calculated order total exceeds the supported amount.');
        }
    }
}
